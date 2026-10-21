<?php

declare(strict_types=1);

namespace Modules\Sales\Application;

use Illuminate\Database\Eloquent\Model;
use Modules\Sales\Application\Receivables\ReceivableLedger;
use Modules\Sales\Application\Sales\SaleOrderTotals;
use Modules\Sales\Models\DeliveryNoteLine;
use Modules\Sales\Models\InvoiceLine;
use Modules\Sales\Models\Receipt;
use Modules\Sales\Models\ReceiptLine;
use Modules\Sales\Models\ReceivableMonth;
use Modules\Sales\Models\Sale;

/**
 * Keeps what is derived from the sales records in line after every save and delete (registered in
 * SalesServiceProvider): the job's sales totals (SaleOrderTotals), the customer's receivables (ReceivableLedger) — for
 * the old job / customer too when they changed —, the totals of a document after its rows changed and 入金済 of a
 * receipt. A hand-entered 消費税端数調整 recalculates the receivables from that month on.
 */
final class SalesObserver
{
    public function __construct(private readonly SaleOrderTotals $orderTotals, private readonly ReceivableLedger $ledger) {}

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
            $model instanceof Sale => $this->saleChanged($model),
            $model instanceof DeliveryNoteLine => $model->deliveryNote()->first()?->refreshTotals(),
            $model instanceof InvoiceLine => $model->invoice()->first()?->refreshTotals(),
            $model instanceof ReceiptLine => $this->receiptChanged($model->receipt()->first()),
            $model instanceof Receipt => $this->receiptChanged($model),
            $model instanceof ReceivableMonth => $model->wasChanged('tax_adjustment') || ! $model->exists ? $this->ledger->refresh($model->customer_id) : null,
            default => null,
        };
    }

    private function saleChanged(Sale $sale): void
    {
        foreach (array_unique(array_filter([$sale->order_id, $sale->getOriginal('order_id')])) as $orderId) {
            $this->orderTotals->refresh((int) $orderId);
        }
        foreach (array_unique(array_filter([$sale->customer_id, $sale->getOriginal('customer_id')])) as $customerId) {
            $this->ledger->refresh((int) $customerId);
        }
    }

    private function receiptChanged(?Receipt $receipt): void
    {
        if ($receipt === null) {
            return;
        }

        if ($receipt->exists) {
            $receipt->refreshTotals();
        }
        foreach (array_unique(array_filter([$receipt->customer_id, $receipt->getOriginal('customer_id')])) as $customerId) {
            $this->ledger->refresh((int) $customerId);
        }
    }
}
