<?php

declare(strict_types=1);

namespace Modules\Core\Application\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Access\Roles;
use Modules\Core\Domain\People\EmploymentStatus;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Makes the super-admin account match the environment (config core.super_admin: name, email,
 * password). Idempotent. When the configured email changes, the previous env-managed account loses
 * the super-admin role, so exactly one account is ever owned by the environment.
 */
final class SuperAdminProvisioner
{
    public function isConfigured(): bool
    {
        return filled(config('core.super_admin.email')) && filled(config('core.super_admin.password'));
    }

    public function sync(): ?User
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $email = (string) config('core.super_admin.email');
        $password = (string) config('core.super_admin.password');
        $name = (string) (config('core.super_admin.name') ?: 'システム管理者');

        return DB::transaction(function () use ($email, $password, $name): User {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $role = Role::findOrCreate(Roles::SUPER_ADMIN, 'web');

            $user = User::query()->firstOrNew(['email' => $email]);
            $user->name = $name;
            $user->employment_status = EmploymentStatus::Active;

            if (! $user->exists || ! Hash::check($password, $user->getAuthPassword())) {
                $user->password = $password;
                $user->forceFill(['password_changed_at' => now()]);
            }

            $user->forceFill(['must_change_password' => false, 'managed_by_env' => true])->save();
            $user->assignRole($role);

            User::query()
                ->where('managed_by_env', true)
                ->whereKeyNot($user->getKey())
                ->get()
                ->each(function (User $previous) use ($role): void {
                    $previous->removeRole($role);
                    $previous->forceFill(['managed_by_env' => false])->save();
                });

            return $user;
        });
    }
}
