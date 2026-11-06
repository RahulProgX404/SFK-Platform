<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Filament\Dashboard\DashboardCard;

/** 上位の売上先 (row 2; design_ref top-customers.png): the five customers with the most sales in the period. */
final class TopCustomersCard extends DashboardCard
{
    protected static ?int $sort = 4;

    protected string $view = 'analytics::widgets.top-customers';

    public static function canView(): bool
    {
        return self::showsMoney();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'customers' => $this->salesData()->topCustomers($this->period()),
            'salesUrl' => self::appUrl('sales-entry'),
        ];
    }
}
