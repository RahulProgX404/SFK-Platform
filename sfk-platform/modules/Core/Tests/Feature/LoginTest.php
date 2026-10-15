<?php

/*
 * The single login page of the whole system (/login): dashboards have none of their own, so it no longer
 * matters which dashboard someone logged out of.
 */

use App\Models\User;
use Modules\Core\Domain\People\EmploymentStatus;

it('shows the SFK システム login page', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('SFK システム')
        ->assertSee('メールアドレス')
        ->assertSee('パスワード');
});

it('signs in and opens the dashboard selection', function () {
    $user = grantDashboards(User::factory()->create(['password' => 'Login-1234']), 'erp');

    $this->post('/login', ['email' => $user->email, 'password' => 'Login-1234'])->assertRedirect('/portal');

    $this->assertAuthenticatedAs($user);
});

it('returns to the page first asked for', function () {
    $user = grantDashboards(User::factory()->create(['password' => 'Login-1234']), 'erp');

    $this->get('/erp')->assertRedirect('/login');
    $this->post('/login', ['email' => $user->email, 'password' => 'Login-1234'])->assertRedirect('/erp');
});

it('lets an employee sign in right after an admin logged out of 共通管理', function () {
    $admin = User::factory()->admin()->create(['password' => 'Admin-1234']);
    $employee = grantDashboards(User::factory()->create(['password' => 'Staff-1234']), 'erp');

    $this->post('/login', ['email' => $admin->email, 'password' => 'Admin-1234']);
    $this->get('/settings')->assertOk();
    $this->post('/settings/logout')->assertRedirect('/login');
    $this->assertGuest();

    $this->post('/login', ['email' => $employee->email, 'password' => 'Staff-1234'])->assertRedirect('/portal');
    $this->assertAuthenticatedAs($employee);
    $this->get('/erp')->assertOk();
});

it('logs out from the account pages too', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});

it('rejects wrong credentials in Japanese', function () {
    $user = User::factory()->create(['password' => 'Login-1234']);

    $this->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);

    $this->assertGuest();
});

it('does not let people who left the company sign in', function () {
    $user = User::factory()->create(['password' => 'Login-1234', 'employment_status' => EmploymentStatus::Retired]);

    $this->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => 'Login-1234'])
        ->assertSessionHasErrors(['email' => 'このアカウントではログインできません。']);

    $this->assertGuest();
});

it('slows down repeated failures', function () {
    $user = User::factory()->create(['password' => 'Login-1234']);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => 'Login-1234'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('sends signed-in users from /login to the dashboard selection', function () {
    $this->actingAs(User::factory()->create())->get('/login')->assertRedirect('/portal');
});
