<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Documents;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Sales\Application\SalesActionNotAllowed;
use Modules\Sales\Models\DeliveryNote;
use Modules\Sales\Models\DeliveryNoteLine;
use Modules\Sales\Models\Sale;

/**
 * Creates 納品書 from sales (BR-SA-08; legacy Customine 06 pages 1 / 5 / 6, and 【新】選択して納品書レコード作成 of
 * 06_SalesEntry.bundle.js): one note per customer, its rows the customer's sales in order of 納品予定日 (納品日 =
 * 納品予定日, 区分 「売上」), numbered from the delivery-note sequence. The note takes 売上日付, 担当者, 締日, 端数区分 and the
 * address (郵便番号(P) / 住所(P), BR-SA-07) of the customer's first sale. The sales are marked 納品書作成 済 (linked).
 * issue() refuses sales already on a delivery note (the legacy re-creation pages are switched off); issueOrAppend() adds
 * them to that note instead.
 */
final class DeliveryNoteIssuer
{
    /**
     * @param  iterable<Sale>  $sales
     * @return list<DeliveryNote>
     */
    public function issue(iterable $sales): array
    {
        $sales = new Collection(is_array($sales) ? $sales : iterator_to_array($sales));
        if ($sales->isEmpty()) {
            throw new SalesActionNotAllowed('売上レコードを選択してください。');
        }

        $delivered = $sales->filter(fn (Sale $sale): bool => $sale->delivery_note_id !== null);
        if ($delivered->isNotEmpty()) {
            throw new SalesActionNotAllowed('すでに納品書が作成されている売上が含まれています（売上No: '.$delivered->pluck('sales_number')->implode('、').'）。');
        }

        return DB::transaction(fn (): array => $sales->load(['customer', 'assignees', 'order'])
            ->groupBy('customer_id')
            ->map(fn (Collection $group): DeliveryNote => $this->issueFor($group->sortBy([['delivery_due_on', 'asc'], ['sold_on', 'asc'], ['id', 'asc']])->values()))
            ->values()
            ->all());
    }

    /**
     * 【新】選択して納品書レコード作成 (06_SalesEntry.bundle.js): per customer, the sales go on the note one of them is
     * already on (the rows missing there added at the end), else on a new note (CustomerDocuments).
     *
     * @param  iterable<Sale>  $sales
     * @return list<DeliveryNote> the notes created or added to
     */
    public function issueOrAppend(iterable $sales): array
    {
        $sales = new Collection(is_array($sales) ? $sales : iterator_to_array($sales));
        if ($sales->isEmpty()) {
            throw new SalesActionNotAllowed('売上レコードを選択してください。');
        }

        $groups = CustomerDocuments::of($sales->load(['customer', 'assignees', 'order', 'deliveryNote']), 'deliveryNote');

        return DB::transaction(fn (): array => array_map(function (array $group): DeliveryNote {
            $note = $group['document'];
            if (! $note instanceof DeliveryNote) {
                return $this->issueFor($group['sales']);
            }

            $sort = (int) $note->lines()->max('sort');
            foreach (CustomerDocuments::missingRows($note, $group['sales']) as $sale) {
                $note->lines()->create([...self::lineOf($sale), 'sort' => ++$sort]);
            }
            $note->refreshTotals();
            Sale::query()->whereKey($group['sales']->pluck('id')->all())->update(['delivery_note_id' => $note->id]);

            return $note;
        }, $groups));
    }

    /**
     * Legacy list button 「納品書管理(売上)にレコード作成」 (page 1): the sales of a period (売上日), optionally of one
     * closing day, not yet on a delivery note.
     *
     * @return list<DeliveryNote>
     */
    public function issueForPeriod(CarbonImmutable $from, CarbonImmutable $to, ?ClosingDay $closingDay): array
    {
        $sales = Sale::query()
            ->whereNull('delivery_note_id')
            ->whereBetween('sold_on', [$from->toDateString(), $to->toDateString()])
            ->when($closingDay !== null, fn ($query) => $query->where('closing_day', $closingDay?->value))
            ->get();

        if ($sales->isEmpty()) {
            throw new SalesActionNotAllowed('対象の売上がありません。');
        }

        return $this->issue($sales);
    }

    /** @param  Collection<int, Sale>  $sales  one customer's, in row order */
    private function issueFor(Collection $sales): DeliveryNote
    {
        /** @var Sale $first */
        $first = $sales->first();
        $address = $first->deliveryAddress();

        $note = DeliveryNote::query()->create([
            'customer_id' => $first->customer_id,
            'customer_rounding' => $first->customer_rounding,
            'closing_day' => $first->closing_day,
            'sold_on' => $first->sold_on,
            'postal_code' => $address['postal_code'],
            'address' => $address['address'],
        ]);
        $note->assignees()->sync($first->assignees->pluck('id')->all());

        foreach ($sales as $sort => $sale) {
            $note->lines()->create([...self::lineOf($sale), 'sort' => $sort]);
        }
        $note->refreshTotals();

        Sale::query()->whereKey($sales->pluck('id')->all())->update(['delivery_note_id' => $note->id]);

        return $note;
    }

    /** @return array<string, mixed> a row copied from a sale */
    public static function lineOf(Sale $sale): array
    {
        return [
            'sale_id' => $sale->id,
            'line_date' => $sale->delivery_due_on,
            'sales_number' => $sale->sales_number,
            'is_fsc' => $sale->order?->is_fsc === true,
            'product_name' => $sale->product_name,
            'summary' => $sale->summary,
            'quantity' => $sale->quantity,
            'unit' => $sale->unit,
            'unit_price' => $sale->unit_price,
            'tax_rate' => $sale->tax_rate,
            'amount' => (int) $sale->amount,
            'order_number' => $sale->order?->order_number,
        ];
    }

    /** The kind of every row (legacy option 「売上」). */
    public static function lineKind(): string
    {
        return DeliveryNoteLine::KIND;
    }
}
