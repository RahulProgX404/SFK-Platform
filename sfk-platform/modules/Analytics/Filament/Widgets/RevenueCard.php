<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Filament\Dashboard\DashboardCard;
use Modules\Analytics\Filament\Dashboard\DashboardTips;
use Modules\Analytics\Filament\Pages\SalesAnalysis;

/**
 * 売上合計 (row 1; design_ref Revenue.png, Total Revenue): the sales of the period, the change against the period before,
 * the 売上目標 of 25. 販売先別売上計画 with the share reached, and the way to 売上分析.
 */
final class RevenueCard extends DashboardCard
{
    protected static ?int $sort = 1;

    protected string $view = 'analytics::widgets.revenue';

    public static function canView(): bool
    {
        return self::showsMoney();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $service = $this->salesData();
        $period = $this->period();
        $now = $service->totals($period);
        $before = $service->totals($period->previous());
        $target = $service->target($period);
        $coverage = $this->coverage();
        $comparable = $coverage->covers($period) && $coverage->covers($period->previous());
        $change = self::change($now['amount'], $before['amount'], $comparable);
        $dataNote = $coverage->note($period) ?? $coverage->note($period->previous());
        $personal = $service->isPersonal();
        $targetLabel = $personal ? '売上予想' : '売上目標';
        $reached = $target !== null && $target > 0 ? (int) floor($now['amount'] / $target * 100) : null;

        return [
            'amount' => $now['amount'],
            'count' => $now['count'],
            // 前期間比: the same number of days just before (Period::previous); none without sales then, or when either
            // period reaches past the days the sales data covers (SalesCoverage — the legacy data ends 2026-10-16)
            'before' => $before['amount'],
            'difference' => $now['amount'] - $before['amount'],
            'previousLabel' => $period->previous()->shortLabel(),
            'comparable' => $comparable,
            'change' => $change,
            'dataNote' => $dataNote,
            'target' => $target,
            // 個人: the 担当者's own 予想 (売上分析) takes the place of the 売上目標
            'targetLabel' => $targetLabel,
            'reached' => $reached,
            // the tooltips of the figures (DashboardTips — from the values above, nothing more is read)
            'tips' => [
                'amount' => DashboardTips::salesAmount('売上合計', $personal),
                'count' => DashboardTips::salesCount($personal),
                'target' => DashboardTips::target($targetLabel, $personal),
                'compare' => DashboardTips::comparison('前期間', $period, $period->previous(), $now['amount'], $before['amount'], true),
            ],
            'detailUrl' => SalesAnalysis::canAccess() ? SalesAnalysis::getUrl() : null,
            // tablets: the row of its own in 個人 (no 入出金 to pair with)
            'personal' => $this->inPersonalLayout(),
        ];
    }
}
