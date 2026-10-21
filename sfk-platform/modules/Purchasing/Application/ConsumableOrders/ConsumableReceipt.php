<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\ConsumableOrders;

use Modules\MasterData\Domain\Consumables\StockMovementReason;
use Modules\MasterData\Models\ConsumableStockMovement;
use Modules\Purchasing\Models\ConsumableOrderLine;

/**
 * 納品日 of a row of 20. 消耗品発注 puts the delivered 数量 into the stock of its item in 19. 消耗品マスタ: one 入庫 movement
 * of the row on its 納品日. The movement is replaced on every save of the row (数量, 納品日 or 商品名 changed) and removed
 * when 納品日 is cleared or the row / order is deleted, so a delivery never counts twice. (The legacy apps had no link
 * between orders and stock, SYSTEM_BRAIN APP_19 / APP_20 known issues.)
 */
final class ConsumableReceipt
{
    public function sync(ConsumableOrderLine $line): void
    {
        $this->clear($line);

        if ($line->consumable_id === null || $line->delivered_on === null || ! is_numeric($line->quantity) || (float) $line->quantity === 0.0) {
            return;
        }

        $movement = new ConsumableStockMovement([
            'consumable_id' => $line->consumable_id,
            'moved_on' => $line->delivered_on->toDateString(),
            'reason' => StockMovementReason::Receipt,
            'quantity' => $line->quantity,
            'note' => '消耗品発注 '.$line->consumable_order_id,
        ]);
        $movement->source()->associate($line);
        $movement->save();
    }

    public function clear(ConsumableOrderLine $line): void
    {
        ConsumableStockMovement::query()->whereMorphedTo('source', $line)->delete();
    }
}
