<?php

declare(strict_types=1);

namespace Modules\MasterData\Application\Consumables;

use Carbon\CarbonImmutable;
use Modules\Core\Application\Notifications\Notice;
use Modules\Core\Application\Notifications\NotificationCheck;
use Modules\Core\Application\Notifications\Notifier;
use Modules\MasterData\Domain\Consumables\ConsumableFields;
use Modules\MasterData\Models\Consumable;

/**
 * 「要発注」 of 19. 消耗品マスタ (BR-MD-05: 在庫数 − 発注点 ≤ 0) as a 通知 to the administrators (who approve the
 * 20. 消耗品発注): sent when a stock movement or a new 発注点 brings the consumable to 要発注, once until the stock is
 * above the 発注点 again (`reorder_alerted_at`). The daily check catches what changed the stock without model events.
 */
final class ReorderAlert implements NotificationCheck
{
    /** The kind of its 通知 (registered by the module). */
    public const NOTIFICATION = 'master-data.consumable.reorder';

    public function __construct(private readonly Notifier $notifier) {}

    public function refresh(int $consumableId): void
    {
        $consumable = Consumable::query()->withStock()->find($consumableId);
        if ($consumable === null) {
            return;
        }

        $low = $consumable->reorderFlag() === ConsumableFields::REORDER;
        if ($low === ($consumable->reorder_alerted_at !== null)) {
            return;
        }

        if ($low) {
            $this->notifier->send(
                Notice::make(self::NOTIFICATION)
                    ->about($consumable, $consumable->name)
                    ->body(ConsumableFields::REORDER.'（在庫数 '.self::number($consumable->stock()).' / 発注点 '.self::number($consumable->reorder_point).'）'),
                $this->notifier->administrators(),
            );
        }

        $consumable->forceFill(['reorder_alerted_at' => $low ? now() : null])->saveQuietly();
    }

    public function run(CarbonImmutable $today): void
    {
        Consumable::query()
            ->where(fn ($query) => $query->whereNotNull('reorder_point')->orWhereNotNull('reorder_alerted_at'))
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (mixed $id) => $this->refresh((int) $id));
    }

    /** 12.5000 → 12.5 */
    private static function number(?string $value): string
    {
        return $value === null ? '' : rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
    }
}
