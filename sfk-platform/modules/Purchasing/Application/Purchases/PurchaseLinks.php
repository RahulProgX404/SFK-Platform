<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\Purchases;

use Illuminate\Support\Facades\DB;
use Modules\Purchasing\Application\Payables\PayableLedger;
use Modules\Purchasing\Models\Purchase;

/**
 * The legacy list buttons of 05. 納品(仕入)入力 (Customine 05 pages 6 / 8 / 11), run after a CSV import:
 *
 * - 納品(仕入)入力No発番: purchases without 納品(仕入)入力No get the next numbers, oldest first;
 * - 受注レコード更新: the 仕入 costs of every job with purchases recalculated (PurchaseOrderTotals);
 * - 買掛レコード更新: the payables of every supplier with purchases recalculated (PayableLedger).
 *
 * Purchases saved here are numbered and posted at once (PurchasingObserver), so the last two only catch up what was
 * changed outside the app; each returns how many records it went through.
 */
final class PurchaseLinks
{
    public function __construct(private readonly PurchaseOrderTotals $orders, private readonly PayableLedger $payables) {}

    public function numberUnnumbered(): int
    {
        return DB::transaction(function (): int {
            $purchases = Purchase::query()->where(fn ($query) => $query->whereNull('purchase_number')->orWhere('purchase_number', ''))->orderBy('id')->lockForUpdate()->get();
            foreach ($purchases as $purchase) {
                $purchase->assignNumber();
                $purchase->saveQuietly();
            }

            return $purchases->count();
        });
    }

    public function refreshOrders(): int
    {
        $orderIds = Purchase::query()->whereNotNull('order_id')->distinct()->pluck('order_id');
        foreach ($orderIds as $orderId) {
            $this->orders->refresh((int) $orderId);
        }

        return $orderIds->count();
    }

    public function refreshPayables(): int
    {
        $supplierIds = Purchase::query()->distinct()->pluck('supplier_id');
        foreach ($supplierIds as $supplierId) {
            $this->payables->refresh((int) $supplierId);
        }

        return $supplierIds->count();
    }
}
