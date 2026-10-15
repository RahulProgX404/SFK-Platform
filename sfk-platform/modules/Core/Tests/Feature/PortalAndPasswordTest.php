<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Domain\People\EmploymentStatus;

function superAdmin(): User
{
    $user = User::factory()->superAdmin()->create();

    return $user;
}

it('sends guests from the portal to the login page', function () {
    $this->get('/portal')->assertRedirect('/login');
});

it('shows dashboard cards with name and icon only', function () {
    $this->actingAs(superAdmin())
        ->get('/portal')
        ->assertOk()
        ->assertSee('白石封筒')
        ->assertSee('sp-card', escape: false)
        ->assertDontSee('日々の業務を行います')
        ->assertDontSee('システム全体の設定を管理します');
});

it('lets a super admin choose between the ERP and the settings dashboards', function () {
    $admin = superAdmin();

    $this->actingAs($admin)
        ->get('/portal')
        ->assertOk()
        ->assertSee("{$admin->name} さん、ようこそ")
        ->assertDontSee('ダッシュボードを選択してください')
        ->assertSee('白石封筒')
        ->assertSee('共通管理');
});

it('sends a user with a single dashboard straight to it', function () {
    $user = User::factory()->create();
    grantDashboards($user, 'erp');

    $this->actingAs($user)->get('/portal')->assertRedirect('/erp');
});

it('blocks people who left the company from every panel', function () {
    $user = User::factory()->create(['employment_status' => EmploymentStatus::Retired]);
    grantDashboards($user, 'erp');

    $this->actingAs($user)->get('/erp')->assertForbidden();
});

it('forces a password change before the portal or a panel can be used', function () {
    $user = User::factory()->create();
    grantDashboards($user, 'erp');
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)->get('/portal')->assertRedirect('/account/password');
    $this->actingAs($user)->get('/erp')->assertRedirect('/account/password');
    $this->actingAs($user)->get('/account/password')
        ->assertOk()
        ->assertSee('新しいパスワード')
        ->assertSee('仮パスワードをそのまま使う')
        ->assertDontSee('current_password');
});

it('sets a new password without asking for the temporary one', function () {
    $user = User::factory()->create(['password' => 'Temp-1234']);
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)
        ->put('/account/password', ['password' => 'NewPass-5678', 'password_confirmation' => 'NewPass-5678'])
        ->assertRedirect('/portal')
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->must_change_password)->toBeFalse()
        ->and($user->password_changed_at)->not->toBeNull()
        ->and(Hash::check('NewPass-5678', $user->password))->toBeTrue();
});

it('keeps the temporary password when asked to', function () {
    $user = User::factory()->create(['password' => 'Temp-1234']);
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)->post('/account/password/keep')->assertRedirect('/portal');

    $user->refresh();
    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('Temp-1234', $user->password))->toBeTrue();
});

it('rejects weak new passwords in Japanese', function () {
    $user = User::factory()->create();
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)
        ->from('/account/password')
        ->put('/account/password', ['password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->must_change_password)->toBeTrue();
});

it('sends users who need no change away from the password page', function () {
    $this->actingAs(User::factory()->create())->get('/account/password')->assertRedirect('/portal');
});

it('records the last login time', function () {
    $user = User::factory()->create();

    auth()->login($user);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});
