<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\Purchases;

use Modules\Orders\Models\Order;
use Modules\Purchasing\Models\Purchase;

/**
 * 仕入金額 / 仕入断裁金額 / 仕入版代 / 仕入置き版代 / 仕入特色 of a job = the sums of its purchases (legacy Customine 05
 * page 9 on save, page 14 on delete, page 8 for CSV imports; recalculated from all purchases, never incremented). The job
 * then recalculates 仕入原価計 and its profit (JobCosting).
 */
final class PurchaseOrderTotals
{
    private const COLUMNS = [
        'amount' => 'purchase_amount',
        'cutting_amount' => 'purchase_cutting_amount',
        'plate_amount' => 'purchase_plate_amount',
        'stored_plate_amount' => 'purchase_stored_plate_amount',
        'spot_color_amount' => 'purchase_spot_color_amount',
    ];

    public function refresh(?int $orderId): void
    {
        $order = $orderId === null ? null : Order::query()->find($orderId);
        if ($order === null) {
            return;
        }

        $purchases = Purchase::query()->where('order_id', $order->id);
        foreach (self::COLUMNS as $purchase => $column) {
            $order->setAttribute($column, (int) $purchases->clone()->sum($purchase));
        }
        $order->refreshTotals();
    }
}
