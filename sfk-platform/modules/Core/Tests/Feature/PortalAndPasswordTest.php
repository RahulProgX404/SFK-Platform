<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Access\AppAccess;
use Modules\Core\Access\DashboardAccess;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Models\BusinessApp;

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
        ->assertSeeText("{$admin->name} さん")
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

    // signed out at once (EnsureAccountIsActive), not only refused
    $this->actingAs($user)->get('/erp')->assertRedirect('/login');
    $this->assertGuest();
    expect(DashboardAccess::allows($user, 'erp'))->toBeFalse();
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
        ->assertDontSee('仮パスワードをそのまま使う') // a temporary password is never kept (security review 2026-11-14)
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

it('never keeps the temporary password: no way to skip the change, and not the same one again (2026-11-14)', function () {
    $user = grantDashboards(User::factory()->create(['password' => 'Temp-1234']), 'erp');
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)->post('/account/password/keep')->assertNotFound();
    $this->actingAs($user)
        ->put('/account/password', ['password' => 'Temp-1234', 'password_confirmation' => 'Temp-1234'])
        ->assertSessionHasErrors(['password' => '仮パスワードとは別のパスワードを入力してください。']);

    $user->refresh();
    expect($user->must_change_password)->toBeTrue();
    $this->actingAs($user)->get('/erp')->assertRedirect('/account/password');
});

it('signs out a user who may no longer sign in, also with an open session; an inactive administrator has no app rights (2026-11-14)', function () {
    $admin = grantDashboards(User::factory()->admin()->create(), 'erp');
    $app = new BusinessApp(['code' => 'sales-entry', 'name' => '06. 売上入力']);
    $app->forceFill(['status' => AppStatus::Published])->save();
    expect(AppAccess::abilitiesFor($admin, $app))->not->toBe([]);
    $this->actingAs($admin)->get('/portal')->assertOk();

    // 退職 while signed in: the next request — inside a dashboard or outside (帳票出力, the portal) — signs out
    $admin->forceFill(['employment_status' => EmploymentStatus::Retired])->save();
    $this->actingAs($admin->refresh())->get('/portal')->assertRedirect('/login');
    $this->assertGuest();
    $this->actingAs($admin)->get('/erp')->assertRedirect('/login');
    $this->actingAs($admin)->getJson('/documents/print/x')->assertUnauthorized();
    expect(AppAccess::abilitiesFor($admin, $app))->toBe([])
        ->and(AppAccess::manages($admin, 'sales-entry'))->toBeFalse();

    // super admins (environment accounts) always stay
    $super = User::factory()->superAdmin()->create();
    $super->forceFill(['employment_status' => EmploymentStatus::Retired])->save();
    $this->actingAs($super->refresh())->get('/portal')->assertOk();
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
