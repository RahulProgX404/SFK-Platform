<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Application\SalesDashboard;
use Modules\Analytics\Domain\Period;
use Modules\Analytics\Filament\Dashboard\DashboardCard;
use Modules\Analytics\Filament\Dashboard\DashboardTips;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\Tip;

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
        $coverage = $this->coverage();
        $end = $coverage->end();
        $countComparable = $coverage->covers($period) && $coverage->covers($period->previous());
        $yearComparable = $coverage->covers($period) && $coverage->covers($period->lastYear());
        $countChange = self::change($overview['count'], $before['count'], $countComparable);
        $yearChange = self::change($overview['amount'], $lastYear['amount'], $yearComparable);
        $lastYearName = $this->lastYearLabel($period);
        $personal = $service->isPersonal();
        $share = fn (int $amount): int => $overview['amount'] > 0 ? (int) round($amount / $overview['amount'] * 100) : 0;
        $parts = $period->parts(count($overview['parts']));

        return [
            'period' => $period,
            'overview' => $overview,
            'labels' => SalesDashboard::STATUSES,
            // 前期間比 of 売上件数, 前年同期比 (前年度同期比 for 今年度) of 売上金額 — each with its baseline, and no
            // percentage when one of the periods reaches past the days the sales data covers (SalesCoverage)
            'countChange' => $countChange,
            'beforeCount' => $before['count'],
            'previousLabel' => $period->previous()->shortLabel(),
            'yearChange' => $yearChange,
            'lastYearAmount' => $lastYear['amount'],
            'lastYearName' => $lastYearName,
            'lastYearLabel' => $period->lastYear()->shortLabel(),
            'dataNote' => $coverage->note($period),
            // the parts of the period after the last day with sales data have no bar
            'partsWithoutData' => array_map(fn (array $part): bool => $end === null || $part[0] > $end->format('Y-m-d'), $parts),
            // the tooltips (DashboardTips — from the values above, nothing more is read)
            'tips' => [
                'amount' => DashboardTips::salesAmount('売上金額（税抜）', $personal),
                'count' => DashboardTips::salesCount($personal),
                'year' => DashboardTips::comparison($lastYearName, $period, $period->lastYear(), $overview['amount'], $lastYear['amount'], true),
                'countCompare' => DashboardTips::comparison('前期間', $period, $period->previous(), $overview['count'], $before['count'], false),
                'statuses' => array_map(fn (string $key): Tip => DashboardTips::status($key, SalesDashboard::STATUSES[$key]), array_combine(array_keys(SalesDashboard::STATUSES), array_keys(SalesDashboard::STATUSES))),
                'segments' => array_map(fn (string $key): Tip => Tip::text(SalesDashboard::STATUSES[$key].' '.AppFormat::yen($overview['statuses'][$key]['amount']).'（'.$share($overview['statuses'][$key]['amount']).'%）'), array_combine(array_keys(SalesDashboard::STATUSES), array_keys(SalesDashboard::STATUSES))),
                // the small bars: the days of each part and its number of sales
                'parts' => array_map(fn (array $part, int $count): Tip => Tip::text(Period::between($part[0], (new \DateTimeImmutable($part[1]))->modify('-1 day')->format('Y-m-d'), $this->today())->shortLabel().'：'.number_format($count).'件'), $parts, $overview['parts']),
            ],
            'salesUrl' => self::appUrl('sales-entry'),
            // 個人: the rest of row 1 next to 売上合計 (no 入出金, bug.md 2026-11-09); 全体: 4 + 5 + 3 with 入出金 (2026-11-11)
            'span' => $this->inPersonalLayout() ? 8 : 5,
        ];
    }
}
