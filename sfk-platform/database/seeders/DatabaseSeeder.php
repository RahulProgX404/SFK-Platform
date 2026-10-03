<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\ModuleManager;
use Illuminate\Database\Seeder;
use Modules\Core\Access\Roles;

class DatabaseSeeder extends Seeder
{
    /**
     * Runs each enabled module's seeder (Modules\<Name>\Database\Seeders\<Name>DatabaseSeeder)
     * in dependency order, then creates the initial administrator when configured.
     */
    public function run(ModuleManager $modules): void
    {
        foreach ($modules->enabled() as $module) {
            $seeder = $module->namespace().'\\Database\\Seeders\\'.$module->name.'DatabaseSeeder';

            if (class_exists($seeder)) {
                $this->call($seeder);
            }
        }

        $this->seedAdministrator();
    }

    private function seedAdministrator(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set — no administrator created.');

            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => env('ADMIN_NAME', 'Administrator'), 'password' => $password],
        );

        $user->assignRole(Roles::SUPER_ADMIN);
    }
}
