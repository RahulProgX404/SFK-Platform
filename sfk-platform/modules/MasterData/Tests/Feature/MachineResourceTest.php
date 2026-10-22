<?php

/*
 * App 03. 機械マスタ (SYSTEM_BRAIN APP_03): legacy form/record/list, unique 機械コード / 機械名 and the seven rate types
 * (APP_03 "MUST be preserved", rates with decimals), the legacy view 機械一覧, the app features of its template
 * (一括削除 off) and access through the app permissions.
 */

use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Money\UnitPrice;
use Modules\Core\Domain\Money\Yen;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\RecordComment;
use Modules\Core\Models\RecordVersion;
use Modules\Core\Models\Space;
use Modules\MasterData\Filament\Resources\Machines\MachineResource;
use Modules\MasterData\Filament\Resources\Machines\Pages\CreateMachine;
use Modules\MasterData\Filament\Resources\Machines\Pages\EditMachine;
use Modules\MasterData\Filament\Resources\Machines\Pages\ListMachines;
use Modules\MasterData\Filament\Resources\Machines\Pages\MachineSettings;
use Modules\MasterData\Filament\Resources\Machines\Pages\ViewMachine;
use Modules\MasterData\Models\Machine;

function makeMachine(array $attributes = []): Machine
{
    return Machine::query()->create(['code' => 'M01', 'name' => '1号機', ...$attributes]);
}

/** Publishes 03. 機械マスタ in a space of 白石封筒, as アプリ設定 would add it (registry sync). */
function publishedMachineApp(): BusinessApp
{
    app(AppLifecycle::class)->syncWithCode(['machine-master' => '03. 機械マスタ'], ['machine-master' => MachineResource::appSettingDefaults()]);
    $space = Space::query()->firstOrCreate(['panel_id' => 'erp', 'name' => '製造スペース']);
    $app = BusinessApp::query()->where('code', 'machine-master')->sole();
    $app->forceFill(['status' => AppStatus::Published])->save();
    $app->spaces()->syncWithoutDetaching([$space->id]);

    return $app;
}

function machineEmployeeWith(array $abilities): User
{
    $app = publishedMachineApp();
    $columns = [];
    foreach (AppAbility::cases() as $ability) {
        $columns[$ability->column()] = in_array($ability, $abilities, true);
    }
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, ...$columns]);

    $employee = grantDashboards(User::factory()->create(), 'erp');
    $app->spaces->first()?->users()->attach($employee);

    return $employee;
}

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->admin = User::factory()->superAdmin()->create(['name' => '管理者']);
});

it('registers itself as an app from the code, with 一括削除 off like its legacy template', function () {
    $discovery = new AppDiscovery([base_path('modules')]);
    expect($discovery->definitions())->toHaveKey('machine-master', '03. 機械マスタ')
        ->and($discovery->settingDefaults())->toHaveKey('machine-master', ['bulk_delete_enabled' => false]);

    $app = publishedMachineApp();
    expect($app->bulk_delete_enabled)->toBeFalse()
        ->and($app->record_comments_enabled)->toBeTrue()
        ->and($app->inline_edit_enabled)->toBeTrue()
        ->and($app->record_duplicate_enabled)->toBeTrue()
        ->and($app->record_history_enabled)->toBeTrue();
});

it('creates a machine with the seven rates, decimals allowed, and shows the saved record', function () {
    $this->actingAs($this->admin);

    $component = Livewire::test(CreateMachine::class)
        ->fillForm([
            'code' => 'M01',
            'name' => '1号機',
            'processing_rate' => '0.35',
            'changeover_rate' => '3000',
            'handwork_rate' => '1200',
            'machine_rate' => '4500',
            'printing_rate' => '1.5',
            'plate_change_rate' => '2500',
            'ink_mixing_rate' => '800',
            'notes' => '窓貼機',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $machine = Machine::query()->sole();
    $component->assertRedirect(MachineResource::getUrl('view', ['record' => $machine]));

    expect($machine->processing_rate)->toBe('0.3500')
        ->and($machine->changeover_rate)->toBe('3000.0000')
        ->and($machine->created_by)->toBe($this->admin->id)
        // job costing: 出来上 × 加工単価(枚) in whole yen (Core Yen)
        ->and(Yen::round(Yen::multiply(1001, $machine->processing_rate), RoundingMode::HalfUp))->toBe(350);

    Livewire::test(ViewMachine::class, ['record' => $machine->getRouteKey()])
        ->assertSeeInOrder(['機械コード', 'M01', '機械名', '1号機', '加工単価(枚)', '¥0.35', '型替単価(回)', '¥3,000', '内職単価(H)', '¥1,200',
            '機械単価(H)', '¥4,500', '印刷単価', '¥1.5', '版替単価', '¥2,500', '練インキ単価', '¥800', '備考', '窓貼機',
            '機械マスタID', '作成者', '作成日時', '更新者', '更新日時']);
});

it('keeps 機械コード and 機械名 unique, requires 機械名 and checks the rates (APP_03)', function () {
    makeMachine();
    $this->actingAs($this->admin);

    Livewire::test(CreateMachine::class)
        ->fillForm(['code' => 'M01', 'name' => '1号機'])
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique', 'name' => 'unique']);

    Livewire::test(CreateMachine::class)
        ->fillForm(['code' => 'M02', 'name' => '', 'processing_rate' => '-1', 'printing_rate' => '0.12345'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'processing_rate', 'printing_rate']);

    // 機械コード may stay blank, like in the legacy app
    Livewire::test(CreateMachine::class)->fillForm(['code' => '', 'name' => '平台'])->call('create')->assertHasNoFormErrors();
    Livewire::test(CreateMachine::class)->fillForm(['code' => '', 'name' => 'カマス貼'])->call('create')->assertHasNoFormErrors();
    expect(Machine::query()->whereNull('code')->count())->toBe(2);
});

it('offers the legacy view 機械一覧 (newest first) and （すべて） with 機械マスタID and 備考', function () {
    publishedMachineApp();
    $old = makeMachine(['code' => 'M01', 'name' => '1号機', 'processing_rate' => '0.35']);
    $new = makeMachine(['code' => 'M02', 'name' => '2号機', 'notes' => '新しい']);
    $this->actingAs($this->admin);

    Livewire::test(ListMachines::class)
        ->assertSee('機械一覧')
        ->assertTableColumnVisible('ink_mixing_rate')
        ->assertTableColumnDoesNotExist('notes')
        ->assertTableColumnDoesNotExist('id')
        ->assertCanSeeTableRecords([$new, $old], inOrder: true)
        ->assertSee('¥0.35')
        ->set('listView', 'all')
        ->assertTableColumnVisible('id')
        ->assertTableColumnVisible('notes')
        ->call('applyAppFilter', ['conditions' => [['field' => 'processing_rate', 'operator' => 'gte', 'values' => ['0.3']]]])
        ->assertCanSeeTableRecords([$old])
        ->assertCanNotSeeTableRecords([$new]);
});

it('edits a row in the list with decimal rates (インライン編集) and keeps the 変更履歴', function () {
    publishedMachineApp();
    makeMachine(['code' => 'M09', 'name' => '9号機']);
    $machine = makeMachine(['code' => 'M01', 'name' => '1号機', 'processing_rate' => '0.35']);
    $this->actingAs($this->admin);

    Livewire::test(ListMachines::class)
        ->callTableAction('inlineEdit', $machine)
        ->assertSet('inlineData.processing_rate', '0.3500')
        ->set('inlineData.name', '9号機')
        ->set('inlineData.processing_rate', '0.123456')
        ->callTableAction('inlineSave', $machine)
        ->assertHasErrors(['inlineData.name' => 'unique', 'inlineData.processing_rate'])
        ->set('inlineData.name', '1号機（新）')
        ->set('inlineData.processing_rate', '0.42')
        ->set('inlineData.machine_rate', '')
        ->callTableAction('inlineSave', $machine)
        ->assertHasNoErrors()
        ->assertSet('inlineRecordKey', null);

    $machine->refresh();
    expect($machine->name)->toBe('1号機（新）')
        ->and($machine->processing_rate)->toBe('0.4200')
        ->and($machine->machine_rate)->toBeNull()
        ->and(RecordVersion::query()->where('record_type', $machine->getMorphClass())->where('record_id', $machine->id)->count())->toBe(1);
});

it('has the record features: 変更履歴, コメント to 作成者 / 更新者, 再利用 and no 一括削除', function () {
    publishedMachineApp();
    $creator = User::factory()->admin()->create(['name' => '作成 太郎']);
    $this->actingAs($creator);
    $machine = makeMachine();

    $this->actingAs($this->admin);
    Livewire::test(EditMachine::class, ['record' => $machine->getRouteKey()])->fillForm(['machine_rate' => '5000'])->call('save')->assertHasNoFormErrors();
    expect(RecordVersion::query()->where('record_id', $machine->id)->where('record_type', $machine->getMorphClass())->sole()->event)->toBe('created');

    Livewire::test(ViewMachine::class, ['record' => $machine->getRouteKey()])
        ->assertSee('sfk-sidebar is-collapsed', false)
        ->set('commentData.body', '刃を交換しました')
        ->call('postComment')
        ->assertHasNoErrors();
    expect(RecordComment::query()->sole()->record_type)->toBe($machine->getMorphClass())
        ->and($creator->notifications()->sole()->data['title'])->toBe('03. 機械マスタ: 1号機');

    Livewire::test(ViewMachine::class, ['record' => $machine->getRouteKey()])
        ->assertActionHasUrl('duplicate', MachineResource::getUrl('create', ['duplicate' => $machine->id]));
    Livewire::test(CreateMachine::class, ['duplicate' => (string) $machine->id])->assertSchemaStateSet(['name' => '1号機', 'machine_rate' => '5000.0000']);

    Livewire::test(ListMachines::class)->assertActionHidden('bulkDelete');

    $this->get(MachineSettings::getUrl())->assertOk()->assertSeeInOrder(['インライン編集を有効にする', '一括削除を有効にする']);
    Livewire::test(MachineSettings::class)->assertSet('bulkDeleteEnabled', false)->assertSet('commentsEnabled', true);

    $machine->delete();
    expect(RecordComment::query()->count())->toBe(0)->and(RecordVersion::query()->count())->toBe(0);
});

it('limits employees to what the app permissions allow', function () {
    $machine = makeMachine();
    $viewer = machineEmployeeWith([AppAbility::View]);
    $this->actingAs($viewer);

    $this->get(MachineResource::getUrl('index'))->assertOk()->assertSee('1号機');
    $this->get(MachineResource::getUrl('view', ['record' => $machine]))->assertOk();
    $this->get(MachineResource::getUrl('create'))->assertForbidden();
    $this->get(MachineResource::getUrl('edit', ['record' => $machine]))->assertForbidden();
    Livewire::test(ListMachines::class)->assertTableActionHidden('inlineEdit', $machine)->assertTableActionHidden('delete', $machine);

    $this->actingAs(grantDashboards(User::factory()->create(), 'erp'));
    $this->get(MachineResource::getUrl('index'))->assertForbidden();
});

it('formats unit prices like the legacy app (¥, thousands separators, no trailing zeros)', function () {
    expect(UnitPrice::format('0.3500'))->toBe('¥0.35')
        ->and(UnitPrice::format('1200.0000'))->toBe('¥1,200')
        ->and(UnitPrice::format('1234567.5'))->toBe('¥1,234,567.5')
        ->and(UnitPrice::format(null))->toBe('');
});
