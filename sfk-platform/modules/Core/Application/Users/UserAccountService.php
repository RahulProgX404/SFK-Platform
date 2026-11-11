<?php

declare(strict_types=1);

namespace Modules\Core\Application\Users;

use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Access\DashboardAccess;
use Modules\Core\Access\UserType;
use Modules\Core\Application\Apps\SpaceMembership;
use Modules\Core\Application\Cache\ReferenceCache;
use Modules\Core\Models\EmployeeCategory;

/**
 * Account lifecycle used by user management.
 *
 * Password policy: any password set by an administrator (on creation or reset) is temporary — the
 * user must replace it on their next sign-in (`must_change_password`). Only the user's own change
 * clears the flag.
 *
 * ユーザー種別 hierarchy ({@see UserType::manageableTypes()}): a super admin creates/edits/deletes admins
 * and employees, an admin only employees, an employee nobody. super_admin itself is never assigned
 * here (SuperAdminProvisioner only). A null `$actor` is a trusted system call (console, seeders).
 * Dashboards: `dashboards` grants extra dashboards to the user on top of their 従業員カテゴリ; administrator-only
 * dashboards (共通管理) are always dropped from it because they follow ユーザー種別 (DashboardAccess).
 * Spaces: `space_ids` sets the spaces the employee is a member of by name, among the spaces of the dashboards they
 * can open (own + 従業員カテゴリ, after this save); memberships in spaces of other dashboards are kept. The spaces of
 * their 従業員カテゴリ follow the categories (SpaceMembership). Only employees are space members (as on スペース), so
 * a user who stops being an employee leaves all spaces. Leaving a space drops their 個人 row of its アクセス権.
 */
final class UserAccountService
{
    /**
     * @param  array<string, mixed>  $attributes  profile attributes (name, email, 氏名, 在籍状況, user_type …)
     */
    public function create(array $attributes, ?string $password = null, ?User $actor = null): IssuedCredentials
    {
        $type = $this->assignableType($attributes['user_type'] ?? null, $actor, UserType::Employee);
        unset($attributes['user_type']);
        $attributes = $this->withGrantableDashboards($attributes);
        [$attributes, $categoryIds] = $this->extractCategories($attributes);
        [$attributes, $spaceIds] = $this->extractSpaces($attributes);
        $generated = blank($password) ? $this->generatePassword() : null;

        $user = new User($attributes);
        $user->password = $generated ?? (string) $password;
        $user->forceFill(['user_type' => $type, 'must_change_password' => true, 'managed_by_env' => false])->save();
        if ($categoryIds !== null) {
            $user->employeeCategories()->sync($categoryIds);
            ReferenceCache::bump(); // their dashboards and access follow the categories
        }
        $this->syncSpaces($user, $spaceIds);

        return new IssuedCredentials($user, $generated);
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(User $user, array $attributes, ?string $password = null, ?User $actor = null): IssuedCredentials
    {
        $this->authorizeManage($user, $actor);
        $type = $this->assignableType($attributes['user_type'] ?? null, $actor);
        unset($attributes['user_type']);
        $attributes = $this->withGrantableDashboards($attributes);
        [$attributes, $categoryIds] = $this->extractCategories($attributes);
        [$attributes, $spaceIds] = $this->extractSpaces($attributes);

        $user->fill($attributes);
        if ($type !== null) {
            $user->forceFill(['user_type' => $type]);
        }
        if (filled($password)) {
            $this->applyTemporaryPassword($user, (string) $password);
        }
        $user->save();
        if ($categoryIds !== null) {
            $user->employeeCategories()->sync($categoryIds);
            ReferenceCache::bump(); // their dashboards and access follow the categories
        }
        $this->syncSpaces($user, $spaceIds);

        return new IssuedCredentials($user);
    }

    /** Administrator sets (or generates) a new password; the user must change it at next sign-in. */
    public function resetPassword(User $user, ?string $password = null, ?User $actor = null): IssuedCredentials
    {
        $this->authorizeManage($user, $actor);

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

    /** The user keeps the temporary password set by an administrator; they are no longer asked to change it. */
    public function keepTemporaryPassword(User $user): void
    {
        $user->forceFill(['must_change_password' => false])->save();
    }

    public function delete(User $user, ?User $actor = null): void
    {
        if ($actor !== null && $actor->is($user)) {
            throw new ProtectedAccountException('自分自身のアカウントは削除できません。');
        }

        $this->authorizeManage($user, $actor);

        $user->delete();
    }

    /**
     * Resolves the requested ユーザー種別 (or `$default` when none was requested) and checks the actor may
     * assign it. Null only when nothing was requested and there is no default (update keeps the type).
     */
    private function assignableType(mixed $requested, ?User $actor, ?UserType $default = null): ?UserType
    {
        $type = match (true) {
            $requested instanceof UserType => $requested,
            blank($requested) => $default,
            default => UserType::tryFrom((string) $requested) ?? throw new InvalidArgumentException('このユーザー種別は割り当てできません。'),
        };

        if ($type === null) {
            return null;
        }

        if ($type === UserType::SuperAdmin) {
            throw new InvalidArgumentException('このユーザー種別は割り当てできません。');
        }

        if ($actor !== null && ! $actor->user_type->canManage($type)) {
            throw new ProtectedAccountException("「{$type->label()}」のユーザーを登録・変更する権限がありません。");
        }

        return $type;
    }

    /**
     * Takes `employee_category_ids` (従業員カテゴリ, several allowed) out of the attributes; null when not given.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: array<string, mixed>, 1: list<int>|null}
     */
    private function extractCategories(array $attributes): array
    {
        if (! array_key_exists('employee_category_ids', $attributes)) {
            return [$attributes, null];
        }

        $requested = array_map('intval', (array) $attributes['employee_category_ids']);
        unset($attributes['employee_category_ids']);

        return [$attributes, EmployeeCategory::query()->whereIn('id', $requested)->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all()];
    }

    /**
     * Takes `space_ids` (スペース) out of the attributes; null when not given.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: array<string, mixed>, 1: list<int>|null}
     */
    private function extractSpaces(array $attributes): array
    {
        if (! array_key_exists('space_ids', $attributes)) {
            return [$attributes, null];
        }

        $requested = array_values(array_unique(array_map('intval', (array) $attributes['space_ids'])));
        unset($attributes['space_ids']);

        return [$attributes, $requested];
    }

    /**
     * Sets the spaces the user is a member of by name among those of the dashboards they can open now (requested ids
     * of other dashboards are ignored, memberships there kept) and those of their 従業員カテゴリ. A user who is not an
     * employee leaves every space.
     *
     * @param  list<int>|null  $spaceIds
     */
    private function syncSpaces(User $user, ?array $spaceIds): void
    {
        app(SpaceMembership::class)->syncUser($user, $spaceIds, $spaceIds !== null ? $user->grantedDashboards() : []);
    }

    /**
     * Keeps only dashboards that may be granted per user (drops 共通管理 and duplicates).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withGrantableDashboards(array $attributes): array
    {
        if (array_key_exists('dashboards', $attributes)) {
            $ids = array_map(fn (mixed $id): string => (string) $id, (array) $attributes['dashboards']);
            $attributes['dashboards'] = array_values(array_unique(array_filter(
                $ids,
                fn (string $id): bool => ! DashboardAccess::isAdminOnly($id),
            )));
        }

        return $attributes;
    }

    private function authorizeManage(User $user, ?User $actor): void
    {
        if ($user->managed_by_env) {
            throw new ProtectedAccountException('スーパー管理者は環境変数で管理されているため、ここでは変更できません。');
        }

        if ($actor !== null && ! $actor->canManageUser($user)) {
            throw new ProtectedAccountException("「{$user->user_type->label()}」のユーザーを変更する権限がありません。");
        }
    }

    private function applyTemporaryPassword(User $user, string $password): void
    {
        $user->password = $password;
        $user->forceFill(['must_change_password' => true]);
    }

    private function generatePassword(): string
    {
        return Str::password(12, symbols: false);
    }
}
