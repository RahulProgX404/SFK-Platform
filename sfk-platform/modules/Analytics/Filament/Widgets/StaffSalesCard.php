<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Filament\Dashboard\DashboardCard;
use Modules\Analytics\Filament\Pages\SalesAnalysis;

/**
 * 担当者別売上 (row 3 of 全体, bug.md 2026-11-11; design_ref sales-by-person.png, Customers): the 担当者 of our company with their number of sales
 * and amount in the period; → leads to 分析 → 売上分析. 個人 (bug.md 2026-11-06): the user's own newest sales instead
 * (担当した売上), each leading to its record.
 */
final class StaffSalesCard extends DashboardCard
{
    protected static ?int $sort = 7;

    protected string $view = 'analytics::widgets.staff-sales';

    public static function canView(): bool
    {
        return self::showsMoney() && self::seesEveryonesMoney();
    }

    /** Not in the 個人 layout (bug.md 2026-11-09). */
    protected static function shownInLayout(bool $personal): bool
    {
        return ! $personal;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $service = $this->salesData();

        return [
            // 個人: the user's own newest sales in place of the ranking of everyone
            'staff' => $service->isPersonal() ? [] : $service->topStaff($this->period()),
            'latest' => $service->isPersonal() ? $service->latestSales($this->period()) : null,
            // 構成比 of the tooltips: the period's 売上合計 (from the cache, as 売上合計)
            'whole' => $service->isPersonal() ? 0 : $service->totals($this->period())['amount'],
            'analysisUrl' => SalesAnalysis::canAccess() ? SalesAnalysis::getUrl() : null,
        ];
    }
}
