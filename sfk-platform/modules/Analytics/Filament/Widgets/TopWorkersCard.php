<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Application\DailyReportRanking;
use Modules\Analytics\Filament\Dashboard\DashboardCard;
use Modules\Analytics\Filament\Pages\DailyReportAnalysis;

/**
 * 作業者ランキング (row 3, every user; design of revenue-category.png, bug.md 2026-11-06): the five 作業者 who made the
 * most in the period (枚数単価 + 型替え単価 of 08. 日報入力), each with their share of what all 作業者 made and their hours
 * worked — the user's own row framed; → leads to 分析 → 日報分析. Shown to users who see everyone's money only.
 */
final class TopWorkersCard extends DashboardCard
{
    protected static ?int $sort = 7;

    protected string $view = 'analytics::widgets.top-workers';

    /** Only for users who see everyone's money (財務分析の範囲 全体; bug.md 2026-11-07). */
    public static function canView(): bool
    {
        return self::seesEveryonesMoney();
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
