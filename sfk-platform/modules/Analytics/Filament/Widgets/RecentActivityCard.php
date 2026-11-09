<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Application\ActivityFeed;
use Modules\Analytics\Filament\Dashboard\DashboardCard;

/**
 * 最近のアクティビティ (row 4, every user, the whole width; design_ref quick-actions.png, Recent Activity): only what
 * concerns the user — their 通知 and what they did themselves (ActivityFeed). In the 個人 layout (bug.md 2026-11-09) half
 * of row 3, next to 休暇・勤怠予定, as one column.
 */
final class RecentActivityCard extends DashboardCard
{
    protected static ?int $sort = 10;

    protected string $view = 'analytics::widgets.recent-activity';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = self::actor();

        return [
            'items' => $user !== null ? app(ActivityFeed::class)->for($user, 10) : [],
            'half' => $this->inPersonalLayout(),
        ];
    }
}
