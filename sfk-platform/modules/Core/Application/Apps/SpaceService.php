<?php

declare(strict_types=1);

namespace Modules\Core\Application\Apps;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Access\DashboardAccess;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\Space;
use Modules\Core\Models\SpacePermission;

/**
 * Spaces of one dashboard, maintained by administrators in that dashboard's ダッシュボード設定: name, members,
 * apps and the space's permission setting (スペースの権限: rows for 全員 / 従業員カテゴリ / 個人 with the abilities the
 * space's members get in its apps, AppAccess). An app may be placed in several spaces (of this dashboard and
 * others), so every app can be added.
 */
final class SpaceService
{
    /**
     * `permissions` replaces the space's permission rows (left out: kept; a new space has none). Each row:
     * `subject_type` (PermissionSubject), `employee_category_id` / `user_id` for 従業員カテゴリ / 個人 and the `can_*`
     * abilities (AppAbility::columns(); without 閲覧 nothing). One 全員 row, each 従業員カテゴリ and member once, and a
     * 個人 row only for a member of the space.
     *
     * @param  array{name: string, user_ids?: list<int|string>, app_ids?: list<int|string>, permissions?: list<array<string, mixed>>}  $data
     */
    public function save(?Space $space, string $panelId, array $data, User $actor): Space
    {
        if (! $actor->user_type->isAdministrator()) {
            throw new AppActionNotAllowed('スペースを管理できるのは管理者のみです。');
        }

        $userIds = array_values(array_unique(array_map('intval', $data['user_ids'] ?? [])));
        $rows = array_key_exists('permissions', $data) ? $this->permissionRows($data['permissions'], $userIds) : null;

        return DB::transaction(function () use ($space, $panelId, $data, $userIds, $rows): Space {
            $space ??= new Space(['panel_id' => $panelId, 'sort_order' => ((int) Space::query()->where('panel_id', $panelId)->max('sort_order')) + 1]);
            $space->fill(['name' => $data['name']])->save();

            $space->users()->sync($userIds);
            $this->syncApps($space, array_map('intval', $data['app_ids'] ?? []));

            if ($rows !== null) {
                $space->permissions()->delete();
                foreach ($rows as $row) {
                    $space->permissions()->create($row);
                }
            } else {
                self::dropRowsOfFormerMembers($space->id);
            }
            Space::forgetCached();

            return $space;
        });
    }

    /**
     * Removes the 個人 rows of users who are no longer members (of these spaces, or of any space).
     *
     * @param  int|list<int>|null  $spaceIds
     */
    public static function dropRowsOfFormerMembers(int|array|null $spaceIds = null, ?int $userId = null): void
    {
        SpacePermission::query()
            ->where('subject_type', PermissionSubject::User->value)
            ->when($spaceIds !== null, fn ($query) => $query->whereIn('space_id', (array) $spaceIds))
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('space_user')
                ->whereColumn('space_user.space_id', 'space_permissions.space_id')
                ->whereColumn('space_user.user_id', 'space_permissions.user_id'))
            ->delete();
    }

    /**
     * Checks and normalizes the rows of a space's permission setting.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<int>  $memberIds
     * @return list<array<string, mixed>>
     */
    private function permissionRows(array $rows, array $memberIds): array
    {
        $normalized = [];
        $everyone = 0;
        $categories = [];
        $users = [];

        foreach ($rows as $row) {
            $subject = PermissionSubject::tryFrom((string) ($row['subject_type'] ?? ''))
                ?? throw new AppActionNotAllowed('権限の対象を選択してください。');
            $categoryId = $subject === PermissionSubject::Category ? (int) ($row['employee_category_id'] ?? 0) : null;
            $userId = $subject === PermissionSubject::User ? (int) ($row['user_id'] ?? 0) : null;

            match ($subject) {
                PermissionSubject::Everyone => $everyone++,
                PermissionSubject::Category => $categories[] = $categoryId,
                PermissionSubject::User => $users[] = $userId,
            };

            if ($categoryId !== null && ! EmployeeCategory::query()->whereKey($categoryId)->exists()) {
                throw new AppActionNotAllowed('従業員カテゴリを選択してください。');
            }
            if ($userId !== null && ! in_array($userId, $memberIds, true)) {
                throw new AppActionNotAllowed('個人の権限はこのスペースのメンバーにのみ設定できます。');
            }

            $abilities = [];
            foreach (AppAbility::columns() as $column) {
                $abilities[$column] = (bool) ($row[$column] ?? false);
            }

            $normalized[] = [
                'subject_type' => $subject,
                'employee_category_id' => $categoryId,
                'user_id' => $userId,
                ...$abilities,
            ];
        }

        if ($everyone > 1) {
            throw new AppActionNotAllowed('「全員」の権限は1つだけ設定できます。');
        }
        if (count($categories) !== count(array_unique($categories)) || count($users) !== count(array_unique($users))) {
            throw new AppActionNotAllowed('同じ対象の権限は1つだけ設定できます。');
        }

        return $normalized;
    }

    public function delete(Space $space, User $actor): void
    {
        if (! $actor->user_type->isAdministrator()) {
            throw new AppActionNotAllowed('スペースを管理できるのは管理者のみです。');
        }

        // Its apps leave it (the placements go with the space); they stay in their other spaces.
        $space->delete();
    }

    /** @param  list<int>  $appIds */
    private function syncApps(Space $space, array $appIds): void
    {
        $space->apps()->sync(BusinessApp::query()->whereIn('id', $appIds)->pluck('id')->all());
    }

    /**
     * Spaces of these dashboards a user can be made a member of (ユーザー管理), in the order of the dashboards and of
     * their spaces; with more than one dashboard the name says which: 「白石封筒：営業」.
     *
     * @param  list<string>  $panelIds
     * @return array<int, string> id => name
     */
    public static function spaceOptions(array $panelIds): array
    {
        $panelIds = array_values(array_unique($panelIds));
        if ($panelIds === []) {
            return [];
        }

        $labels = DashboardAccess::grantableOptions();
        $spaces = Space::query()
            ->whereIn('panel_id', $panelIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'panel_id', 'name'])
            ->sortBy(fn (Space $space): int => (int) array_search($space->panel_id, $panelIds, true), SORT_NUMERIC);
        $several = $spaces->pluck('panel_id')->unique()->count() > 1;

        return $spaces
            ->mapWithKeys(fn (Space $space): array => [
                $space->id => ($several ? ($labels[$space->panel_id] ?? $space->panel_id).'：' : '').$space->name,
            ])
            ->all();
    }

    /**
     * Apps that can be placed in the space: every app (an app may be in several spaces).
     *
     * @return array<int, string> id => "name (code)"
     */
    public static function placeableApps(?Space $space): array
    {
        return BusinessApp::query()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(fn (BusinessApp $app): array => [$app->id => "{$app->name}（{$app->code}）"])
            ->all();
    }
}
