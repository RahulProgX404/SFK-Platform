<?php

/*
 * アプリ管理 (user, 2026-11-11; AppAbility::Manage): a row of an app's or a space's アクセス権 makes its users the
 * administrators of that app only — every other ability, アプリの設定 (一覧, 設定, 帳票出力), the buttons and steps for
 * administrators and no レコード / フィールドのアクセス権 limits — but never ダッシュボード設定 / 共通管理 / ユーザー管理.
 */

use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Access\AppAccess;
use Modules\Core\Access\FieldAccess;
use Modules\Core\Access\RecordAccess;
use Modules\Core\Application\Apps\AppActionNotAllowed;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\FieldAccessLevel;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\AppSettingsResource;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages\EditAppSettings;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\DocumentOutput;
use Modules\Core\Models\Space;
use Modules\MasterData\Filament\Resources\Machines\MachineResource;
use Modules\MasterData\Filament\Resources\Machines\Pages\ListMachines;
use Modules\MasterData\Filament\Resources\Machines\Pages\MachineSettings;
use Modules\Purchasing\Application\ConsumableOrders\ConsumableOrderWorkflow;
use Modules\Purchasing\Domain\ConsumableOrders\ConsumableOrderStatus;
use Modules\Purchasing\Models\ConsumableOrder;

/** 03. 機械マスタ published in a space of 白石封筒, with the given row for each of the users (registry sync). */
function managedMachineApp(): BusinessApp
{
    app(AppLifecycle::class)->syncWithCode(['machine-master' => '03. 機械マスタ'], ['machine-master' => MachineResource::appSettingDefaults()]);
    $app = BusinessApp::query()->where('code', 'machine-master')->sole();
    $app->forceFill(['status' => AppStatus::Published])->save();
    $app->spaces()->syncWithoutDetaching([Space::query()->firstOrCreate(['panel_id' => 'erp', 'name' => '製造スペース'])->id]);

    return $app;
}

/** @param  list<AppAbility>  $abilities */
function machineMember(BusinessApp $app, array $abilities, string $name): User
{
    $user = grantDashboards(User::factory()->create(['name' => $name]), 'erp');
    $app->spaces->first()?->users()->attach($user);
    $columns = [];
    foreach (AppAbility::cases() as $ability) {
        $columns[$ability->column()] = in_array($ability, $abilities, true);
    }
    $app->permissions()->create(['subject_type' => PermissionSubject::User, 'user_id' => $user->id, ...$columns]);
    Space::forgetCached();

    return $user;
}

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->machines = managedMachineApp();
    $this->manager = machineMember($this->machines, [AppAbility::Manage], '管理 太郎');
    $this->editor = machineMember($this->machines, [AppAbility::View, AppAbility::Create, AppAbility::Update, AppAbility::Delete], '編集 花子');
});

it('ticks every other ability with アプリ管理, in the row and in the access', function () {
    $row = $this->machines->permissions()->where('user_id', $this->manager->id)->sole();

    expect($row->abilities())->toBe(AppAbility::cases())
        ->and((bool) $row->can_view && (bool) $row->can_export && (bool) $row->can_delete)->toBeTrue()
        ->and(AppAccess::abilitiesFor($this->manager, $this->machines->fresh()))->toBe(AppAbility::cases())
        ->and(AppAccess::manages($this->manager, 'machine-master'))->toBeTrue()
        ->and(AppAccess::manages($this->editor, 'machine-master'))->toBeFalse()
        // the app only
        ->and(AppAccess::manages($this->manager, 'partner-master'))->toBeFalse()
        ->and($this->manager->user_type->isAdministrator())->toBeFalse()
        // administrators always
        ->and(AppAccess::manages(User::factory()->admin()->create(), 'machine-master'))->toBeTrue();

    // through a space's アクセス権 too
    $space = $this->machines->spaces->first();
    $member = grantDashboards(User::factory()->create(), 'erp');
    $space->users()->attach($member);
    $space->permissions()->create(['subject_type' => PermissionSubject::User, 'user_id' => $member->id, 'can_view' => true, 'can_manage' => true]);
    Space::forgetCached();
    expect(AppAccess::abilitiesFor($member, $this->machines->fresh()))->toBe(AppAbility::cases());

    // メンテナンス中: read-only like everyone else
    $this->machines->forceFill(['status' => AppStatus::Maintenance])->save();
    expect(AppAccess::abilitiesFor($this->manager, $this->machines->fresh()))->toBe([AppAbility::View, AppAbility::Export]);
});

it('opens アプリの設定 of that app to アプリ管理, without the links into ダッシュボード設定', function () {
    $this->actingAs($this->manager);
    $this->get(MachineSettings::getUrl())->assertOk()->assertSee('インライン編集を有効にする');
    $this->get(MachineResource::getUrl('views'))->assertOk();

    $menu = array_column(Livewire::test(ListMachines::class)->instance()->appChrome()['settings_menu'], 'label');
    expect($menu)->toContain('一覧', '設定', 'その他の設定', '帳票出力')
        ->not->toContain('アクセス権');

    // アプリ名 (アプリの設定 → 設定)
    app(AppLifecycle::class)->rename($this->machines, '機械一覧', '03. 機械マスタ', $this->manager);
    expect($this->machines->fresh()->name)->toBe('機械一覧');

    // never ダッシュボード設定
    $this->get('/erp/dashboard-settings/apps')->assertForbidden();
    $this->get('/erp/dashboard-settings/spaces')->assertForbidden();
    $this->get(AppSettingsResource::getUrl('edit', ['record' => $this->machines]))->assertForbidden();
    $this->get('/settings')->assertForbidden();
    Filament::setCurrentPanel('erp'); // the request above switched to 共通管理

    // without アプリ管理: none of it
    $this->actingAs($this->editor);
    $this->get(MachineSettings::getUrl())->assertForbidden();
    $this->get(MachineResource::getUrl('views'))->assertForbidden();
    expect(Livewire::test(ListMachines::class)->instance()->appChrome()['settings_menu'])->toBe([]);
    expect(fn () => app(AppLifecycle::class)->rename($this->machines, '機械', '03. 機械マスタ', $this->editor))
        ->toThrow(AppActionNotAllowed::class);
});

it('lifts the レコード / フィールドのアクセス権 and opens the 帳票 for administrators to アプリ管理, in that app', function () {
    $this->machines->fieldPermissions()->create(['field' => 'name', 'sort_order' => 1, 'subject_type' => 'everyone', 'access' => FieldAccessLevel::None]);

    expect(FieldAccess::levels($this->manager, $this->machines))->toBe([])
        ->and(FieldAccess::levels($this->editor, $this->machines))->toBe(['name' => FieldAccessLevel::None])
        ->and(RecordAccess::restricts($this->manager, $this->machines))->toBeFalse()
        ->and(RecordAccess::restricts($this->editor, $this->machines))->toBeTrue();

    $output = (new DocumentOutput(['admin_only' => true]))->setRelation('app', $this->machines);
    expect($output->isAvailableTo($this->manager))->toBeTrue()
        ->and($output->isAvailableTo($this->editor))->toBeFalse();
});

it('lets アプリ管理 of 20. 消耗品発注 approve — the step for administrators — and nobody else', function () {
    $orders = new BusinessApp(['code' => ConsumableOrder::APP_CODE, 'name' => '20. 消耗品発注']);
    $orders->forceFill(['status' => AppStatus::Published])->save();
    $orders->spaces()->attach($this->machines->spaces->first());
    $orders->permissions()->create(['subject_type' => PermissionSubject::User, 'user_id' => $this->manager->id, 'can_manage' => true]);
    $orders->permissions()->create(['subject_type' => PermissionSubject::User, 'user_id' => $this->editor->id, 'can_view' => true, 'can_update' => true]);

    $checking = (new ConsumableOrder)->forceFill(['status' => ConsumableOrderStatus::Checking]);
    $workflow = app(ConsumableOrderWorkflow::class);

    expect($workflow->canAdvance($checking, $this->manager))->toBeTrue()
        ->and($workflow->canAdvance($checking, $this->editor))->toBeFalse();
});

it('ticks the other abilities when アプリ管理 is ticked in アプリの権限', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(EditAppSettings::class, ['record' => $this->machines->getRouteKey()])
        ->fillForm(['permissions' => [['subject_type' => 'everyone', 'can_manage' => true]]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->machines->permissions()->where('subject_type', 'everyone')->sole()->abilities())->toBe(AppAbility::cases());
});
