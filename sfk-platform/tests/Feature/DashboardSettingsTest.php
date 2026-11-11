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
use Modules\Core\Application\Users\UserAccountService;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages\EditAppSettings;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages\ListAppSettings;
use Modules\Core\Filament\DashboardSettings\Resources\Spaces\Pages\ManageSpaces;
use Modules\Core\Filament\DashboardSettings\Resources\Spaces\SpaceResource;
use Modules\Core\Filament\DashboardSettings\Resources\Spaces\SpaceWizard;
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

it('creates a space with member employees and apps in the quick-entry wizard (user 2026-11-11)', function () {
    $this->actingAs($this->admin);
    $partners = placed_app('partner-master', AppStatus::Developing);
    $orders = placed_app('order-entry', AppStatus::Developing);

    Livewire::test(ManageSpaces::class)
        ->callAction('create')
        ->assertDispatched(SpaceWizard::OPEN_EVENT);

    Livewire::test(SpaceWizard::class)
        ->call('start')
        ->assertSet('open', true)
        ->assertSet('screen', 'name')
        ->call('next')
        ->assertHasErrors('name')
        ->assertSee('スペース名は必須です。')
        ->assertSet('screen', 'name')
        ->set('name', '営業スペース')
        ->call('next')
        ->assertSet('screen', 'members')
        ->assertSee(['山田 花子', '0名'])
        ->call('toggleUser', $this->employee->id)
        ->assertSee('1名')
        ->call('next')
        ->assertSet('screen', 'apps')
        ->call('toggleApp', $partners->id)
        ->call('toggleApp', $orders->id)
        ->call('next')
        ->assertSet('screen', SpaceWizard::REVIEW)
        ->assertSee(['営業スペース', '1名：山田 花子', '2件', 'この内容で作成'])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('open', false)
        ->assertDispatched(SpaceWizard::SAVED_EVENT)
        ->assertNotified('スペースを作成しました');

    $space = Space::query()->where('name', '営業スペース')->firstOrFail();
    expect($space->panel_id)->toBe('erp')
        ->and($space->users()->pluck('users.id')->all())->toBe([$this->employee->id])
        ->and($space->apps()->pluck('code')->sort()->values()->all())->toBe(['order-entry', 'partner-master']);

    // the name is unique in the dashboard
    Livewire::test(SpaceWizard::class)->call('start')->set('name', '営業スペース')->call('next')
        ->assertHasErrors('name')
        ->assertSet('screen', 'name');
});

it('opens a space in the wizard on its check: from ✎, and from ⚙ of the dashboard (?space=)', function () {
    $this->actingAs($this->admin);
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業']);
    $space->users()->attach($this->employee);
    $app = placed_app('partner-master', space: $space);

    Livewire::test(ManageSpaces::class)
        ->callTableAction('edit', $space)
        ->assertDispatched(SpaceWizard::OPEN_EVENT, space: $space->id);

    $this->get(SpaceResource::getUrl('index', ['space' => $space->id]))->assertOk()->assertSee(['スペースを編集', '1名：山田 花子', 'この内容で保存']);

    $other = placed_app('order-entry');
    Livewire::test(SpaceWizard::class, ['space' => $space->id])
        ->assertSet('open', true)
        ->assertSet('screen', SpaceWizard::REVIEW)
        ->assertSet('users', [$this->employee->id])
        ->assertSet('apps', [$app->id])
        // a row of the check goes to its step, 次へ back to the check
        ->call('goTo', 'apps')
        ->assertSet('screen', 'apps')
        ->assertSee('確認へ')
        ->call('toggleApp', $other->id)
        ->call('next')
        ->assertSet('screen', SpaceWizard::REVIEW)
        ->call('submit')
        ->assertNotified('スペースを保存しました');

    expect($space->apps()->pluck('code')->sort()->values()->all())->toBe(['order-entry', 'partner-master'])
        ->and($space->users()->pluck('users.id')->all())->toBe([$this->employee->id]);

    // a space of another dashboard is not opened
    $sns = Space::query()->create(['panel_id' => 'sns', 'name' => 'SNS']);
    Livewire::test(SpaceWizard::class, ['space' => $sns->id])->assertSet('open', false);
});

it('moves through the step bar to the steps reached before, checking the open one going forward', function () {
    $this->actingAs($this->admin);

    $wizard = Livewire::test(SpaceWizard::class)
        ->call('start')
        ->call('jump', 'members')
        ->assertSet('screen', 'name')
        ->set('name', 'S')
        ->call('next')
        ->call('next')
        ->assertSet('screen', 'apps')
        ->call('jump', 'name')
        ->assertSet('screen', 'name')
        ->set('name', '')
        ->call('jump', 'apps')
        ->assertSet('screen', 'name')
        ->assertHasErrors('name')
        ->set('name', 'S')
        ->call('jump', 'apps')
        ->assertSet('screen', 'apps')
        ->call('jump', SpaceWizard::REVIEW)
        ->assertSet('screen', 'apps');

    expect(array_column($wizard->instance()->stepBar(), 'enabled', 'key'))->toBe(['name' => true, 'members' => true, 'apps' => false, 'review' => false]);
});

it('lets only administrators use the wizard', function () {
    $this->actingAs($this->employee);

    Livewire::test(SpaceWizard::class)->call('start')->assertForbidden();
    Livewire::test(SpaceWizard::class)->call('save')->assertForbidden();
});

it('selects or clears every employee and app the search shows', function () {
    $this->actingAs($this->admin);
    $other = grantDashboards(User::factory()->create(['name' => '佐藤 次郎']), 'erp');
    $partners = placed_app('partner-master');
    $orders = placed_app('order-entry');

    $wizard = Livewire::test(SpaceWizard::class)
        ->call('start')
        ->set('name', 'S')
        ->call('next')
        ->set('search', '山田')
        ->assertSee('山田 花子')
        ->assertDontSee('佐藤 次郎')
        ->call('selectListed', 'users', true)
        ->assertSet('users', [$this->employee->id])
        ->set('search', '')
        ->call('selectListed', 'users', true)
        ->assertSet('users', [$this->employee->id, $other->id])
        ->call('selectListed', 'users', false)
        ->assertSet('users', [])
        ->call('next')
        ->call('selectListed', 'apps', true);

    expect($wizard->get('apps'))->toEqualCanonicalizing([$partners->id, $orders->id]);
});

it('places an app in several spaces and keeps the others when one of them is deleted', function () {
    $this->actingAs($this->admin);
    $sales = Space::query()->create(['panel_id' => 'erp', 'name' => '営業']);
    $app = placed_app('partner-master', space: $sales);
    $factory = Space::query()->create(['panel_id' => 'erp', 'name' => '製造']);
    $factoryWorker = grantDashboards(User::factory()->create(), 'erp');
    $app->permissions()->create(['subject_type' => 'everyone', 'can_view' => true]);

    // the app placed in 営業 is offered for 製造 too and joins it
    Livewire::test(SpaceWizard::class, ['space' => $factory->id])
        ->call('toggleUser', $factoryWorker->id)
        ->call('toggleApp', $app->id)
        ->call('save')
        ->assertHasNoErrors();
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

    Livewire::test(SpaceWizard::class)
        ->call('start')
        ->set('name', 'X')
        ->call('toggleUser', $this->admin->id)
        ->assertSet('users', [])
        ->set('users', [$this->admin->id])
        ->call('save')
        ->assertHasErrors(['users'])
        ->assertSet('screen', 'members');
    expect(Space::query()->where('name', 'X')->exists())->toBeFalse();

    // SpaceService keeps employees only
    $space = app(SpaceService::class)->save(null, 'erp', ['name' => 'Y', 'user_ids' => [$this->admin->id, $this->employee->id]], $this->admin);
    expect($space->users()->pluck('users.id')->all())->toBe([$this->employee->id]);
});

it('adds the employees of a 従業員カテゴリ to a space — also those who get it later — and drops them when it goes (user 2026-11-11)', function () {
    $this->actingAs($this->admin);
    $sales = EmployeeCategory::query()->where('name', '営業')->firstOrFail();
    $plant = EmployeeCategory::query()->where('name', '製造')->firstOrFail();
    $seller = grantDashboards(User::factory()->create(['name' => '営業 一郎']), 'erp');
    $seller->employeeCategories()->attach($sales);
    $this->admin->employeeCategories()->attach($sales); // not an employee: never a member
    $app = placed_app('partner-master');

    $wizard = Livewire::test(SpaceWizard::class)
        ->call('start')
        ->set('name', '営業スペース')
        ->call('next')
        ->call('toggleCategory', $sales->id)
        ->assertSet('categories', [$sales->id])
        ->assertSee('1名');
    expect(array_keys($wizard->instance()->coveredUsers()))->toBe([$seller->id]);
    // a member through the category stays ticked
    $wizard->call('toggleUser', $seller->id)->assertSet('users', [])
        ->call('toggleUser', $this->employee->id)
        ->call('next')
        ->call('toggleApp', $app->id)
        ->call('next')
        ->assertSee(['2名：営業、山田 花子'])
        ->call('submit')
        ->assertHasNoErrors();

    $space = Space::query()->where('name', '営業スペース')->firstOrFail();
    $members = fn (): array => $space->users()->orderBy('users.id')->get()->mapWithKeys(fn (User $user): array => [$user->id => (bool) $user->pivot->direct])->all();
    expect($space->employeeCategories()->pluck('employee_categories.id')->all())->toBe([$sales->id])
        ->and($members())->toBe([$this->employee->id => true, $seller->id => false]);
    Livewire::test(ManageSpaces::class)->assertTableColumnStateSet('employeeCategories.name', ['営業'], $space);

    // someone who gets 営業 later joins; with it taken off they leave, with their 個人 row of the アクセス権
    $accounts = app(UserAccountService::class);
    $newcomer = $accounts->create(['name' => '営業 新人', 'email' => 'newcomer@example.com', 'employee_category_ids' => [$sales->id]])->user;
    expect($space->hasMember($newcomer))->toBeTrue()
        ->and(AppAccess::abilitiesFor($newcomer->fresh(), $app->fresh()))->toBe([]);
    $space->permissions()->create(['subject_type' => 'user', 'user_id' => $newcomer->id, 'can_view' => true]);
    $accounts->update($newcomer, ['employee_category_ids' => [$plant->id]]);
    expect(array_keys($members()))->toBe([$this->employee->id, $seller->id])
        ->and($space->permissions()->where('user_id', $newcomer->id)->exists())->toBeFalse();

    // added by name and through 営業: stays when the category goes; leaves the space when no longer an employee
    $accounts->update($this->employee, ['employee_category_ids' => [$sales->id]]);
    expect($members()[$this->employee->id])->toBeTrue();
    // ユーザー管理 sets the spaces by name; those of the categories stay
    $accounts->update($seller, ['space_ids' => []]);
    expect($members())->toHaveKey($seller->id);
    $accounts->update($seller, ['user_type' => 'admin']);
    expect($members())->not->toHaveKey($seller->id);

    // the category taken off the space: only those added by name stay
    Livewire::test(SpaceWizard::class, ['space' => $space->id])
        ->assertSet('categories', [$sales->id])
        ->assertSet('users', [$this->employee->id])
        ->call('goTo', 'members')
        ->call('toggleCategory', $sales->id)
        ->call('next')
        ->call('submit');
    expect($members())->toBe([$this->employee->id => true])
        ->and($space->employeeCategories()->count())->toBe(0);
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

    // a space created in the wizard has no rows; アクセス権 sets them
    $space = app(SpaceService::class)->save(null, 'erp', [
        'name' => '営業スペース',
        'user_ids' => [$this->employee->id, $salesPerson->id, $boss->id],
        'app_ids' => [$partners->id, $orders->id],
    ], $this->admin);
    expect($space->permissions()->count())->toBe(0);

    Livewire::test(ManageSpaces::class)
        ->callTableAction('permissions', $space, data: [
            'permissions' => [
                ['subject_type' => 'everyone', 'can_view' => true],
                ['subject_type' => 'category', 'employee_category_id' => $sales->id, 'can_view' => true, 'can_create' => true],
                ['subject_type' => 'user', 'user_id' => $boss->id, 'can_view' => true, 'can_update' => true, 'can_delete' => true],
            ],
        ])
        ->assertHasNoTableActionErrors();

    expect($space->permissions()->count())->toBe(3);

    foreach ([$partners, $orders] as $app) {
        expect(AppAccess::abilitiesFor($this->employee->fresh(), $app->fresh()))->toBe([AppAbility::View])
            ->and(AppAccess::abilitiesFor($salesPerson->fresh(), $app->fresh()))->toBe([AppAbility::View, AppAbility::Create])
            ->and(AppAccess::abilitiesFor($boss->fresh(), $app->fresh()))->toBe([AppAbility::View, AppAbility::Update, AppAbility::Delete]);
    }

    Livewire::test(ManageSpaces::class)
        ->assertTableColumnStateSet('rights', ['個人：佐藤 課長：閲覧・編集・削除', '従業員カテゴリ：営業：閲覧・追加', '全員：閲覧'], $space)
        // the dialog shows the rows it has
        ->mountTableAction('permissions', $space)
        ->assertTableActionDataSet(function (array $data) use ($boss, $sales): array {
            expect(array_map(fn (array $row): array => [$row['subject_type'], $row['employee_category_id'], $row['user_id'], $row['can_update']], array_values($data['permissions'])))
                ->toEqual([['user', null, $boss->id, true], ['category', $sales->id, null, false], ['everyone', null, null, false]]);

            return [];
        });
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
        ->callTableAction('permissions', $space, data: [
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

    $space = Space::query()->create(['panel_id' => 'erp', 'name' => 'X']);
    $space->users()->attach($this->employee);

    // 個人 offers the members only
    Livewire::test(ManageSpaces::class)
        ->callTableAction('permissions', $space, data: [
            'permissions' => [['subject_type' => 'user', 'user_id' => $outsider->id, 'can_view' => true]],
        ])
        ->assertHasTableActionErrors();
    Livewire::test(ManageSpaces::class)
        ->callTableAction('permissions', $space, data: [
            'permissions' => [['subject_type' => 'everyone', 'can_view' => true], ['subject_type' => 'everyone', 'can_view' => true]],
        ])
        ->assertHasTableActionErrors(['permissions']);
    Livewire::test(ManageSpaces::class)
        ->callTableAction('permissions', $space, data: [
            'permissions' => [
                ['subject_type' => 'category', 'employee_category_id' => $sales->id, 'can_view' => true],
                ['subject_type' => 'category', 'employee_category_id' => $sales->id, 'can_view' => true],
            ],
        ])
        ->assertHasTableActionErrors();

    expect($space->permissions()->count())->toBe(0);

    expect(fn () => app(SpaceService::class)->savePermissions($space, [['subject_type' => 'user', 'user_id' => $outsider->id, 'can_view' => true]], $this->admin))
        ->toThrow(AppActionNotAllowed::class, '個人の権限はこのスペースのメンバーにのみ設定できます。');
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
