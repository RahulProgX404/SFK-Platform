<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Modules\Core\Access\PanelPermission;
use Modules\Core\Access\Roles;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the super-admin role and one access permission per registered Filament panel.
 * Idempotent — safe to run after enabling a new extension module.
 */
final class CoreDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate(Roles::SUPER_ADMIN, 'web');

        foreach (array_keys(Filament::getPanels()) as $panelId) {
            Permission::findOrCreate(PanelPermission::for($panelId), 'web');
        }
    }
}
