<?php

use App\Models\User;
use Filament\Facades\Filament;
use Modules\Core\Access\PanelPermission;
use Modules\Core\Access\Roles;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;

beforeEach(fn () => $this->seed(CoreDatabaseSeeder::class));

it('registers the settings panel', function () {
    expect(Filament::getPanel('settings', isStrict: false)?->getId())->toBe('settings');
});

it('exposes the settings status endpoint', function () {
    $this->getJson('/api/settings/status')
        ->assertOk()
        ->assertJson(['module' => 'settings', 'status' => 'ok']);
});

it('shows the settings dashboard with its menu to super admins', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Roles::SUPER_ADMIN);

    $this->actingAs($admin)
        ->get('/settings')
        ->assertOk()
        ->assertSee('ERP 共通管理')
        ->assertSee('ユーザー管理')
        ->assertSee('組織・役職');
});

it('keeps users without the settings permission out', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PanelPermission::for('erp'));

    $this->actingAs($user)->get('/settings')->assertForbidden();
});
