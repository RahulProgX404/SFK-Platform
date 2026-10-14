<?php

namespace Database\Seeders;

use App\Modules\ModuleManager;
use Illuminate\Database\Seeder;
use Modules\Core\Application\Users\SuperAdminProvisioner;

class DatabaseSeeder extends Seeder
{
    /**
     * Runs each enabled module's seeder (Modules\<Name>\Database\Seeders\<Name>DatabaseSeeder)
     * in dependency order, then syncs the super admin from the environment when configured.
     */
    public function run(ModuleManager $modules, SuperAdminProvisioner $superAdmin): void
    {
        foreach ($modules->enabled() as $module) {
            $seeder = $module->namespace().'\\Database\\Seeders\\'.$module->name.'DatabaseSeeder';

            if (class_exists($seeder)) {
                $this->call($seeder);
            }
        }

        if ($superAdmin->sync() === null) {
            $this->command?->warn('SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD not set — no super admin created.');
        }
    }
}
