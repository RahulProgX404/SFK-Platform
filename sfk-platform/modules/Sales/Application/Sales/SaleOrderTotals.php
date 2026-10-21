<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Sales;

use Modules\Orders\Models\Order;
use Modules\Sales\Models\Sale;

/**
 * 売上済数量 / 現在売上済金額 of a job = the sums of its sales (legacy Customine 06 page 25 on save, page 26 on delete;
 * recalculated from all sales, never incremented). The job then recalculates 売上残数量 and its profit (JobCosting).
 */
final class SaleOrderTotals
{
    public function refresh(?int $orderId): void
    {
        $order = $orderId === null ? null : Order::query()->find($orderId);
        if ($order === null) {
            return;
        }

        $sales = Sale::query()->where('order_id', $order->id);
        $order->forceFill([
            'sold_quantity' => (string) ($sales->clone()->sum('quantity') ?: '0'),
            'sold_amount' => (int) $sales->clone()->sum('amount'),
        ]);
        $order->refreshTotals();
    }

    /**
     * Figures of the job without the given sale, for the save checks (SaleChecks).
     *
     * @return array{remaining: string, sold_amount: int, planned_amount: int}
     */
    public function withoutSale(Order $order, ?int $saleId): array
    {
        $others = Sale::query()->where('order_id', $order->id)->when($saleId !== null, fn ($query) => $query->whereKeyNot($saleId));
        $sold = (string) ($others->clone()->sum('quantity') ?: '0');

        return [
            'remaining' => bcsub(number_format((float) ($order->quantity ?? 0), 4, '.', ''), number_format((float) $sold, 4, '.', ''), 4),
            'sold_amount' => (int) $others->clone()->sum('amount'),
            'planned_amount' => (int) $order->sales_amount,
        ];
    }
}
