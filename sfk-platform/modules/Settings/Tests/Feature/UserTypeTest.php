<?php

/*
 * ユーザー種別 (how a user uses the system) vs 従業員カテゴリ (position in the company).
 * Only a super admin creates admins; an admin creates employees; employees manage nobody.
 */

use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Access\UserType;
use Modules\Core\Application\Users\ProtectedAccountException;
use Modules\Core\Application\Users\UserAccountService;
use Modules\Settings\Filament\Resources\Users\Pages\CreateUser;
use Modules\Settings\Filament\Resources\Users\Pages\EditUser;
use Modules\Settings\Filament\Resources\Users\Pages\ListUsers;

beforeEach(function () {
    Filament::setCurrentPanel('settings');

    $this->superAdmin = User::factory()->superAdmin()->create();
    $this->superAdmin->forceFill(['managed_by_env' => true])->save();

    $this->admin = User::factory()->admin()->create();

    $this->employee = User::factory()->create();
});

function userData(array $overrides = []): array
{
    return [
        'name' => '新規',
        'email' => 'new@example.test',
        'employment_status' => 'active',
        ...employeeMasterFields(),
        ...$overrides,
    ];
}

it('lets the super admin create an admin', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(CreateUser::class)
        ->fillForm(userData(['user_type' => 'admin']))
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'new@example.test')->firstOrFail();
    expect($user->user_type)->toBe(UserType::Admin);
});

it('lets an admin create employees but not admins', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateUser::class)
        ->assertSchemaStateSet(['user_type' => 'employee'])
        ->fillForm(userData(['user_type' => 'admin']))
        ->call('create')
        ->assertHasFormErrors(['user_type']);

    Livewire::test(CreateUser::class)
        ->fillForm(userData(['user_type' => 'employee']))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'new@example.test')->value('user_type'))->toBe(UserType::Employee);

    expect(fn () => app(UserAccountService::class)->create(userData(['email' => 'b@example.test', 'user_type' => 'admin']), actor: $this->admin))
        ->toThrow(ProtectedAccountException::class);
});

it('keeps employees from creating or editing users', function () {
    $this->actingAs($this->employee);

    $this->get('/settings/users/create')->assertForbidden();
    $this->get("/settings/users/{$this->admin->id}/edit")->assertForbidden();

    Livewire::test(ListUsers::class)
        ->assertActionHidden('create')
        ->assertTableActionHidden('edit', $this->admin)
        ->assertTableActionHidden('delete', $this->admin);

    expect(fn () => app(UserAccountService::class)->create(userData(), actor: $this->employee))
        ->toThrow(ProtectedAccountException::class);
});

it('lets an admin manage employees only', function () {
    $other = User::factory()->admin()->create();
    $this->actingAs($this->admin);

    $this->get("/settings/users/{$this->employee->id}/edit")->assertOk();
    $this->get("/settings/users/{$other->id}/edit")->assertForbidden();
    $this->get("/settings/users/{$this->admin->id}/edit")->assertForbidden();

    Livewire::test(ListUsers::class)
        ->assertTableActionVisible('delete', $this->employee)
        ->assertTableActionHidden('delete', $other)
        ->assertTableActionHidden('resetPassword', $other);

    $service = app(UserAccountService::class);
    expect(fn () => $service->delete($other, $this->admin))->toThrow(ProtectedAccountException::class)
        ->and(fn () => $service->resetPassword($other, null, $this->admin))->toThrow(ProtectedAccountException::class)
        ->and(fn () => $service->update($this->employee, ['user_type' => 'admin'], actor: $this->admin))
        ->toThrow(ProtectedAccountException::class);
});

it('lets the super admin promote an employee and demote an admin', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(EditUser::class, ['record' => $this->employee->getRouteKey()])
        ->fillForm(['user_type' => 'admin', ...employeeMasterFields()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->employee->fresh()->user_type)->toBe(UserType::Admin);

    Livewire::test(EditUser::class, ['record' => $this->admin->getRouteKey()])
        ->fillForm(['user_type' => 'employee', ...employeeMasterFields()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->admin->fresh()->user_type)->toBe(UserType::Employee);
});
