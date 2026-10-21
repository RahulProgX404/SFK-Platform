<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Receivables;

use Illuminate\Database\Eloquent\Collection;
use Modules\Sales\Application\SalesActionNotAllowed;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\ReceivableMonth;

/**
 * 請求書更新 of 23-2. 売掛管理(締日別) (legacy Customine 23-2 page 9, BR-SA-12): the balances of a cycle month go into one
 * of the customer's invoices of that 月度 (締日売掛連携キー) — 前月御請求額 = 前月売掛残, ご入金額 = 当月入金額; 繰越金額
 * and 御請求額 follow on save (BR-SA-11).
 */
final class InvoiceBalances
{
    /** @return Collection<int, Invoice> the customer's invoices of the month, newest first */
    public function invoicesFor(ReceivableMonth $month): Collection
    {
        return Invoice::query()
            ->where('customer_id', $month->customer_id)
            ->where('billing_month', $month->year_month)
            ->orderByDesc('invoiced_on')
            ->orderByDesc('id')
            ->get();
    }

    public function apply(ReceivableMonth $month, Invoice $invoice): Invoice
    {
        if ($month->basis !== ReceivableBasis::Cycle) {
            throw new SalesActionNotAllowed('締日別の売掛レコードから更新してください。');
        }
        if ((int) $invoice->customer_id !== $month->customer_id || $invoice->billing_month !== $month->year_month) {
            throw new SalesActionNotAllowed('この売掛レコードの得意先・月度の請求書ではありません。');
        }

        $invoice->previous_amount = $month->previous_balance;
        $invoice->received_amount = $month->received;
        $invoice->save();

        return $invoice;
    }
}
