<?php

declare(strict_types=1);

namespace Modules\Sales\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Sales\Models\Sale;

/**
 * A row of 納品内容詳細 / 請求内容詳細: a copy of a sale (snapshot — editing the sale does not change it, BR-SA-15)
 * or a row typed by hand. 月日 is 納品日 / 日付 as 「M/d」.
 *
 * @mixin Model
 *
 * @property int|null $sale_id
 * @property Carbon|null $line_date
 * @property string|null $sales_number
 * @property bool $is_fsc
 * @property string|null $product_name
 * @property string|null $summary
 * @property string|null $quantity
 * @property string|null $unit
 * @property string|null $unit_price
 * @property int $amount
 * @property string $tax_rate
 * @property string|null $order_number
 * @property-read Sale|null $sale
 */
trait IsDocumentLine
{
    /** A row typed without 小計 is saved with ¥0. */
    public static function bootIsDocumentLine(): void
    {
        static::saving(function (Model $line): void {
            $line->setAttribute('amount', (int) $line->getAttribute('amount'));
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'line_date' => 'date',
            'is_fsc' => 'boolean',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'amount' => 'integer',
        ];
    }

    /** 月日 */
    public function monthDay(): string
    {
        return $this->line_date?->format('n/j') ?? '';
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
