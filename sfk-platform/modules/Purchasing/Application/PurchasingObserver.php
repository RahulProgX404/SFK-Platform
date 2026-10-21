<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application;

use Illuminate\Database\Eloquent\Model;
use Modules\Purchasing\Application\Payables\PayableLedger;
use Modules\Purchasing\Application\Purchases\PurchaseOrderTotals;
use Modules\Purchasing\Models\PayableMonth;
use Modules\Purchasing\Models\Payment;
use Modules\Purchasing\Models\PaymentLine;
use Modules\Purchasing\Models\Purchase;

/**
 * Keeps what is derived from the purchasing records in line after every save and delete (registered in
 * PurchasingServiceProvider): the job's 仕入 costs (PurchaseOrderTotals) and the supplier's payables (PayableLedger) —
 * for the old job / supplier too when they changed —, 支払金額合計 of a payment after its rows changed. A hand-entered
 * 消費税端数調整 recalculates the payables from that month on.
 */
final class PurchasingObserver
{
    public function __construct(private readonly PurchaseOrderTotals $orderTotals, private readonly PayableLedger $ledger) {}

    public function saved(Model $model): void
    {
        $this->changed($model);
    }

    public function deleted(Model $model): void
    {
        $this->changed($model);
    }

    private function changed(Model $model): void
    {
        match (true) {
            $model instanceof Purchase => $this->purchaseChanged($model),
            $model instanceof PaymentLine => $this->paymentLineChanged($model),
            $model instanceof PayableMonth => $model->wasChanged('tax_adjustment') || ! $model->exists ? $this->ledger->refresh($model->supplier_id) : null,
            default => null,
        };
    }

    private function purchaseChanged(Purchase $purchase): void
    {
        foreach (array_unique(array_filter([$purchase->order_id, $purchase->getOriginal('order_id')])) as $orderId) {
            $this->orderTotals->refresh((int) $orderId);
        }
        foreach (array_unique(array_filter([$purchase->supplier_id, $purchase->getOriginal('supplier_id')])) as $supplierId) {
            $this->ledger->refresh((int) $supplierId);
        }
    }

    private function paymentLineChanged(PaymentLine $line): void
    {
        Payment::query()->find($line->payment_id)?->refreshTotals();
        foreach (array_unique(array_filter([$line->supplier_id, $line->getOriginal('supplier_id')])) as $supplierId) {
            $this->ledger->refresh((int) $supplierId);
        }
    }
}
