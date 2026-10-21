<?php

declare(strict_types=1);

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\MasterData\Models\Consumable;

/**
 * A row of 発注明細 (SYSTEM_BRAIN APP_20): 商品名 (lookup of 19. 消耗品マスタ copying 単価 / 単位), 数量, 希望納期,
 * 発注者名又は番号, 納品日 and 備考. A 納品日 puts the 数量 into the item's stock (ConsumableReceipt, registered in PurchasingServiceProvider).
 *
 * @property int $id
 * @property int $consumable_order_id
 * @property int|null $consumable_id
 * @property string|null $unit_price
 * @property string|null $quantity
 * @property string|null $unit
 * @property Carbon|null $requested_on
 * @property string|null $orderer
 * @property Carbon|null $delivered_on
 * @property string|null $notes
 * @property-read Consumable|null $consumable
 */
final class ConsumableOrderLine extends Model
{
    /** Columns of a row, as kept in the 変更履歴 of the order. */
    public const COLUMNS = ['consumable_id', 'unit_price', 'quantity', 'unit', 'requested_on', 'orderer', 'delivered_on', 'notes'];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'quantity' => 'decimal:4',
            'requested_on' => 'date',
            'delivered_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // lookup 商品名: 単価 and 単位 are copied from 19. 消耗品マスタ when the item is chosen (a snapshot)
        self::saving(function (self $line): void {
            if ($line->isDirty('consumable_id') && $line->consumable_id !== null) {
                $consumable = Consumable::query()->find($line->consumable_id);
                if ($consumable !== null) {
                    $line->unit_price = $consumable->unit_price;
                    $line->unit = $consumable->unit;
                }
            }
        });
    }

    /** @return BelongsTo<Consumable, $this> 商品名 */
    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class);
    }

    /** @return BelongsTo<ConsumableOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ConsumableOrder::class, 'consumable_order_id');
    }
}
