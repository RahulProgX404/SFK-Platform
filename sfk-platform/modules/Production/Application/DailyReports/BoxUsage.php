<?php

declare(strict_types=1);

namespace Modules\Production\Application\DailyReports;

use Modules\MasterData\Domain\Consumables\StockMovementReason;
use Modules\MasterData\Models\Consumable;
use Modules\MasterData\Models\ConsumableStockMovement;
use Modules\Production\Models\DailyReport;

/**
 * 使用数 / 使用数(仕切) of a daily report take the boxes out of the stock of 19. 消耗品マスタ (BR-PR-07) as 使用
 * movements of the report (作業日). The report's movements are replaced on every save and removed with it — the legacy
 * flow subtracted again on every save and never gave back on delete (APP_08 / APP_08N known issues). Like the legacy
 * flow, a box whose stock is not kept (no movement yet) is left alone.
 */
final class BoxUsage
{
    public function sync(DailyReport $report): void
    {
        $this->clear($report);

        foreach ([[$report->cardboard_id, $report->cardboard_used], [$report->partition_id, $report->partition_used]] as [$consumableId, $used]) {
            if ($consumableId === null || ! is_numeric($used) || (float) $used === 0.0) {
                continue;
            }
            if (! ConsumableStockMovement::query()->where('consumable_id', $consumableId)->exists()) {
                continue;
            }

            $movement = new ConsumableStockMovement([
                'consumable_id' => $consumableId,
                'moved_on' => $report->worked_on->toDateString(),
                'reason' => StockMovementReason::Usage,
                'quantity' => bcmul((string) $used, '-1', 4),
                'note' => '日報 '.$report->id,
            ]);
            $movement->source()->associate($report);
            $movement->save();
        }
    }

    public function clear(DailyReport $report): void
    {
        ConsumableStockMovement::query()->whereMorphedTo('source', $report)->delete();
    }

    /** 在庫数 of a box now (null while its stock is not kept). */
    public static function stockOf(?int $consumableId): ?string
    {
        return $consumableId === null ? null : Consumable::query()->find($consumableId)?->stock();
    }
}
