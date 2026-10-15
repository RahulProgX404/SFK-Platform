<?php

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Arr;
use Livewire\Livewire;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\Position;
use Modules\Settings\Filament\Resources\Positions\Pages\ManagePositions;
use Modules\Settings\Filament\Resources\Users\Pages\CreateUser;
use Modules\Settings\Filament\Resources\Users\Pages\EditUser;
use Modules\Settings\Filament\Resources\Users\Pages\ListUsers;
use Modules\Settings\Filament\Resources\Users\Pages\ViewUser;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create(['name' => '管理者']);
    $this->admin->forceFill(['managed_by_env' => true])->save();

    $this->actingAs($this->admin);
    Filament::setCurrentPanel('settings');
});

it('shows the headline figures and the user list', function () {
    $member = User::factory()->create(['name' => '佐藤 花子']);

    $this->get('/settings/users')
        ->assertOk()
        ->assertSee('ユーザー管理')
        ->assertSee('登録ユーザー')
        ->assertDontSee('過去30日にログイン')
        ->assertDontSee('在籍状況別のユーザー数')
        ->assertDontSee('組織別の所属人数')
        ->assertDontSee('PW変更待ち')
        ->assertDontSee('ユーザーの状況を確認し');

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$member, $this->admin]);
});

it('creates a user with only the required fields and a generated password', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => '鈴木 一郎',
            'email' => 'suzuki@example.test',
            'employment_status' => EmploymentStatus::Active->value,
            ...employeeMasterFields(),
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('ユーザーを登録しました');

    $user = User::query()->where('email', 'suzuki@example.test')->firstOrFail();
    expect($user->must_change_password)->toBeTrue()
        ->and($user->employeeCategories()->count())->toBe(1);
});

it('requires name, email, user type and working status', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(['name' => '', 'email' => '', 'user_type' => null, 'employment_status' => null])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'email' => 'required',
            'user_type' => 'required',
            'employment_status' => 'required',
        ]);
});

it('no longer asks for roles or dashboards on the user form', function () {
    Livewire::test(CreateUser::class)
        ->assertFormFieldDoesNotExist('roles')
        ->assertFormFieldDoesNotExist('panels');
});

it('does not offer the super admin user type in the form', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'X',
            'email' => 'x@example.test',
            'user_type' => 'super_admin',
            'employment_status' => 'active',
            ...employeeMasterFields(),
        ])
        ->call('create')
        ->assertHasFormErrors(['user_type']);
});

it('creates a full profile with a 役職', function () {
    $manager = Position::createAtEnd('課長');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => '田中',
            'email' => 'tanaka@example.test',
            'password' => 'Initial-123',
            'employment_status' => 'active',
            ...employeeMasterFields(['position_id' => $manager->id]),
            'last_name' => '田中',
            'first_name' => '次郎',
            'birthday' => '1990-04-01',
            'address' => '東京都千代田区1-1',
            'phone' => '03-1234-5678',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'tanaka@example.test')->firstOrFail();
    expect($user->position_id)->toBe($manager->id)
        ->and($user->birthday?->format('Y-m-d'))->toBe('1990-04-01');

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])->assertSee('課長');
});

it('keeps 役職 optional and single-choice, from active options only', function () {
    $retired = Position::createAtEnd('相談役', active: false);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'A', 'email' => 'a@example.test', 'employment_status' => 'active', ...employeeMasterFields()])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'a@example.test')->value('position_id'))->toBeNull()
        ->and(Position::options())->not->toContain('相談役')
        ->and(Position::options($retired->id))->toContain('相談役');

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'B', 'email' => 'b@example.test', 'employment_status' => 'active', ...employeeMasterFields(['position_id' => 999])])
        ->call('create')
        ->assertHasFormErrors(['position_id']);
});

it('adds a 役職 option from the dropdown', function () {
    Position::createAtEnd('部長');

    Livewire::test(CreateUser::class)
        ->callAction(TestAction::make('createOption')->schemaComponent('position_id'), data: ['name' => '部長'])
        ->assertHasActionErrors(['name' => 'unique']);

    Livewire::test(CreateUser::class)
        ->callAction(TestAction::make('createOption')->schemaComponent('position_id'), data: ['name' => '主任'])
        ->assertHasNoFormErrors()
        ->assertSchemaStateSet(['position_id' => Position::query()->where('name', '主任')->value('id')]);
});

it('no longer has organizations on the user form', function () {
    Livewire::test(CreateUser::class)->assertFormFieldDoesNotExist('organizationMemberships');
});

it('edits a user and forces a password change when the admin sets a new password', function () {
    $user = User::factory()->inCategories((int) EmployeeCategory::query()->value('id'))->create(Arr::except(employeeMasterFields(), 'employee_category_ids'));

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'employment_status' => EmploymentStatus::Retired->value,
            'password' => 'Reset-9876',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    expect($user->employment_status)->toBe(EmploymentStatus::Retired)
        ->and($user->must_change_password)->toBeTrue();
});

it('resets a password from the user page', function () {
    $user = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->callAction('resetPassword', data: ['password' => null])
        ->assertNotified('パスワードを再設定しました');

    expect($user->fresh()->must_change_password)->toBeTrue();
});

it('does not allow editing or deleting the env-managed super admin', function () {
    $this->get("/settings/users/{$this->admin->id}/edit")->assertForbidden();

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('edit', $this->admin)
        ->assertTableActionHidden('delete', $this->admin);
});

it('deletes a user', function () {
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)->callTableAction('delete', $user);

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});

it('manages 役職 options on their own screen and protects options in use', function () {
    $manager = Position::createAtEnd('課長');
    User::factory()->create(['position_id' => $manager->id]);
    $unused = Position::createAtEnd('係長');

    Livewire::test(ManagePositions::class)
        ->assertCanSeeTableRecords([$manager, $unused])
        ->callAction('create', data: ['name' => '部長', 'is_active' => true])
        ->assertHasNoActionErrors()
        ->assertTableActionHidden('delete', $manager)
        ->callTableAction('delete', $unused);

    expect(Position::query()->orderBy('sort_order')->pluck('name')->all())->toBe(['課長', '部長']);
});
