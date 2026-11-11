<?php

declare(strict_types=1);

namespace Modules\Core\Access;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Modules\Core\Application\Cache\ReferenceCache;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Models\AppPermission;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;
use Modules\Core\Models\SpacePermission;
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
 *    - the permission setting of each of those spaces is added (スペースの権限, Space::permissions(): rows for 全員 /
 *      従業員カテゴリ / 個人, the same first match as the app rows within one space): the user gets every ability the
 *      app rows or any of their spaces holding the app give (space A 閲覧 + space B 閲覧・編集 ⇒ 閲覧・編集). App rows
 *      cannot take away what a space gives, nor one space what another gives.
 *    - メンテナンス中 keeps only the read-only abilities (閲覧, ファイル書き出し).
 *    - アプリ管理 (AppAbility::Manage, a row's can_manage) gives every ability and makes the user the app's
 *      administrator (manages()).
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

    /**
     * The user administers this app (アプリの設定, the buttons and steps for administrators, no レコード / フィールドの
     * アクセス権 limits): administrators always, others with アプリ管理 (AppAbility::Manage) — in this app only. What is
     * outside the apps (ダッシュボード設定, 共通管理, ユーザー管理 …) asks for ユーザー種別 instead.
     */
    public static function manages(?User $user, string $appCode): bool
    {
        return $user !== null && ($user->user_type->isAdministrator() || self::can($user, $appCode, AppAbility::Manage));
    }

    /** @return list<AppAbility> */
    public static function abilities(User $user, string $appCode): array
    {
        self::$perRequest ??= new WeakMap;
        $request = request();
        $cached = self::$perRequest[$request] ?? [];
        $key = $user->id.':'.$appCode;

        if (! array_key_exists($key, $cached)) {
            // from the cache between requests (ReferenceCache): the screens ask for every app of the sidebar
            $cached[$key] = array_map(fn (string $ability): AppAbility => AppAbility::from($ability), ReferenceCache::remember(
                'abilities:'.$key,
                fn (): array => array_map(fn (AppAbility $ability): string => $ability->value, self::abilitiesFor($user, BusinessApp::forCode($appCode))),
            ));
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

        $spaces = $app->spaces->filter(fn (Space $space): bool => DashboardAccess::allows($user, $space->panel_id)
            && $space->hasMember($user));

        if ($spaces->isEmpty()) {
            return [];
        }

        $categoryIds = self::categoryIds($user);
        $abilities = self::combined([
            self::firstMatch($app->permissions()->applyingTo($user, $categoryIds)->get()),
            ...$spaces->map(fn (Space $space): array => self::spaceAbilities($user, $space, $categoryIds))->values()->all(),
        ]);

        if ($app->status->isReadOnlyForEmployees()) {
            $abilities = array_values(array_filter($abilities, fn (AppAbility $ability): bool => $ability->isReadOnly()));
        }

        return $abilities;
    }

    /**
     * Every ability any of the lists gives (in AppAbility order): A 閲覧 + B 閲覧・編集 ⇒ 閲覧・編集.
     *
     * @param  list<list<AppAbility>>  $lists
     * @return list<AppAbility>
     */
    private static function combined(array $lists): array
    {
        return array_values(array_filter(
            AppAbility::cases(),
            fn (AppAbility $ability): bool => array_any($lists, fn (array $list): bool => in_array($ability, $list, true)),
        ));
    }

    /**
     * The abilities a member gets from a space's permission setting (the same first match as the app rows).
     *
     * @param  list<int>  $categoryIds
     * @return list<AppAbility>
     */
    private static function spaceAbilities(User $user, Space $space, array $categoryIds): array
    {
        return self::firstMatch($space->permissionRowsFor($user, $categoryIds));
    }

    /** @return list<int> ids of the user's 従業員カテゴリ */
    private static function categoryIds(User $user): array
    {
        return array_map('intval', $user->employeeCategories()->pluck('employee_categories.id')->all());
    }

    /**
     * Of the rows that apply to the user: their own row, else the union of their categories' rows, else 全員, else
     * nothing. A matching level replaces the lower ones entirely.
     *
     * @param  Collection<int, AppPermission>|Collection<int, SpacePermission>  $rows
     * @return list<AppAbility>
     */
    private static function firstMatch(Collection $rows): array
    {
        $byLevel = $rows->groupBy(fn (AppPermission|SpacePermission $row): string => $row->subject_type->value);

        foreach ([PermissionSubject::User, PermissionSubject::Category, PermissionSubject::Everyone] as $level) {
            $matches = $byLevel->get($level->value);
            if ($matches !== null && $matches->isNotEmpty()) {
                return array_values(array_filter(
                    AppAbility::cases(),
                    fn (AppAbility $ability): bool => $matches->contains(fn (AppPermission|SpacePermission $row): bool => $row->allows($ability)),
                ));
            }
        }

        return [];
    }
}
