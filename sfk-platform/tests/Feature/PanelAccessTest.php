<?php

use App\Models\User;

it('redirects the site root to the dashboard selection portal', function () {
    $this->get('/')->assertRedirect('/portal');
});

it('sends guests of every dashboard to the single /login page', function () {
    $this->get('/erp')->assertRedirect('/login');
    $this->get('/settings')->assertRedirect('/login');
    $this->get('/erp/login')->assertNotFound();
    $this->get('/settings/login')->assertNotFound();
});

it('forbids users without the panel permission', function () {
    $this->actingAs(User::factory()->create())
        ->get('/erp')
        ->assertForbidden();
});

it('allows users with the panel permission', function () {
    $user = User::factory()->create();
    grantDashboards($user, 'erp');

    $this->actingAs($user)->get('/erp')->assertOk();
});

it('allows super admins without the モジュール card or a greeting on 白石封筒', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->get('/erp')
        ->assertOk()
        ->assertDontSee('モジュール')
        ->assertDontSee('今日も一日よろしくお願いします');
});
