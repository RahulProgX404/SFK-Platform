<?php

/*
 * Dashboards come from the user's own selection and their 従業員カテゴリ; 共通管理 (settings) is for ユーザー種別 super_admin /
 * admin only and is never a category dashboard. Every panel's user menu offers the other dashboards the
 * user may open.
 */

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Support\Arr;
use Livewire\Livewire;
use Modules\Core\Access\DashboardAccess;
use Modules\Core\Application\Users\UserAccountService;
use Modules\Core\Models\EmployeeCategory;
use Modules\Settings\Filament\Resources\EmployeeCategories\Pages\ManageEmployeeCategories;
use Modules\Settings\Filament\Resources\Users\Pages\CreateUser;
use Modules\Settings\Filament\Resources\Users\Pages\EditUser;

it('lets employees open only the dashboards of their category', function () {
    $employee = grantDashboards(User::factory()->create(), 'erp');

    $this->actingAs($employee)->get('/erp')->assertOk();
    $this->actingAs($employee)->get('/settings')->assertForbidden();
    $this->actingAs($employee)->get('/portal')->assertRedirect('/erp');
});

it('gives employees whose category allows nothing no dashboards', function () {
    $employee = User::factory()->inCategories((int) EmployeeCategory::query()->value('id'))->create();

    $this->actingAs($employee)->get('/erp')->assertForbidden();
    $this->actingAs($employee)->get('/portal')->assertOk()->assertDontSee('白石封筒');
});

it('combines the dashboards of all of a user\'s categories', function () {
    $employee = User::factory()->create();
    $sales = EmployeeCategory::query()->where('name', '営業')->firstOrFail();
    $sales->update(['dashboards' => ['erp']]);
    $factory = EmployeeCategory::query()->where('name', '製造')->firstOrFail();
    $employee->employeeCategories()->attach([$sales->id, $factory->id]);

    $this->actingAs($employee)->get('/erp')->assertOk();
});

it('keeps granting dashboards when a category is deactivated', function () {
    $employee = grantDashboards(User::factory()->create(), 'erp');
    EmployeeCategory::query()->whereIn('id', $employee->employeeCategories()->pluck('employee_categories.id'))->update(['is_active' => false]);

    $this->actingAs($employee)->get('/erp')->assertOk();
});

it('even a category cannot open 共通管理 to employees', function () {
    $employee = grantDashboards(User::factory()->create(), 'erp', 'settings');

    expect($employee->canAccessPanel(Filament::getPanel('settings')))->toBeFalse()
        ->and(DashboardAccess::grantableOptions())->toHaveKey('erp')->not->toHaveKey('settings');
});

it('opens 共通管理 to admins by user type and other dashboards through the category', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/settings')->assertOk();
    $this->actingAs($admin)->get('/erp')->assertForbidden();

    grantDashboards($admin, 'erp');
    $this->actingAs($admin)->get('/erp')->assertOk();
});

it('sets the dashboards on the 従業員カテゴリ screen, without 共通管理', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    Filament::setCurrentPanel('settings');
    $sales = EmployeeCategory::query()->where('name', '営業')->firstOrFail();

    Livewire::test(ManageEmployeeCategories::class)
        ->callTableAction('edit', $sales, data: ['dashboards' => ['settings']])
        ->assertHasTableActionErrors(['dashboards.0']);

    Livewire::test(ManageEmployeeCategories::class)
        ->callTableAction('edit', $sales, data: ['dashboards' => ['erp']])
        ->assertHasNoTableActionErrors()
        ->callAction('create', data: ['name' => '派遣', 'dashboards' => ['erp'], 'is_active' => true])
        ->assertHasNoActionErrors();

    expect($sales->fresh()->dashboards)->toBe(['erp'])
        ->and(EmployeeCategory::query()->where('name', '派遣')->value('dashboards'))->toBe(['erp']);
});

it('switches dashboards from the menu group ダッシュボード切替, the dashboard name leading to its ポータル', function () {
    $admin = grantDashboards(User::factory()->admin()->create(), 'erp');

    $this->actingAs($admin)->get('/erp')
        ->assertOk()
        ->assertSeeInOrder(['sfk-dashboard-switcher', 'href="'.url('/erp').'"', '白石封筒', 'data-group-label="ダッシュボード切替"', 'fi-active',
            'href="'.url('/erp').'"', 'aria-current="page"', '白石封筒', 'href="'.url('/settings').'"', '共通管理'], escape: false)
        ->assertDontSee('fi-sidebar-item-label">ポータル', escape: false)
        ->assertDontSee('に切り替え');

    $this->actingAs($admin)->get('/settings')
        ->assertOk()
        ->assertSeeInOrder(['data-group-label="ダッシュボード切替"', 'href="'.url('/erp').'"', '白石封筒', 'fi-active', 'href="'.url('/settings').'"',
            'aria-current="page"', '共通管理'], escape: false);
});

it('lists a single dashboard in ダッシュボード切替 too', function () {
    $employee = grantDashboards(User::factory()->create(), 'erp');

    $this->actingAs($employee)->get('/erp')
        ->assertOk()
        ->assertSeeInOrder(['data-group-label="ダッシュボード切替"', 'href="'.url('/erp').'"', '白石封筒'], escape: false)
        ->assertDontSee('href="'.url('/settings').'"', escape: false);
});

it('grants dashboards chosen on the user form on top of the category', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    Filament::setCurrentPanel('settings');

    Livewire::test(CreateUser::class)
        ->assertFormFieldExists('dashboards', checkFieldUsing: fn (CheckboxList $field): bool => array_keys($field->getOptions()) === ['erp'])
        ->fillForm([
            'name' => '個別', 'email' => 'own@example.test', 'employment_status' => 'active',
            ...employeeMasterFields(), 'dashboards' => ['erp'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'own@example.test')->firstOrFail();
    expect($user->dashboards)->toBe(['erp'])
        ->and($user->employeeCategories()->first()?->dashboards)->toBeNull();

    $user->forceFill(['must_change_password' => false])->save(); // new users first change their temporary password
    $this->actingAs($user->fresh())->get('/erp')->assertOk();
});

it('does not offer or store 共通管理 as a user dashboard', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    Filament::setCurrentPanel('settings');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'X', 'email' => 'x@example.test', 'employment_status' => 'active',
            ...employeeMasterFields(), 'dashboards' => ['settings'],
        ])
        ->call('create')
        ->assertHasFormErrors(['dashboards.0']);

    $user = app(UserAccountService::class)->create([
        'name' => 'Y', 'email' => 'y@example.test', 'dashboards' => ['settings', 'erp', 'erp'],
    ])->user;

    expect($user->dashboards)->toBe(['erp'])
        ->and($user->canAccessPanel(Filament::getPanel('settings')))->toBeFalse();
});

it('revokes a user dashboard when it is unticked', function () {
    $employee = User::factory()
        ->inCategories((int) EmployeeCategory::query()->value('id'))
        ->create(['dashboards' => ['erp'], ...Arr::except(employeeMasterFields(), 'employee_category_ids')]);
    $this->actingAs($employee)->get('/erp')->assertOk();

    $this->actingAs(User::factory()->superAdmin()->create());
    Filament::setCurrentPanel('settings');
    Livewire::test(EditUser::class, ['record' => $employee->getRouteKey()])
        ->assertSchemaStateSet(['dashboards' => ['erp']])
        ->fillForm(['dashboards' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->actingAs($employee->fresh())->get('/erp')->assertForbidden();
});
