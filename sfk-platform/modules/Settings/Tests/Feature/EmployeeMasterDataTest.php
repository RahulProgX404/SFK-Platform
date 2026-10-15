<?php

/*
 * 従業員マスタ情報 on users (SYSTEM_BRAIN APP_02): 担当者コード, 作業員コード, 部課コード, 従業員カテゴリ,
 * 作業単価, 残業単価 — required when an administrator registers a user. 従業員カテゴリ is the person's
 * position in the company (it replaced ロール). Rates are visible only to administrators and the user
 * themselves (11_PERMISSIONS §3, cost data is confidential).
 */

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Modules\Core\Access\EmployeeDataAccess;
use Modules\Core\Application\Users\ProtectedAccountException;
use Modules\Core\Application\Users\UserAccountService;
use Modules\Core\Models\EmployeeCategory;
use Modules\Settings\Filament\Resources\EmployeeCategories\Pages\ManageEmployeeCategories;
use Modules\Settings\Filament\Resources\Users\Pages\CreateUser;
use Modules\Settings\Filament\Resources\Users\Pages\EditUser;
use Modules\Settings\Filament\Resources\Users\Pages\ListUsers;
use Modules\Settings\Filament\Resources\Users\Pages\ViewUser;

beforeEach(function () {
    Filament::setCurrentPanel('settings');

    $this->admin = User::factory()->admin()->create(['name' => '管理者']);
    $this->staff = User::factory()->create(['name' => '一般 社員']);

    $this->manufacturing = EmployeeCategory::query()->where('name', '製造')->firstOrFail();

    $this->worker = User::factory()->inCategories($this->manufacturing->id)->create([
        'name' => '作業 太郎',
        'sales_staff_code' => 'S001',
        'worker_code' => 'W001',
        'department_code' => 'D10',
        'work_rate' => 1850,
        'overtime_rate' => 2310,
    ]);
});

it('seeds the legacy employee categories with 役員 inactive', function () {
    expect(EmployeeCategory::query()->ordered()->pluck('name')->all())
        ->toBe(['営業', '総務', '製造', '工務', '制作', '製造パート', '営業パート', '役員'])
        ->and(array_values(EmployeeCategory::options()))->not->toContain('役員');
});

it('lets an administrator create a user with all employee master fields and several categories', function () {
    $this->actingAs($this->admin);
    $sales = EmployeeCategory::query()->where('name', '営業')->firstOrFail();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => '新人',
            'email' => 'new@example.test',
            'employment_status' => 'active',
            'sales_staff_code' => 'S100',
            'worker_code' => 'W100',
            'department_code' => 'D20',
            'employee_category_ids' => [$this->manufacturing->id, $sales->id],
            'work_rate' => 1500,
            'overtime_rate' => 1875,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'new@example.test')->firstOrFail();
    expect($user->only(['sales_staff_code', 'worker_code', 'department_code', 'work_rate', 'overtime_rate']))
        ->toBe([
            'sales_staff_code' => 'S100',
            'worker_code' => 'W100',
            'department_code' => 'D20',
            'work_rate' => 1500,
            'overtime_rate' => 1875,
        ])
        ->and($user->employeeCategories()->pluck('name')->all())->toEqualCanonicalizing(['製造', '営業']);
});

it('requires every employee master field when creating a user', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => '新人', 'email' => 'new@example.test', 'employment_status' => 'active'])
        ->call('create')
        ->assertHasFormErrors([
            'sales_staff_code' => 'required',
            'worker_code' => 'required',
            'department_code' => 'required',
            'employee_category_ids' => 'required',
            'work_rate' => 'required',
            'overtime_rate' => 'required',
        ]);

    expect(User::query()->where('email', 'new@example.test')->exists())->toBeFalse();
});

it('keeps 担当者コード and 作業員コード unique and rates non-negative', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'X',
            'email' => 'x@example.test',
            'employment_status' => 'active',
            ...employeeMasterFields(['sales_staff_code' => 'S001', 'worker_code' => 'W001', 'work_rate' => -1]),
        ])
        ->call('create')
        ->assertHasFormErrors(['sales_staff_code' => 'unique', 'worker_code' => 'unique', 'work_rate']);
});

it('lets an administrator add a new 従業員カテゴリ option from the dropdown', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditUser::class, ['record' => $this->worker->getRouteKey()])
        ->callAction(TestAction::make('createOption')->schemaComponent('employee_category_ids'), data: ['name' => '製造'])
        ->assertHasActionErrors(['name' => 'unique']);

    Livewire::test(EditUser::class, ['record' => $this->worker->getRouteKey()])
        ->callAction(TestAction::make('createOption')->schemaComponent('employee_category_ids'), data: ['name' => '派遣'])
        ->assertHasNoFormErrors()
        ->call('save')
        ->assertHasNoFormErrors();

    $dispatch = EmployeeCategory::query()->where('name', '派遣')->firstOrFail();
    expect($this->worker->employeeCategories()->pluck('name')->all())->toEqualCanonicalizing(['製造', '派遣'])
        ->and($dispatch->sort_order)->toBe((int) EmployeeCategory::query()->max('sort_order'));
});

it('generates a password on the create page and shows it for copying', function () {
    $this->actingAs($this->admin);

    $page = Livewire::test(CreateUser::class)
        ->assertFormFieldHidden('generated_password')
        ->callAction(TestAction::make('generatePassword')->schemaComponent('password'))
        ->assertFormFieldVisible('generated_password');

    $password = $page->get('data.password');
    expect($password)->toBeString()->toHaveLength(12)
        ->and($page->get('data.generated_password'))->toBe($password);

    $page->fillForm(['name' => '新人', 'email' => 'gen@example.test', 'employment_status' => 'active', ...employeeMasterFields()])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Hash::check($password, User::query()->where('email', 'gen@example.test')->value('password')))->toBeTrue();
});

it('keeps employees out of user management', function () {
    $this->actingAs($this->staff);

    $this->get("/settings/users/{$this->worker->id}")->assertForbidden();

    expect(fn () => app(UserAccountService::class)->update($this->worker, ['work_rate' => 1], actor: $this->staff))
        ->toThrow(ProtectedAccountException::class)
        ->and($this->worker->fresh()->work_rate)->toBe(1850);
});

it('shows rates only to administrators and to the user themselves', function () {
    expect(EmployeeDataAccess::canViewRates($this->admin, $this->worker))->toBeTrue()
        ->and(EmployeeDataAccess::canViewRates($this->worker, $this->worker))->toBeTrue()
        ->and(EmployeeDataAccess::canViewRates($this->staff, $this->worker))->toBeFalse();
});

it('shows and edits every employee\'s rates as an administrator', function () {
    $this->actingAs($this->admin);

    Livewire::test(ViewUser::class, ['record' => $this->worker->getRouteKey()])
        ->assertSee('1,850 円/時');

    Livewire::test(ListUsers::class)
        ->assertTableColumnVisible('work_rate');

    Livewire::test(EditUser::class, ['record' => $this->worker->getRouteKey()])
        ->assertSchemaStateSet(['work_rate' => 1850, 'overtime_rate' => 2310])
        ->fillForm(['work_rate' => 2000])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->worker->fresh()->work_rate)->toBe(2000);
});

it('never serializes the rates', function () {
    expect($this->worker->fresh()->toArray())->not->toHaveKeys(['work_rate', 'overtime_rate']);
});

it('lets administrators manage the category options', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManageEmployeeCategories::class)
        ->callAction('create', data: ['name' => '嘱託', 'is_active' => true])
        ->assertHasNoActionErrors()
        ->assertTableActionHidden('delete', $this->manufacturing);

    expect(EmployeeCategory::query()->where('name', '嘱託')->exists())->toBeTrue();
});
