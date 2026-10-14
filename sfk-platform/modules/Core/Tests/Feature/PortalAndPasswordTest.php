<?php

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Access\PanelPermission;
use Modules\Core\Access\Roles;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Http\Responses\PortalLoginResponse;

beforeEach(fn () => $this->seed(CoreDatabaseSeeder::class));

function superAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Roles::SUPER_ADMIN);

    return $user;
}

it('sends guests from the portal to the login page', function () {
    $this->get('/portal')->assertRedirect('/erp/login');
});

it('opens the portal after login on any panel', function () {
    expect(app(LoginResponse::class))->toBeInstanceOf(PortalLoginResponse::class);
});

it('lets a super admin choose between the ERP and the settings dashboards', function () {
    $admin = superAdmin();

    $this->actingAs($admin)
        ->get('/portal')
        ->assertOk()
        ->assertSee("{$admin->name} さん、ようこそ")
        ->assertDontSee('ダッシュボードを選択してください')
        ->assertSee('白石封筒')
        ->assertSee('ERP 共通管理');
});

it('sends a user with a single dashboard straight to it', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PanelPermission::for('erp'));

    $this->actingAs($user)->get('/portal')->assertRedirect('/erp');
});

it('blocks people who left the company from every panel', function () {
    $user = User::factory()->create(['employment_status' => EmploymentStatus::Retired]);
    $user->givePermissionTo(PanelPermission::for('erp'));

    $this->actingAs($user)->get('/erp')->assertForbidden();
});

it('forces a password change before the portal or a panel can be used', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PanelPermission::for('erp'));
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)->get('/portal')->assertRedirect('/account/password');
    $this->actingAs($user)->get('/erp')->assertRedirect('/account/password');
    $this->actingAs($user)->get('/account/password')->assertOk()->assertSee('仮パスワード');
});

it('clears the forced change once the user sets a new password', function () {
    $user = User::factory()->create(['password' => 'Temp-1234']);
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)
        ->put('/account/password', [
            'current_password' => 'Temp-1234',
            'password' => 'NewPass-5678',
            'password_confirmation' => 'NewPass-5678',
        ])
        ->assertRedirect('/portal')
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->must_change_password)->toBeFalse()
        ->and($user->password_changed_at)->not->toBeNull()
        ->and(Hash::check('NewPass-5678', $user->password))->toBeTrue();
});

it('rejects a wrong current password and weak new passwords in Japanese', function () {
    $user = User::factory()->create(['password' => 'Temp-1234']);

    $this->actingAs($user)
        ->from('/account/password')
        ->put('/account/password', [
            'current_password' => 'wrong',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertSessionHasErrors([
            'current_password' => 'パスワードが正しくありません。',
            'password',
        ]);
});

it('does not let the env-managed super admin change the password on screen', function () {
    $admin = superAdmin();
    $admin->forceFill(['managed_by_env' => true])->save();

    $this->actingAs($admin)->get('/account/password')->assertOk()->assertSee('環境変数で管理');
    $this->actingAs($admin)
        ->put('/account/password', ['current_password' => 'password', 'password' => 'Abcdefg1', 'password_confirmation' => 'Abcdefg1'])
        ->assertSessionHasErrors('password');
});

it('records the last login time', function () {
    $user = User::factory()->create();

    auth()->login($user);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});
