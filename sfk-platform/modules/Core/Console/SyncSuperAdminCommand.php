<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Application\Users\SuperAdminProvisioner;

final class SyncSuperAdminCommand extends Command
{
    protected $signature = 'core:super-admin';

    protected $description = 'Create or update the super-admin account from SUPER_ADMIN_NAME / SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD';

    public function handle(SuperAdminProvisioner $provisioner): int
    {
        if (! $provisioner->isConfigured()) {
            $this->components->warn('SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD are not set — no super admin created.');

            return self::FAILURE;
        }

        $user = $provisioner->sync();
        $this->components->info("Super admin [{$user?->email}] is in sync with the environment.");

        return self::SUCCESS;
    }
}
