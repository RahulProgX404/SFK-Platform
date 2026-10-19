<?php

declare(strict_types=1);

namespace Modules\Orders\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Order;

/**
 * A row of the tables 印刷 and トムソン・NO・シルク・箔 (same columns): supplier, 面付, 通し数, 予備, 色数, 刷色, 単価 and the
 * manual amount, which 入力方法 「手動」 switches on (BR-OR-04).
 *
 * @mixin Model
 */
trait IsProcessRow
{
    /** Columns of a row (in legacy order), as kept in the 変更履歴 of the order. */
    public const COLUMNS = ['supplier_id', 'notes', 'imposition', 'passes', 'spare_passes', 'colors', 'ink_colors', 'unit_price', 'manual', 'manual_amount'];

    public static function bootIsProcessRow(): void
    {
        static::saving(function (Model $row): void {
            if (! $row->getAttribute('manual')) {
                $row->setAttribute('manual_amount', null);
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'imposition' => 'decimal:4',
            'passes' => 'decimal:4',
            'spare_passes' => 'decimal:4',
            'colors' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'manual' => 'boolean',
            'manual_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Partner, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
