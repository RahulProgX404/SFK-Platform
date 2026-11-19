<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Sales;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Records\DeletionGuard;
use Modules\Sales\Application\Documents\SaleDocumentRefresh;
use Modules\Sales\Application\SalesActionNotAllowed;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\Sales\Filament\Resources\Invoices\InvoiceResource;
use Modules\Sales\Filament\Resources\Receipts\ReceiptResource;
use Modules\Sales\Models\DeliveryNote;
use Modules\Sales\Models\DeliveryNoteLine;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoiceLine;
use Modules\Sales\Models\Receipt;
use Modules\Sales\Models\ReceiptLine;
use Modules\Sales\Models\Sale;

/**
 * Deleting 売上 (06) with its documents (削除 of 06_SalesEntry.bundle.js; docs/receivables.md), as one transaction with the
 * sale (Core RecordDeletion; the list's bulk delete is one transaction too):
 *
 * - blocked when it would leave an invoice without rows whose 21. 売入金管理 record has any 入金 / 手数料 / 歩引き ≠ 0 (the
 *   invoice is named) — remove the payments first;
 * - its rows leave the 納品書 / 請求書; a document left without rows is deleted (an invoice with its 21 record), the others
 *   get their totals again and the invoice's 21 record its 請求金額;
 * - the job's totals and profit and the receivables follow by themselves (SalesObserver: SaleOrderTotals,
 *   ReceivableLedger) — the legacy delete did not touch 04 / 23.
 *
 * impact() adds these to the warning of the delete confirmations.
 */
final class SaleDeletion implements DeletionGuard
{
    public function blocker(Model $record): ?string
    {
        if (! $record instanceof Sale) {
            return null;
        }

        $paid = $this->paidInvoicesEmptiedBy([$record->id]);

        return $paid === [] ? null
            : '入金・手数料・歩引きのある請求書が削除されるため、この売上は削除できません（請求書番号: '.implode('、', $paid).'）。先に売入金管理の入金明細を削除してください。';
    }

    public function impact(array $keys): array
    {
        $ids = array_map('intval', $keys);
        $notes = $this->emptied(DeliveryNoteLine::class, 'delivery_note_id', $ids);
        $invoices = $this->emptied(InvoiceLine::class, 'invoice_id', $ids);
        $shrunk = InvoiceLine::query()->whereIn('sale_id', $ids)->whereNotIn('invoice_id', $invoices)->distinct()->count('invoice_id');
        $receipts = Receipt::query()->whereIn('invoice_id', $invoices)->count();
        $paid = $this->paidInvoicesEmptiedBy($ids);

        return array_values(array_filter([
            $notes === [] ? null : ['app' => DeliveryNoteResource::displayName(), 'text' => count($notes).'件の納品書が明細がなくなるため削除されます', 'blocks' => false],
            $invoices === [] ? null : ['app' => InvoiceResource::displayName(), 'text' => count($invoices).'件の請求書が明細がなくなるため削除されます', 'blocks' => false],
            $shrunk === 0 ? null : ['app' => InvoiceResource::displayName(), 'text' => $shrunk.'件の請求書の請求金額が変わります', 'blocks' => false],
            $receipts === 0 ? null : ['app' => ReceiptResource::displayName(), 'text' => $receipts.'件のレコードが請求書と一緒に削除されます', 'blocks' => false],
            $paid === [] ? null : ['app' => ReceiptResource::displayName(), 'text' => '入金のある請求書（'.implode('、', $paid).'）の売上は削除されません', 'blocks' => true],
        ]));
    }

    /** The cascade of a sale being deleted (SalesObserver::deleting), inside the delete's transaction. */
    public function cascade(Sale $sale): void
    {
        if (($reason = $this->blocker($sale)) !== null) {
            throw new SalesActionNotAllowed($reason);
        }

        DB::transaction(function () use ($sale): void {
            $noteIds = DeliveryNoteLine::query()->where('sale_id', $sale->id)->distinct()->pluck('delivery_note_id')->all();
            DeliveryNoteLine::query()->where('sale_id', $sale->id)->delete();
            foreach (DeliveryNote::query()->whereKey($noteIds)->withCount('lines')->get() as $note) {
                $note->getAttribute('lines_count') === 0 ? $note->delete() : $note->refreshTotals();
            }

            $invoiceIds = InvoiceLine::query()->where('sale_id', $sale->id)->distinct()->pluck('invoice_id')->all();
            InvoiceLine::query()->where('sale_id', $sale->id)->delete();
            foreach (Invoice::query()->whereKey($invoiceIds)->withCount('lines')->get() as $invoice) {
                if ($invoice->getAttribute('lines_count') === 0) {
                    Receipt::query()->where('invoice_id', $invoice->id)->get()->each(fn (Receipt $receipt): ?bool => $receipt->delete());
                    $invoice->delete();
                } else {
                    $invoice->refreshTotals();
                    SaleDocumentRefresh::refreshReceipt($invoice);
                }
            }
        });
    }

    /**
     * Numbers of the invoices the sales would leave without rows whose 21 record has a payment, fee or discount.
     *
     * @param  list<int>  $saleIds
     * @return list<string>
     */
    private function paidInvoicesEmptiedBy(array $saleIds): array
    {
        $emptied = $this->emptied(InvoiceLine::class, 'invoice_id', $saleIds);
        if ($emptied === []) {
            return [];
        }

        $paid = ReceiptLine::query()
            ->join('receipts', 'receipts.id', '=', 'receipt_lines.receipt_id')
            ->whereIn('receipts.invoice_id', $emptied)
            ->where(fn ($query) => $query->where('receipt_lines.amount', '<>', 0)->orWhere('receipt_lines.fee', '<>', 0)->orWhere('receipt_lines.discount', '<>', 0))
            ->distinct()->pluck('receipts.invoice_id');

        return Invoice::query()->whereKey($paid->all())->orderBy('number')->pluck('number')->map(fn (mixed $number): string => (string) $number)->all();
    }

    /**
     * The documents all of whose rows belong to the sales.
     *
     * @param  class-string<DeliveryNoteLine|InvoiceLine>  $lineModel
     * @param  list<int>  $saleIds
     * @return list<int>
     */
    private function emptied(string $lineModel, string $documentKey, array $saleIds): array
    {
        $documents = $lineModel::query()->whereIn('sale_id', $saleIds)->distinct()->pluck($documentKey)->all();
        if ($documents === []) {
            return [];
        }

        $kept = $lineModel::query()->whereIn($documentKey, $documents)
            ->where(fn ($query) => $query->whereNull('sale_id')->orWhereNotIn('sale_id', $saleIds))
            ->distinct()->pluck($documentKey)->all();

        return array_values(array_map('intval', array_diff($documents, $kept)));
    }
}
