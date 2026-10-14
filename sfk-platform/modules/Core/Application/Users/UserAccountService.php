<?php

declare(strict_types=1);

namespace Modules\Core\Application\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Access\PanelPermission;
use Modules\Core\Access\Roles;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Account lifecycle used by user management.
 *
 * Password policy: any password set by an administrator (on creation or reset) is temporary — the
 * user must replace it on their next sign-in (`must_change_password`). Only the user's own change
 * clears the flag.
 */
final class UserAccountService
{
    private const GUARD = 'web';

    /**
     * @param  array<string, mixed>  $attributes  profile attributes (name, email, 氏名, 在籍状況 …)
     * @param  list<string>  $roles  role names typed by the administrator; created when new
     * @param  list<string>  $panelIds  panels the user may open (panel.<id>.access)
     */
    public function create(array $attributes, array $roles, array $panelIds = [], ?string $password = null): IssuedCredentials
    {
        $generated = blank($password) ? $this->generatePassword() : null;

        $user = DB::transaction(function () use ($attributes, $roles, $panelIds, $password, $generated): User {
            $user = new User($attributes);
            $user->password = $generated ?? (string) $password;
            $user->forceFill(['must_change_password' => true, 'managed_by_env' => false])->save();

            $this->syncRoles($user, $roles);
            $this->syncPanelAccess($user, $panelIds);

            return $user;
        });

        return new IssuedCredentials($user, $generated);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $roles
     * @param  list<string>  $panelIds
     */
    public function update(User $user, array $attributes, array $roles, array $panelIds = [], ?string $password = null): IssuedCredentials
    {
        $this->guardManaged($user);

        DB::transaction(function () use ($user, $attributes, $roles, $panelIds, $password): void {
            $user->fill($attributes);
            if (filled($password)) {
                $this->applyTemporaryPassword($user, (string) $password);
            }
            $user->save();

            $this->syncRoles($user, $roles);
            $this->syncPanelAccess($user, $panelIds);
        });

        return new IssuedCredentials($user);
    }

    /** Administrator sets (or generates) a new password; the user must change it at next sign-in. */
    public function resetPassword(User $user, ?string $password = null): IssuedCredentials
    {
        $this->guardManaged($user);

        $generated = blank($password) ? $this->generatePassword() : null;
        $this->applyTemporaryPassword($user, $generated ?? (string) $password);
        $user->save();

        return new IssuedCredentials($user, $generated);
    }

    /** The user replaces their own password; this clears the forced change. */
    public function changeOwnPassword(User $user, string $password): void
    {
        $user->password = $password;
        $user->forceFill(['must_change_password' => false, 'password_changed_at' => now()])->save();
    }

    public function delete(User $user, ?User $actor = null): void
    {
        $this->guardManaged($user);

        if ($actor !== null && $actor->is($user)) {
            throw new ProtectedAccountException('自分自身のアカウントは削除できません。');
        }

        $user->delete();
    }

    public function canManage(User $user): bool
    {
        return ! $user->managed_by_env;
    }

    /** @return list<string> panel ids the user has direct access to */
    public function panelAccess(User $user): array
    {
        $panelIds = [];
        foreach ($user->getDirectPermissions() as $permission) {
            if (preg_match('/^panel\.(.+)\.access$/', (string) $permission->name, $match) === 1) {
                $panelIds[] = $match[1];
            }
        }

        return $panelIds;
    }

    /**
     * Trims, de-duplicates and drops empty role names.
     *
     * @param  array<array-key, mixed>  $roles
     * @return list<string>
     */
    public static function normalizeRoles(array $roles): array
    {
        $names = array_map(fn (mixed $role): string => trim((string) $role), $roles);

        return array_values(array_unique(array_filter($names, fn (string $role): bool => $role !== '')));
    }

    /** @param  list<string>  $roles */
    private function syncRoles(User $user, array $roles): void
    {
        $roles = self::normalizeRoles($roles);

        foreach ($roles as $role) {
            if (Roles::isReserved($role)) {
                throw new InvalidArgumentException("ロール「{$role}」は予約されているため割り当てできません。");
            }
        }

        $user->syncRoles(array_map(fn (string $role): RoleContract => Role::findOrCreate($role, self::GUARD), $roles));
    }

    /**
     * Replaces the user's panel permissions; other direct permissions are kept.
     *
     * @param  list<string>  $panelIds
     */
    private function syncPanelAccess(User $user, array $panelIds): void
    {
        $keep = $user->getDirectPermissions()
            ->reject(fn (Permission $permission): bool => str_starts_with((string) $permission->name, 'panel.'))
            ->all();

        $panel = array_map(
            fn (string $id): PermissionContract => Permission::findOrCreate(PanelPermission::for($id), self::GUARD),
            array_values(array_unique($panelIds)),
        );

        $user->syncPermissions([...$keep, ...$panel]);
    }

    private function applyTemporaryPassword(User $user, string $password): void
    {
        $user->password = $password;
        $user->forceFill(['must_change_password' => true]);
    }

    private function guardManaged(User $user): void
    {
        if ($user->managed_by_env) {
            throw new ProtectedAccountException('スーパー管理者は環境変数で管理されているため、ここでは変更できません。');
        }
    }

    private function generatePassword(): string
    {
        return Str::password(12, symbols: false);
    }
}
