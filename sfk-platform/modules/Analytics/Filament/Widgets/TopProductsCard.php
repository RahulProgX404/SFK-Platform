<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Domain\ShortYen;
use Modules\Analytics\Filament\Dashboard\DashboardCard;

/** 売れ筋 TOP3 (row 2; design_ref top-3-products.png): the three 品名 with the most sales in the period, as bars. */
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
        $axis = ShortYen::axis($products === [] ? 0 : max(array_column($products, 'amount')), 5);

        return [
            'products' => $products,
            'axis' => $axis,
            'top' => max(1, $axis[0]),
            'salesUrl' => self::appUrl('sales-entry'),
        ];
    }
}
