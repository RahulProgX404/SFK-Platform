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
 * document (納品書 / 請求書) one of them is already on: the rest of them are added to it. Refused, as in the legacy script:
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
     * The sales of the group not yet a row of the document.
     *
     * @param  Collection<int, Sale>  $sales
     * @return Collection<int, Sale>
     */
    public static function missingRows(DeliveryNote|Invoice $document, Collection $sales): Collection
    {
        $onDocument = $document->lines()->pluck('sale_id')->filter()->map(fn (mixed $id): int => (int) $id)->all();

        return $sales->reject(fn (Sale $sale): bool => in_array($sale->id, $onDocument, true))->values();
    }
}
