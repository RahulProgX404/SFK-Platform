<?php

/*
 * ダッシュボード設定 on business dashboards (permission.md "Dashboard" / "Space" / "App"): administrators
 * maintain spaces (members, apps) and app permissions; employees never see it; 共通管理 has none.
 */

use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Access\AppAccess;
use Modules\Core\Application\Apps\AppActionNotAllowed;
use Modules\Core\Application\Apps\SpaceService;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages\EditAppSettings;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages\ListAppSettings;
use Modules\Core\Filament\DashboardSettings\Resources\Spaces\Pages\ManageSpaces;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\Space;

function placed_app(string $code, AppStatus $status = AppStatus::Published, ?Space $space = null): BusinessApp
{
    $app = new BusinessApp(['code' => $code, 'name' => $code]);
    $app->forceFill(['status' => $status])->save();
    if ($space !== null) {
        $app->spaces()->attach($space);
    }

    return $app;
}

beforeEach(function () {
    $this->admin = grantDashboards(User::factory()->admin()->create(), 'erp');
    $this->employee = grantDashboards(User::factory()->create(['name' => '山田 花子']), 'erp');
    Filament::setCurrentPanel('erp');
});

it('shows ダッシュボード設定 to administrators only, and not on 共通管理', function () {
    $this->actingAs($this->admin)->get('/erp/dashboard-settings/spaces')->assertOk()->assertSee('スペース');
    $this->actingAs($this->admin)->get('/erp/dashboard-settings/apps')->assertOk();
    $this->actingAs($this->admin)->get('/erp')->assertSee('ダッシュボード設定');

    $this->actingAs($this->employee)->get('/erp/dashboard-settings/spaces')->assertForbidden();
    $this->actingAs($this->employee)->get('/erp/dashboard-settings/apps')->assertForbidden();

    $this->actingAs($this->admin)->get('/settings/dashboard-settings/spaces')->assertNotFound();
});

// Separate test: Filament builds the sidebar once per panel boot, i.e. once per request in production.
it('does not list ダッシュボード設定 in the sidebar of employees', function () {
    $this->actingAs($this->employee)->get('/erp')->assertOk()->assertDontSee('ダッシュボード設定');
});

it('creates a space with member employees and apps', function () {
    $this->actingAs($this->admin);
    $partners = placed_app('partner-master', AppStatus::Developing);
    $orders = placed_app('order-entry', AppStatus::Developing);

    Livewire::test(ManageSpaces::class)
        ->callAction('create', data: [
            'name' => '営業スペース',
            'user_ids' => [$this->employee->id],
            'app_ids' => [$partners->id, $orders->id],
        ])
        ->assertHasNoActionErrors();

    $space = Space::query()->where('name', '営業スペース')->firstOrFail();
    expect($space->panel_id)->toBe('erp')
        ->and($space->users()->pluck('users.id')->all())->toBe([$this->employee->id])
        ->and($space->apps()->pluck('code')->sort()->values()->all())->toBe(['order-entry', 'partner-master']);
});

it('places an app in several spaces and keeps the others when one of them is deleted', function () {
    $this->actingAs($this->admin);
    $sales = Space::query()->create(['panel_id' => 'erp', 'name' => '営業']);
    $app = placed_app('partner-master', space: $sales);
    $factory = Space::query()->create(['panel_id' => 'erp', 'name' => '製造']);
    $factoryWorker = grantDashboards(User::factory()->create(), 'erp');
    $app->permissions()->create(['subject_type' => 'everyone', 'can_view' => true]);

    // the app placed in 営業 is offered for 製造 too and joins it
    Livewire::test(ManageSpaces::class)
        ->callTableAction('edit', $factory, data: ['name' => '製造', 'user_ids' => [$factoryWorker->id], 'app_ids' => [$app->id]])
        ->assertHasNoTableActionErrors();
    expect(SpaceService::placeableApps($factory))->toHaveKey($app->id)
        ->and($app->fresh()->spaces->pluck('name')->all())->toBe(['営業', '製造'])
        ->and(AppAccess::abilitiesFor($factoryWorker, $app->fresh()))->toBe([AppAbility::View]);

    // deleting 営業 keeps the app in 製造
    Livewire::test(ManageSpaces::class)->callTableAction('delete', $sales);
    expect($app->fresh()->spaces->pluck('name')->all())->toBe(['製造'])
        ->and(AppAccess::abilitiesFor($factoryWorker, $app->fresh()))->toBe([AppAbility::View]);
});

it('accepts only employees as space members', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManageSpaces::class)
        ->callAction('create', data: ['name' => 'X', 'user_ids' => [$this->admin->id]])
        ->assertHasActionErrors(['user_ids']);
});

it('lists only the apps placed in this dashboard and edits their permissions', function () {
    $this->actingAs($this->admin);
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業']);
    $space->users()->attach($this->employee);
    $app = placed_app('partner-master', space: $space);
    $other = placed_app('elsewhere', space: Space::query()->create(['panel_id' => 'sns', 'name' => 'SNS']));
    $unplaced = placed_app('unplaced');
    $sales = EmployeeCategory::query()->where('name', '営業')->firstOrFail();

    Livewire::test(ListAppSettings::class)
        ->assertCanSeeTableRecords([$app])
        ->assertCanNotSeeTableRecords([$other, $unplaced]);

    Livewire::test(EditAppSettings::class, ['record' => $app->getRouteKey()])
        ->fillForm(['permissions' => [
            ['subject_type' => 'everyone', 'can_view' => true],
            ['subject_type' => 'category', 'employee_category_id' => $sales->id, 'can_view' => true, 'can_create' => true],
            ['subject_type' => 'user', 'user_id' => $this->employee->id, 'can_view' => false, 'can_delete' => true],
        ]])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('アクセス権を保存しました');

    $rows = $app->permissions()->get()->keyBy(fn ($row) => $row->subject_type->value);
    expect($rows)->toHaveCount(3)
        ->and($rows['category']->abilities())->toBe([AppAbility::View, AppAbility::Create])
        ->and($rows['user']->abilities())->toBe([]);

    // The employee's own (empty) row wins over the category and 全員 rows.
    expect(AppAccess::abilitiesFor($this->employee->fresh(), $app->fresh()))->toBe([]);
});

it('rejects a second 全員 row and duplicate subjects', function () {
    $this->actingAs($this->admin);
    $app = placed_app('partner-master', space: Space::query()->create(['panel_id' => 'erp', 'name' => '営業']));

    Livewire::test(EditAppSettings::class, ['record' => $app->getRouteKey()])
        ->fillForm(['permissions' => [
            ['subject_type' => 'everyone', 'can_view' => true],
            ['subject_type' => 'everyone', 'can_view' => true],
        ]])
        ->call('save')
        ->assertHasFormErrors(['permissions']);

    $component = Livewire::test(EditAppSettings::class, ['record' => $app->getRouteKey()])
        ->fillForm(['permissions' => [
            ['subject_type' => 'user', 'user_id' => $this->employee->id, 'can_view' => true],
            ['subject_type' => 'user', 'user_id' => $this->employee->id, 'can_view' => true],
        ]])
        ->call('save');

    expect(collect($component->errors()->keys())->contains(fn (string $key): bool => str_ends_with($key, 'user_id')))->toBeTrue()
        ->and($app->permissions()->count())->toBe(0);
});

it('labels permission subjects in Japanese', function () {
    expect(PermissionSubject::options())->toBe(['user' => '個人', 'category' => '従業員カテゴリ', 'everyone' => '全員']);
});

it('creates every space with a permission setting for 全員 / 従業員カテゴリ / 個人 that applies in all its apps', function () {
    $this->actingAs($this->admin);
    $partners = placed_app('partner-master');
    $orders = placed_app('order-entry');
    $sales = EmployeeCategory::query()->where('name', '営業')->firstOrFail();
    $salesPerson = grantDashboards(User::factory()->create(['name' => '営業 太郎']), 'erp');
    $salesPerson->employeeCategories()->attach($sales);
    $boss = grantDashboards(User::factory()->create(['name' => '佐藤 課長']), 'erp');

    Livewire::test(ManageSpaces::class)
        ->callAction('create', data: [
            'name' => '営業スペース',
            'user_ids' => [$this->employee->id, $salesPerson->id, $boss->id],
            'app_ids' => [$partners->id, $orders->id],
            'permissions' => [
                ['subject_type' => 'everyone', 'can_view' => true],
                ['subject_type' => 'category', 'employee_category_id' => $sales->id, 'can_view' => true, 'can_create' => true],
                ['subject_type' => 'user', 'user_id' => $boss->id, 'can_view' => true, 'can_update' => true, 'can_delete' => true],
            ],
        ])
        ->assertHasNoActionErrors();

    $space = Space::query()->where('name', '営業スペース')->firstOrFail();
    expect($space->permissions()->count())->toBe(3);

    foreach ([$partners, $orders] as $app) {
        expect(AppAccess::abilitiesFor($this->employee->fresh(), $app->fresh()))->toBe([AppAbility::View])
            ->and(AppAccess::abilitiesFor($salesPerson->fresh(), $app->fresh()))->toBe([AppAbility::View, AppAbility::Create])
            ->and(AppAccess::abilitiesFor($boss->fresh(), $app->fresh()))->toBe([AppAbility::View, AppAbility::Update, AppAbility::Delete]);
    }

    Livewire::test(ManageSpaces::class)
        ->assertTableColumnStateSet('rights', ['個人：佐藤 課長：閲覧・編集・削除', '従業員カテゴリ：営業：閲覧・追加', '全員：閲覧'], $space);

    // a space created without rows gives nothing
    Livewire::test(ManageSpaces::class)
        ->callAction('create', data: ['name' => '空のスペース'])
        ->assertHasNoActionErrors();
    expect(Space::query()->where('name', '空のスペース')->firstOrFail()->permissions()->count())->toBe(0);
});

it('edits the rows of a space and drops the 個人 row of a member who leaves', function () {
    $this->actingAs($this->admin);
    $app = placed_app('partner-master');
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業']);
    $space->users()->attach($this->employee);
    $app->spaces()->attach($space);
    $space->permissions()->create(['subject_type' => 'everyone', 'can_view' => true]);
    $space->permissions()->create(['subject_type' => 'user', 'user_id' => $this->employee->id, 'can_view' => true, 'can_update' => true]);

    Livewire::test(ManageSpaces::class)
        ->callTableAction('edit', $space, data: [
            'name' => '営業',
            'user_ids' => [$this->employee->id],
            'app_ids' => [$app->id],
            'permissions' => [
                ['subject_type' => 'everyone', 'can_view' => true, 'can_export' => true],
            ],
        ])
        ->assertHasNoTableActionErrors();

    expect($space->permissions()->count())->toBe(1)
        ->and(AppAccess::abilitiesFor($this->employee->fresh(), $app->fresh()))->toBe([AppAbility::View, AppAbility::Export]);

    // the 個人 row goes with the membership (SpaceService, without permissions given)
    $space->permissions()->create(['subject_type' => 'user', 'user_id' => $this->employee->id, 'can_view' => true]);
    app(SpaceService::class)->save($space, 'erp', ['name' => '営業', 'user_ids' => [], 'app_ids' => [$app->id]], $this->admin);
    expect($space->permissions()->where('subject_type', 'user')->count())->toBe(0);
});

it('rejects a 個人 row for someone who is not a member, a second 全員 row and duplicate categories', function () {
    $this->actingAs($this->admin);
    $outsider = grantDashboards(User::factory()->create(), 'erp');
    $sales = EmployeeCategory::query()->where('name', '営業')->firstOrFail();

    Livewire::test(ManageSpaces::class)
        ->callAction('create', data: [
            'name' => 'X',
            'user_ids' => [$this->employee->id],
            'permissions' => [['subject_type' => 'user', 'user_id' => $outsider->id, 'can_view' => true]],
        ]);
    Livewire::test(ManageSpaces::class)
        ->callAction('create', data: [
            'name' => 'Y',
            'permissions' => [['subject_type' => 'everyone', 'can_view' => true], ['subject_type' => 'everyone', 'can_view' => true]],
        ]);
    Livewire::test(ManageSpaces::class)
        ->callAction('create', data: [
            'name' => 'Z',
            'permissions' => [
                ['subject_type' => 'category', 'employee_category_id' => $sales->id, 'can_view' => true],
                ['subject_type' => 'category', 'employee_category_id' => $sales->id, 'can_view' => true],
            ],
        ]);

    expect(Space::query()->whereIn('name', ['X', 'Y', 'Z'])->count())->toBe(0);

    expect(fn () => app(SpaceService::class)->save(null, 'erp', [
        'name' => 'W',
        'user_ids' => [$this->employee->id],
        'permissions' => [['subject_type' => 'user', 'user_id' => $outsider->id, 'can_view' => true]],
    ], $this->admin))->toThrow(AppActionNotAllowed::class, '個人の権限はこのスペースのメンバーにのみ設定できます。');
});

it('adds the rights of two spaces holding the same app (閲覧 + 閲覧・編集 = 閲覧・編集) and shows them on the app', function () {
    $this->actingAs($this->admin);
    $a = Space::query()->create(['panel_id' => 'erp', 'name' => 'A']);
    $b = Space::query()->create(['panel_id' => 'erp', 'name' => 'B']);
    $a->permissions()->create(['subject_type' => 'everyone', 'can_view' => true]);
    $b->permissions()->create(['subject_type' => 'everyone', 'can_view' => true, 'can_update' => true]);
    $a->users()->attach($this->employee);
    $b->users()->attach($this->employee);
    $app = placed_app('partner-master', space: $a);
    $app->spaces()->attach($b);
    Space::forgetCached();

    expect(AppAccess::abilitiesFor($this->employee->fresh(), $app->fresh()))->toBe([AppAbility::View, AppAbility::Update]);

    $this->get(EditAppSettings::getUrl(['record' => $app]))
        ->assertOk()
        ->assertSee('スペースの権限')
        ->assertSee('A（全員）：閲覧')
        ->assertSee('B（全員）：閲覧・編集');
});
