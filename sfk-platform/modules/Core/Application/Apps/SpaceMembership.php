<?php

declare(strict_types=1);

namespace Modules\Core\Application\Apps;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Access\UserType;
use Modules\Core\Models\Space;

/**
 * Who is a member of a スペース (user, 2026-11-11): the employees added by name (`space_user.direct`) and every employee
 * of the space's 従業員カテゴリ (`employee_category_space`) — also those who get the category later; they leave when the
 * category is taken off them or off the space, unless they were added by name too. Only employees are members.
 *
 * `space_user` keeps all members, so everything that asks for membership (AppAccess, Space::hasMember(), the sidebar,
 * the dashboard's アプリ) reads one table. Leaving a space drops the member's 個人 row of its アクセス権.
 */
final class SpaceMembership
{
    /**
     * Sets the members of a space: the given employees by name (null: those it has) and the employees of its
     * 従業員カテゴリ (null: those it has).
     *
     * @param  list<int>|null  $directUserIds
     * @param  list<int>|null  $categoryIds
     */
    public function syncSpace(Space $space, ?array $directUserIds = null, ?array $categoryIds = null): void
    {
        if ($categoryIds !== null) {
            $space->employeeCategories()->sync(array_values(array_unique($categoryIds)));
        }

        $direct = $directUserIds !== null
            ? self::employeeIds($directUserIds)
            : DB::table('space_user')->where('space_id', $space->id)->where('direct', true)->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
        $members = array_fill_keys($direct, true) + array_fill_keys(self::employeesOfCategories(self::categoryIdsOf($space)), false);

        $space->users()->sync(array_map(fn (bool $byName): array => ['direct' => $byName], $members));
        SpaceService::dropRowsOfFormerMembers($space->id);
        Space::forgetCached();
    }

    /**
     * Sets the spaces of a user: the spaces they were added to by name — among `$panelIds`, `$directSpaceIds` replaces
     * them (null: kept), those of other dashboards are kept — and the spaces of their 従業員カテゴリ. A user who is not an
     * employee leaves every space.
     *
     * @param  list<int>|null  $directSpaceIds
     * @param  list<string>  $panelIds  the dashboards `$directSpaceIds` chooses in
     */
    public function syncUser(User $user, ?array $directSpaceIds = null, array $panelIds = []): void
    {
        $spaces = [];
        if ($user->user_type === UserType::Employee) {
            $direct = DB::table('space_user')->where('user_id', $user->id)->where('direct', true)->pluck('space_id')->map(fn (mixed $id): int => (int) $id)->all();
            if ($directSpaceIds !== null) {
                $offered = Space::query()->whereIn('panel_id', $panelIds)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
                $direct = [...array_diff($direct, $offered), ...array_intersect(array_map('intval', $directSpaceIds), $offered)];
            }
            $byCategory = DB::table('employee_category_space')
                ->join('employee_category_user', 'employee_category_user.employee_category_id', '=', 'employee_category_space.employee_category_id')
                ->where('employee_category_user.user_id', $user->id)
                ->distinct()
                ->pluck('employee_category_space.space_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
            $spaces = array_fill_keys($direct, true) + array_fill_keys($byCategory, false);
        }

        $current = DB::table('space_user')->where('user_id', $user->id)->pluck('direct', 'space_id')->map(fn (mixed $direct): bool => (bool) $direct)->all();
        if ($current == $spaces) {
            return;
        }

        $user->spaces()->sync(array_map(fn (bool $byName): array => ['direct' => $byName], $spaces));
        SpaceService::dropRowsOfFormerMembers(userId: $user->id);
        Space::forgetCached();
    }

    /**
     * The employees a space would have: those by name and those of the categories.
     *
     * @param  list<int>  $directUserIds
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    public static function membersOf(array $directUserIds, array $categoryIds): array
    {
        return array_values(array_unique([...self::employeeIds($directUserIds), ...self::employeesOfCategories($categoryIds)]));
    }

    /**
     * @param  list<int>  $categoryIds
     * @return list<int> the employees of these 従業員カテゴリ
     */
    public static function employeesOfCategories(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        return User::query()
            ->where('user_type', UserType::Employee->value)
            ->whereHas('employeeCategories', fn ($query) => $query->whereIn('employee_categories.id', $categoryIds))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $userIds
     * @return list<int> those of them who are employees
     */
    private static function employeeIds(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        return $userIds === [] ? [] : User::query()
            ->whereIn('id', $userIds)
            ->where('user_type', UserType::Employee->value)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @return list<int> */
    private static function categoryIdsOf(Space $space): array
    {
        return DB::table('employee_category_space')->where('space_id', $space->id)->pluck('employee_category_id')->map(fn (mixed $id): int => (int) $id)->all();
    }
}
