<?php

use App\Models\User;
use Filament\Facades\Filament;

it('registers the settings panel', function () {
    expect(Filament::getPanel('settings', isStrict: false)?->getId())->toBe('settings');
});

it('exposes the settings status endpoint', function () {
    $this->getJson('/api/settings/status')
        ->assertOk()
        ->assertJson(['module' => 'settings', 'status' => 'ok']);
});

it('shows the settings dashboard with its menu to super admins', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->get('/settings')
        ->assertOk()
        ->assertSee('共通管理')
        ->assertSee('ユーザー管理')
        ->assertSee('従業員カテゴリ')
        ->assertSee('役職');
});

it('keeps users without the settings permission out', function () {
    $user = User::factory()->create();
    grantDashboards($user, 'erp');

    $this->actingAs($user)->get('/settings')->assertForbidden();
});
