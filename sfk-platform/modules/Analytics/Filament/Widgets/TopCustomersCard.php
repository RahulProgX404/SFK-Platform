<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Filament\Dashboard\DashboardCard;

/** 上位の売上先 (row 2, 30%, bug.md 2026-11-11; design_ref top-customers.png): the five customers with the most sales in the period. */
final class TopCustomersCard extends DashboardCard
{
    protected static ?int $sort = 5;

    protected string $view = 'analytics::widgets.top-customers';

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
        return [
            'customers' => $this->salesData()->topCustomers($this->period()),
            // 構成比 of the tooltips: the period's 売上合計 (the figure 売上合計 has already added up, from the cache)
            'whole' => $this->salesData()->totals($this->period())['amount'],
            'salesUrl' => self::appUrl('sales-entry'),
        ];
    }
}
