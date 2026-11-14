<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Application\ActivityFeed;
use Modules\Analytics\Filament\Dashboard\DashboardCard;

/**
 * 最近のアクティビティ (every user; design_ref quick-actions.png, Recent Activity): only what concerns the user — their
 * 通知 and what they did themselves (ActivityFeed), the 10 latest. In the 個人 and 全体 layouts (bug.md 2026-11-09 /
 * 2026-11-11) half of the row next to 休暇・勤怠予定; without 財務分析を表示 (user 2026-11-14) a third of the row after
 * 最近表示した項目 and 休暇・勤怠予定 (DashboardCard::teamRowOfThree) — one column in both; else the whole width in two.
 */
final class RecentActivityCard extends DashboardCard
{
    protected static ?int $sort = 11;

    /** the latest activities shown (user 2026-11-14: only the 10 latest) */
    public const LIMIT = 10;

    protected string $view = 'analytics::widgets.recent-activity';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = self::actor();

        return [
            'items' => $user !== null ? app(ActivityFeed::class)->for($user, self::LIMIT) : [],
            'span' => match (true) {
                self::teamRowOfThree() => 4,
                $this->inPersonalLayout() || $this->inOverallLayout() => 6,
                default => 12,
            },
        ];
    }
}
