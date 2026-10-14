<?php

use App\Models\User;
use Modules\Core\Access\PanelPermission;
use Modules\Core\Access\Roles;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;

beforeEach(fn () => $this->seed(CoreDatabaseSeeder::class));

it('redirects the site root to the dashboard selection portal', function () {
    $this->get('/')->assertRedirect('/portal');
});

it('sends guests to the ERP login page', function () {
    $this->get('/erp')->assertRedirect('/erp/login');
    $this->get('/erp/login')->assertOk();
});

it('forbids users without the panel permission', function () {
    $this->actingAs(User::factory()->create())
        ->get('/erp')
        ->assertForbidden();
});

it('allows users with the panel permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PanelPermission::for('erp'));

    $this->actingAs($user)->get('/erp')->assertOk();
});

it('allows super admins and shows the modules overview', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Roles::SUPER_ADMIN);

    $this->actingAs($admin)
        ->get('/erp')
        ->assertOk()
        ->assertSee('モジュール');
});
