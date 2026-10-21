<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Documents;

use Illuminate\Support\Facades\DB;
use Modules\Sales\Application\SalesActionNotAllowed;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\Receipt;
use Modules\Sales\Models\ReceivableMonth;
use Modules\Sales\Models\Sale;

/**
 * 納品レコード更新 / 請求レコード更新 of a sale of 06. 売上入力 (legacy Customine 06 pages 14 / 15, detail buttons): after a
 * sale changed, its row on its delivery note / invoice is written again from the sale (the documents are not updated on
 * save, BR-SA-15) and the document's totals follow. 23 / 23-2 need no extra step: they are recalculated from the sales and
 * receipts on every save (the legacy pages updated 23 by hand, and page 15 compared a literal key so its 入金額 stayed 0).
 *
 * - A sale without a document is refused (legacy warning 「…が作成されていません。」).
 * - A sale whose row is no longer on the document (it was removed there) is unlinked — 「…未作成として更新しました」.
 * - 請求レコード更新 also takes 前月御請求額 / ご入金額 again from the customer's 23-2 month like the invoice creation
 *   (BR-SA-12; the legacy page used the calendar month before 請求日付 with a mismatched customer key), and refreshes the
 *   invoice's copy in 21. 売入金管理 (legacy 「ルックアップを取得しなおす」).
 */
final class SaleDocumentRefresh
{
    public const UPDATED = 'updated';

    public const UNLINKED = 'unlinked';

    /** @return self::UPDATED|self::UNLINKED */
    public function deliveryNote(Sale $sale): string
    {
        $note = $sale->deliveryNote;
        if ($note === null) {
            throw new SalesActionNotAllowed('この売上データの納品レコードが作成されていません。先に納品レコードの作成を行ってください。');
        }

        return DB::transaction(function () use ($sale, $note): string {
            $line = $note->lines()->where('sale_id', $sale->id)->first();
            if ($line === null) {
                $sale->forceFill(['delivery_note_id' => null])->save();

                return self::UNLINKED;
            }

            $line->fill(DeliveryNoteIssuer::lineOf($sale->loadMissing('order')))->save();
            $note->refreshTotals();

            return self::UPDATED;
        });
    }

    /** @return self::UPDATED|self::UNLINKED */
    public function invoice(Sale $sale): string
    {
        $invoice = $sale->invoice;
        if ($invoice === null) {
            throw new SalesActionNotAllowed('この売上データの請求レコードが作成されていません。先に請求レコードの作成を行ってください。');
        }

        return DB::transaction(function () use ($sale, $invoice): string {
            $line = $invoice->lines()->where('sale_id', $sale->id)->first();
            if ($line === null) {
                $sale->forceFill(['invoice_id' => null])->save();

                return self::UNLINKED;
            }

            $line->fill([...DeliveryNoteIssuer::lineOf($sale->loadMissing(['order', 'deliveryNote'])), 'slip_number' => $sale->deliveryNote?->number])->save();

            if ($invoice->closing_day !== null && ! $sale->isAdHoc()) {
                $balance = ReceivableMonth::query()->ofBasis(ReceivableBasis::Cycle)->where('customer_id', $invoice->customer_id)
                    ->where('year_month', $sale->billing_month)->first();
                $invoice->forceFill(['previous_amount' => $balance->previous_balance ?? 0, 'received_amount' => $balance->received ?? 0])->save();
            }
            $invoice->refreshTotals();
            $this->refreshReceipt($invoice->fresh() ?? $invoice);

            return self::UPDATED;
        });
    }

    /** 21. 売入金管理: the invoice's 請求日 / 請求金額 / 今回締日 copied again. */
    private function refreshReceipt(Invoice $invoice): void
    {
        $receipt = Receipt::query()->where('invoice_id', $invoice->id)->first();
        if ($receipt === null) {
            return;
        }

        $receipt->forceFill([
            'billed_on' => $invoice->invoiced_on,
            'billed_amount' => $invoice->purchaseTotal(),
            'closing_day' => $invoice->closing_day,
        ]);
        if ($receipt->isDirty()) {
            $receipt->save();
        }
    }
}
