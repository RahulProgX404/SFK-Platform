<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Portal;

/**
 * A card of the dashboard (PortalPage) whose place depends on the page filters (e.g. 全体 | 個人, `scope`): whether it is
 * shown and where, besides canView() (may the user see it at all) and getSort() (its place when it says nothing else).
 * Lets the business modules lay out their cards per filter without the page knowing them.
 */
interface FollowsDashboardFilters
{
    /** @param  array<string, mixed>  $filters  the page filters */
    public static function shownOnDashboard(array $filters): bool;

    /** @param  array<string, mixed>  $filters  the page filters */
    public static function sortOnDashboard(array $filters): int;
}
