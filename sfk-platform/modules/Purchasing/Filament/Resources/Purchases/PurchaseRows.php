<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Purchases;

use Illuminate\Support\Collection;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Purchasing\Domain\PurchasingFields;
use Modules\Purchasing\Models\Purchase;

/**
 * Purchases as a related-record list (core::filament.related-table): 仕入履歴 of a job (04), 同注番の仕入履歴 (05) and
 * 仕入情報詳細 (23. 買掛管理), each with the legacy columns of that list.
 */
final class PurchaseRows
{
    private const NUMERIC = ['unit_price', 'quantity', 'amount', 'cutting_amount', 'plate_amount', 'stored_plate_amount', 'spot_color_amount', 'total'];

    /**
     * @param  Collection<int, Purchase>  $purchases
     * @param  list<string>  $columns  attributes, labels as in 05
     * @param  class-string<PurchaseResource>  $resource
     * @return array{columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>}
     */
    public static function of(Collection $purchases, array $columns, string $resource): array
    {
        return [
            'columns' => array_combine($columns, array_map(fn (string $column): string => PurchasingFields::PURCHASE[$column], $columns)),
            'numeric' => array_values(array_intersect($columns, self::NUMERIC)),
            'rows' => $purchases->map(fn (Purchase $purchase): array => array_combine($columns, array_map(
                fn (string $column): string => self::value($purchase, $column),
                $columns,
            )))->values()->all(),
            'urls' => $purchases->map(fn (Purchase $purchase): ?string => $resource::canView($purchase) ? $resource::getUrl('view', ['record' => $purchase]) : null)
                ->values()->all(),
        ];
    }

    private static function value(Purchase $purchase, string $column): string
    {
        return match ($column) {
            'purchased_on' => AppFormat::date($purchase->purchased_on),
            'supplier_id' => $purchase->supplier->name,
            'unit_price' => AppFormat::price($purchase->unit_price),
            'quantity' => AppFormat::number($purchase->quantity),
            'amount', 'cutting_amount', 'plate_amount', 'stored_plate_amount', 'spot_color_amount', 'total' => AppFormat::yen($purchase->getAttribute($column)),
            'master_category' => (string) AppFormat::text($purchase->supplier->purchase_category),
            'effective_category' => (string) AppFormat::text($purchase->effectiveCategory()),
            default => (string) AppFormat::text($purchase->getAttribute($column)),
        };
    }
}
