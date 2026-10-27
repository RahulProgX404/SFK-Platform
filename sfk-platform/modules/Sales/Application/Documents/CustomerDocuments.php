<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Documents;

use Illuminate\Database\Eloquent\Collection;
use Modules\Sales\Application\SalesActionNotAllowed;
use Modules\Sales\Models\DeliveryNote;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\Sale;

/**
 * The selected sales of the 【新】 buttons of 06_SalesEntry.bundle.js by customer, each customer's sales with the
 * document (納品書 / 請求書) one of them is already on: the rest of them are added to it, the rows of those already on it
 * written again from the sales (syncRows()). Refused, as in the legacy script:
 * a customer's sales on different documents, and one document for several customers.
 */
final class CustomerDocuments
{
    /**
     * @param  Collection<int, Sale>  $sales  with customer and the document relation loaded
     * @param  'deliveryNote'|'invoice'  $relation
     * @return list<array{sales: Collection<int, Sale>, document: DeliveryNote|Invoice|null}>
     */
    public static function of(Collection $sales, string $relation): array
    {
        $label = $relation === 'deliveryNote' ? '納品書番号' : '請求書番号';
        $groups = [];
        $owners = [];

        foreach ($sales->groupBy('customer_id') as $group) {
            /** @var Sale $first */
            $first = $group->first();
            $customer = $first->customer->name;
            /** @var Collection<int, DeliveryNote|Invoice> $documents */
            $documents = $group->map(fn (Sale $sale): DeliveryNote|Invoice|null => $sale->getRelation($relation))->filter()->unique('id')->sortBy('number')->values();

            if ($documents->count() > 1) {
                throw new SalesActionNotAllowed('売上先「'.$customer.'」のレコードに、異なる「'.$label.'」が混在しています：'
                    .$documents->pluck('number')->implode('、').'。同じ売上先では同じ'.$label.'のレコードのみ選択してください。');
            }

            $document = $documents->first();
            if ($document !== null) {
                $owner = $owners[$document->id] ?? $document->customer?->name;
                if (isset($owners[$document->id]) || ($document->customer_id !== null && $document->customer_id !== $first->customer_id)) {
                    throw new SalesActionNotAllowed($label.'「'.$document->number.'」が複数の売上先に設定されています：'.$owner.'、'.$customer);
                }
                $owners[$document->id] = $customer;
            }

            $groups[] = ['sales' => $group->sortBy([['delivery_due_on', 'asc'], ['sold_on', 'asc'], ['id', 'asc']])->values(), 'document' => $document];
        }

        return $groups;
    }

    /**
     * The group's sales onto the document (syncInvoiceRows of 06_SalesEntry): a sale's row already there is written again
     * from the sale where any column differs — it keeps its id and place, rows of other sales and rows typed by hand stay as
     * they are — and the sales not yet on it are added at the end. Nothing is saved when nothing differs or is new.
     *
     * @param  Collection<int, Sale>  $sales
     * @param  callable(Sale): array<string, mixed>  $rowOf
     * @return array{updated: int, appended: int}
     */
    public static function syncRows(DeliveryNote|Invoice $document, Collection $sales, callable $rowOf): array
    {
        $rows = $document->lines()->whereIn('sale_id', $sales->modelKeys())->get()->keyBy('sale_id');
        $sort = (int) $document->lines()->max('sort');
        $updated = 0;
        $appended = 0;

        foreach ($sales as $sale) {
            $row = $rows->get($sale->id);
            if ($row === null) {
                $document->lines()->create([...$rowOf($sale), 'sort' => ++$sort]);
                $appended++;
            } elseif ($row->fill($rowOf($sale))->isDirty()) {
                $row->save();
                $updated++;
            }
        }

        if ($updated + $appended > 0) {
            $document->refreshTotals();
        }

        return ['updated' => $updated, 'appended' => $appended];
    }
}
