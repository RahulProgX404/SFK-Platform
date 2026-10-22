<?php

declare(strict_types=1);

namespace Modules\Core\Access;

use App\Models\User;
use Illuminate\Http\Request;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Models\AppPermission;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;
use WeakMap;

/**
 * What a user may do in an app (permission.md), decided in this order:
 *
 * 1. ユーザー種別 super_admin: everything, in every status. An app that is not registered yet counts as 開発中.
 * 2. admin: everything once the app has left development (準備完了 / 公開中 / メンテナンス中 / 停止中).
 * 3. employee, only for 公開中 and メンテナンス中 apps, and only when
 *    - the app is placed in a space (it may be in several) whose dashboard the user can open (DashboardAccess)
 *      and of which the user is a member;
 *    - then the app's permission rows decide: the user's own row, else the rows of their 従業員カテゴリ (a user
 *      may have several: their abilities are combined), else the 全員 row, else nothing. A matching level
 *      replaces the lower ones entirely (like the legacy system's first match).
 *    - メンテナンス中 keeps only the read-only abilities (閲覧, ファイル書き出し).
 *
 * Results are memoized per user and app for the current request: screens ask many times per page.
 */
final class AppAccess
{
    /** @var WeakMap<Request, array<string, list<AppAbility>>>|null "userId:code" => abilities, per request */
    private static ?WeakMap $perRequest = null;

    public static function can(?User $user, string $appCode, AppAbility $ability): bool
    {
        return $user !== null && in_array($ability, self::abilities($user, $appCode), true);
    }

    /** @return list<AppAbility> */
    public static function abilities(User $user, string $appCode): array
    {
        self::$perRequest ??= new WeakMap;
        $request = request();
        $cached = self::$perRequest[$request] ?? [];
        $key = $user->id.':'.$appCode;

        if (! array_key_exists($key, $cached)) {
            $app = BusinessApp::query()->with('spaces')->where('code', $appCode)->first();
            $cached[$key] = self::abilitiesFor($user, $app);
            self::$perRequest[$request] = $cached;
        }

        return $cached[$key];
    }

    /**
     * Whether the app is listed on a dashboard (ポータル アプリ list, shortcuts, sidebar) for this user: super admins
     * see apps in every stage (the unpublished ones dimmed); everyone else only 公開中 / メンテナンス中 apps they may
     * view. An app not registered yet counts as 開発中.
     */
    public static function listsOnDashboard(User $user, ?BusinessApp $app): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $app !== null
            && $app->status->isOpenToEmployees()
            && self::can($user, $app->code, AppAbility::View);
    }

    /** @return list<AppAbility> */
    public static function abilitiesFor(User $user, ?BusinessApp $app): array
    {
        if ($user->isSuperAdmin()) {
            return AppAbility::cases();
        }

        if ($app === null || $app->status->isSuperAdminOnly()) {
            return [];
        }

        if ($user->user_type->isAdministrator()) {
            return AppAbility::cases();
        }

        return self::employeeAbilities($user, $app);
    }

    /** @return list<AppAbility> */
    private static function employeeAbilities(User $user, BusinessApp $app): array
    {
        if (! $app->status->isOpenToEmployees()) {
            return [];
        }

        $member = $app->spaces->contains(fn (Space $space): bool => DashboardAccess::allows($user, $space->panel_id)
            && $space->users()->whereKey($user->id)->exists());

        if (! $member) {
            return [];
        }

        $abilities = self::matchingAbilities($user, $app);

        if ($app->status->isReadOnlyForEmployees()) {
            $abilities = array_values(array_filter($abilities, fn (AppAbility $ability): bool => $ability->isReadOnly()));
        }

        return $abilities;
    }

    /**
     * The user's own row, else the union of their categories' rows, else 全員, else nothing.
     *
     * @return list<AppAbility>
     */
    private static function matchingAbilities(User $user, BusinessApp $app): array
    {
        $categoryIds = $user->employeeCategories()->pluck('employee_categories.id')->all();

        $rows = $app->permissions()
            ->where(function ($query) use ($user, $categoryIds): void {
                $query->where('subject_type', PermissionSubject::Everyone->value)
                    ->orWhere(fn ($query) => $query
                        ->where('subject_type', PermissionSubject::User->value)
                        ->where('user_id', $user->id))
                    ->orWhere(fn ($query) => $query
                        ->where('subject_type', PermissionSubject::Category->value)
                        ->whereIn('employee_category_id', $categoryIds));
            })
            ->get()
            ->groupBy(fn (AppPermission $permission): string => $permission->subject_type->value);

        foreach ([PermissionSubject::User, PermissionSubject::Category, PermissionSubject::Everyone] as $level) {
            $matches = $rows->get($level->value);
            if ($matches !== null && $matches->isNotEmpty()) {
                return array_values(array_filter(
                    AppAbility::cases(),
                    fn (AppAbility $ability): bool => $matches->contains(fn (AppPermission $permission): bool => $permission->allows($ability)),
                ));
            }
        }

        return [];
    }
}
