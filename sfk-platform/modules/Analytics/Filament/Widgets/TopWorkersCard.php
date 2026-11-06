<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Application\DailyReportRanking;
use Modules\Analytics\Filament\Dashboard\DashboardCard;
use Modules\Analytics\Filament\Pages\DailyReportAnalysis;

/**
 * 作業者ランキング (row 3, every user; design of revenue-category.png, bug.md 2026-11-06): the five 作業者 who made the
 * most in the period (枚数単価 + 型替え単価 of 08. 日報入力), each with their share of what all 作業者 made and their hours
 * worked — the user's own row framed; → leads to 分析 → 日報分析.
 */
final class TopWorkersCard extends DashboardCard
{
    protected static ?int $sort = 7;

    protected string $view = 'analytics::widgets.top-workers';

    /** Row 3: first — last for users without 財務分析を表示 (クイックアクション first for them; bug.md 2026-11-06). */
    public static function getSort(): int
    {
        return (self::actor()?->seesFinancialAnalysis() ?? false) ? 7 : 9;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $period = $this->period();
        $everyone = app(DailyReportRanking::class)->top($period->start(), $period->after(), PHP_INT_MAX);
        $total = array_sum(array_column($everyone, 'total'));

        return [
            'workers' => array_map(fn (array $worker): array => [
                ...$worker,
                'share' => $total > 0 ? (int) round($worker['total'] / $total * 100) : 0,
            ], array_slice($everyone, 0, 5)),
            'analysisUrl' => DailyReportAnalysis::canAccess() ? DailyReportAnalysis::getUrl() : null,
            'me' => self::actor()?->id,
        ];
    }
}
