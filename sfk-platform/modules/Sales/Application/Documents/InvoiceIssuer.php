<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Documents;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Billing\BillingCycle;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Sales\Application\SalesActionNotAllowed;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\Receipt;
use Modules\Sales\Models\ReceivableMonth;
use Modules\Sales\Models\Sale;

/**
 * Creates 請求書 from delivered sales (BR-SA-09; legacy Customine 06 pages 9 / 13, and 【新】選択して請求書レコード作成 of
 * 06_SalesEntry.bundle.js — issueOrAppend()): one statement invoice per customer, its rows the customer's sales in order of 納品予定日
 * (日付 = 納品予定日, 伝票No = 納品書番号), numbered from the invoice sequence.
 *
 * - issue(): only sales already on a delivery note, not yet invoiced and not 随時請求. 請求日付 is the given date, else by
 *   the closing day of the first sale (BR-SA-10: 5〜25日 → that day of this month, 末日 → last day of the previous
 *   month, else today). 今回締日 = that closing day. 前月御請求額 / ご入金額 come from the customer's cycle receivable of
 *   the sales' 月度 (BR-SA-12: 前月売掛残 / 当月入金額); 繰越金額 follows (BR-SA-11).
 * - issueAdHoc(): only 随時請求 sales; 請求日付 = 請求日付 of the first sale, 今回締日 「随時請求」.
 *
 * Each invoice gets its record in 21. 売入金管理 (BR-SA-13), 請求先 by the customer's 請求書送付先 (BR-MD-06), 担当者 of the
 * first sale; the sales are marked 請求書作成 済 (linked).
 */
final class InvoiceIssuer
{
    /**
     * @param  iterable<Sale>  $sales
     * @return list<Invoice>
     */
    public function issue(iterable $sales, ?CarbonImmutable $invoiceDate = null, ?CarbonImmutable $today = null): array
    {
        $sales = $this->checked($sales);
        $adHoc = $sales->filter(fn (Sale $sale): bool => $sale->isAdHoc());
        if ($adHoc->isNotEmpty()) {
            throw new SalesActionNotAllowed('随時請求の売上が含まれています（売上No: '.$adHoc->pluck('sales_number')->implode('、').'）。随時請求の請求書作成から作成してください。');
        }

        $today ??= CarbonImmutable::today();

        return $this->create($sales, fn (Sale $first): array => [
            'invoiced_on' => $invoiceDate ?? BillingCycle::defaultInvoiceDate($first->effectiveClosingDay(), $today),
            'closing_day' => $first->effectiveClosingDay()?->value,
        ], withBalances: true);
    }

    /**
     * 【新】選択して請求書レコード作成 (06_SalesEntry.bundle.js): every sale must be on a delivery note and none 随時請求;
     * per customer, the sales go on the invoice one of them is already on (the rows missing there added at the end), else
     * on a new invoice as issue() makes it (CustomerDocuments).
     *
     * @param  iterable<Sale>  $sales
     * @return list<Invoice> the invoices created or added to
     */
    public function issueOrAppend(iterable $sales, ?CarbonImmutable $invoiceDate = null, ?CarbonImmutable $today = null): array
    {
        $sales = new Collection(is_array($sales) ? $sales : iterator_to_array($sales));
        if ($sales->isEmpty()) {
            throw new SalesActionNotAllowed('売上レコードを選択してください。');
        }

        $undelivered = $sales->filter(fn (Sale $sale): bool => $sale->delivery_note_id === null);
        if ($undelivered->isNotEmpty()) {
            throw new SalesActionNotAllowed('納品書番号が未設定のレコードが '.$undelivered->count().' 件あります。請求書を作成するには、すべての選択レコードに納品書番号が必要です。（未設定の売上No: '
                .$undelivered->pluck('sales_number')->implode('、').'）');
        }

        $adHoc = $sales->filter(fn (Sale $sale): bool => $sale->isAdHoc());
        if ($adHoc->isNotEmpty()) {
            throw new SalesActionNotAllowed('随時請求の売上レコードが選択されています。随時請求分は別途ボタンから実行してください。（随時請求の売上No: '
                .$adHoc->pluck('sales_number')->implode('、').'）');
        }

        $groups = CustomerDocuments::of($sales->load(['customer', 'assignees', 'order', 'deliveryNote', 'invoice']), 'invoice');
        $today ??= CarbonImmutable::today();

        return DB::transaction(fn (): array => array_map(function (array $group) use ($invoiceDate, $today): Invoice {
            $invoice = $group['document'];
            if (! $invoice instanceof Invoice) {
                /** @var Sale $first */
                $first = $group['sales']->first();

                return $this->invoiceFor($group['sales'], [
                    'invoiced_on' => $invoiceDate ?? BillingCycle::defaultInvoiceDate($first->effectiveClosingDay(), $today),
                    'closing_day' => $first->effectiveClosingDay()?->value,
                ], withBalances: true);
            }

            $sort = (int) $invoice->lines()->max('sort');
            foreach (CustomerDocuments::missingRows($invoice, $group['sales']) as $sale) {
                $invoice->lines()->create([...DeliveryNoteIssuer::lineOf($sale), 'slip_number' => $sale->deliveryNote?->number, 'sort' => ++$sort]);
            }
            $invoice->refreshTotals();
            Sale::query()->whereKey($group['sales']->pluck('id')->all())->update(['invoice_id' => $invoice->id]);

            return $invoice;
        }, $groups));
    }

    /**
     * @param  iterable<Sale>  $sales
     * @return list<Invoice>
     */
    public function issueAdHoc(iterable $sales): array
    {
        $sales = $this->checked($sales);
        $regular = $sales->reject(fn (Sale $sale): bool => $sale->isAdHoc());
        if ($regular->isNotEmpty()) {
            throw new SalesActionNotAllowed('随時請求ではない売上が含まれています（売上No: '.$regular->pluck('sales_number')->implode('、').'）。');
        }

        return $this->create($sales, fn (Sale $first): array => [
            'invoiced_on' => $first->billing_date ?? CarbonImmutable::today(),
            'closing_day' => ClosingDay::AdHoc->value,
        ], withBalances: false);
    }

    /**
     * @param  iterable<Sale>  $sales
     * @return Collection<int, Sale>
     */
    private function checked(iterable $sales): Collection
    {
        $sales = new Collection(is_array($sales) ? $sales : iterator_to_array($sales));
        if ($sales->isEmpty()) {
            throw new SalesActionNotAllowed('売上レコードを選択してください。');
        }

        $undelivered = $sales->filter(fn (Sale $sale): bool => $sale->delivery_note_id === null);
        if ($undelivered->isNotEmpty()) {
            throw new SalesActionNotAllowed('納品書が作成されていない売上が含まれています（売上No: '.$undelivered->pluck('sales_number')->implode('、').'）。');
        }

        $invoiced = $sales->filter(fn (Sale $sale): bool => $sale->invoice_id !== null);
        if ($invoiced->isNotEmpty()) {
            throw new SalesActionNotAllowed('すでに請求書が作成されている売上が含まれています（売上No: '.$invoiced->pluck('sales_number')->implode('、').'）。');
        }

        return $sales->load(['customer', 'assignees', 'order', 'deliveryNote']);
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @param  callable(Sale): array<string, mixed>  $header
     * @return list<Invoice>
     */
    private function create(Collection $sales, callable $header, bool $withBalances): array
    {
        return DB::transaction(fn (): array => $sales->groupBy('customer_id')
            ->map(function (Collection $group) use ($header, $withBalances): Invoice {
                $group = $group->sortBy([['delivery_due_on', 'asc'], ['sold_on', 'asc'], ['id', 'asc']])->values();

                return $this->invoiceFor($group, $header($group->first()), $withBalances);
            })
            ->values()
            ->all());
    }

    /**
     * @param  Collection<int, Sale>  $sales  one customer's, in row order
     * @param  array<string, mixed>  $header
     */
    private function invoiceFor(Collection $sales, array $header, bool $withBalances): Invoice
    {
        /** @var Sale $first */
        $first = $sales->first();
        $addressee = $first->customer->invoiceAddressee();
        $balance = $withBalances
            ? ReceivableMonth::query()->ofBasis(ReceivableBasis::Cycle)->where('customer_id', $first->customer_id)->where('year_month', $first->billing_month)->first()
            : null;

        $invoice = Invoice::query()->create([
            ...$header,
            'customer_id' => $first->customer_id,
            'customer_rounding' => $first->customer_rounding,
            'postal_code' => $addressee['postal_code'],
            'address' => $addressee['address'],
            'previous_amount' => $balance->previous_balance ?? 0,
            'received_amount' => $balance->received ?? 0,
        ]);
        $assignees = $first->assignees->pluck('id')->all();
        $invoice->assignees()->sync($assignees);

        foreach ($sales as $sort => $sale) {
            $invoice->lines()->create([
                ...DeliveryNoteIssuer::lineOf($sale),
                'slip_number' => $sale->deliveryNote?->number,
                'sort' => $sort,
            ]);
        }
        $invoice->refreshTotals();

        $receipt = Receipt::query()->create(['invoice_id' => $invoice->id, 'customer_id' => $invoice->customer_id]);
        $receipt->assignees()->sync($assignees);

        Sale::query()->whereKey($sales->pluck('id')->all())->update(['invoice_id' => $invoice->id]);

        return $invoice;
    }
}
