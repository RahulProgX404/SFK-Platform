<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Filament\Dashboard\DashboardCard;
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

        return [
            'amount' => $now['amount'],
            'change' => self::change($now['amount'], $before['amount']),
            'target' => $target,
            // 個人: the 担当者's own 予想 (売上分析) takes the place of the 売上目標
            'targetLabel' => $service->isPersonal() ? '売上予想' : '売上目標',
            'reached' => $target !== null && $target > 0 ? (int) floor($now['amount'] / $target * 100) : null,
            'detailUrl' => SalesAnalysis::canAccess() ? SalesAnalysis::getUrl() : null,
        ];
    }
}
