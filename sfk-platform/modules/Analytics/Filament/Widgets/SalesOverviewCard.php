<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Application\SalesDashboard;
use Modules\Analytics\Filament\Dashboard\DashboardCard;

/**
 * 売上概要 (row 1; design_ref Revenue.png, Invoice Overview): the number of sales of the period against the period
 * before, how their amount stands (入金済 / 入金待ち / 期限超過 / 未請求) as a bar and in figures, the sales per part of
 * the period, and the amount against the same days a year earlier.
 */
final class SalesOverviewCard extends DashboardCard
{
    protected static ?int $sort = 2;

    protected string $view = 'analytics::widgets.sales-overview';

    public static function canView(): bool
    {
        return self::showsMoney();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $service = $this->salesData();
        $period = $this->period();
        $overview = $service->overview($period, $this->today());
        $before = $service->totals($period->previous());
        $lastYear = $service->totals($period->lastYear());

        return [
            'period' => $period,
            'overview' => $overview,
            'labels' => SalesDashboard::STATUSES,
            'countChange' => self::change($overview['count'], $before['count']),
            'yearChange' => self::change($overview['amount'], $lastYear['amount']),
            'salesUrl' => self::appUrl('sales-entry'),
        ];
    }
}
