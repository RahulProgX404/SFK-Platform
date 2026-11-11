<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Filament\Dashboard\DashboardCard;

/** 売れ筋 TOP3 (row 2, 20%, bug.md 2026-11-11; design_ref top-3-products.png): the three 品名 with the most sales in the period, as horizontal bars. */
final class TopProductsCard extends DashboardCard
{
    protected static ?int $sort = 6;

    protected string $view = 'analytics::widgets.top-products';

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
        $products = $this->salesData()->topProducts($this->period());

        return [
            'products' => $products,
            // the bars against the first
            'top' => max(1, $products === [] ? 0 : max(array_column($products, 'amount'))),
            'salesUrl' => self::appUrl('sales-entry'),
        ];
    }
}
