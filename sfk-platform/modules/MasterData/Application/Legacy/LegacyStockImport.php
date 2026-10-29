<?php

declare(strict_types=1);

namespace Modules\MasterData\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\MasterData\Domain\Consumables\ConsumableKind;
use Modules\MasterData\Domain\Consumables\StockMovementReason;
use Modules\MasterData\Models\Consumable;
use Modules\MasterData\Models\ConsumableStockMovement;

/**
 * Takes over the legacy app 13. 在庫管理 (legacy app 18, SYSTEM_BRAIN APP_13; not rebuilt — its stock lives in 19,
 * roadmap Phase 5): each item becomes the 消耗品 of its 名称(管理No) (created when 19 has none, 区分 ダンボール =
 * 段ボール, 原価 = 単価) with its 在庫数 as one 調整 stock movement on its 更新日時 — replaced on a re-run.
 */
final class LegacyStockImport implements LegacyImporter
{
    private const NOTE = '旧システム 13. 在庫管理 の在庫数';

    public function __construct(private readonly LegacyUsers $users) {}

    public function legacyAppId(): string
    {
        return '18';
    }

    public function label(): string
    {
        return '13. 在庫管理';
    }

    public function step(): int
    {
        return 45;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        DB::transaction(function () use ($export, $log): void {
            foreach ($export->records() as $record) {
                $label = '在庫管理ID '.$record->id();
                $name = $record->text('名称_管理No_') ?? $record->text('名称_日報連携キー') ?? $record->text('商品名');
                if ($name === null) {
                    $log->skipped("{$label}: 名称がありません");

                    continue;
                }

                $userId = $this->users->id($record->modifier());
                $consumable = Consumable::query()->where('name', $name)->first();
                $created = $consumable === null;
                if ($consumable === null) {
                    $kind = str_replace('ダンボール', '段ボール', (string) $record->text('区分'));
                    $consumable = new Consumable([
                        'name' => $name,
                        'kind' => ConsumableKind::tryFrom($kind)?->value,
                        'unit_price' => $record->decimal('原価'),
                        'notes' => $record->text('備考') ?? $record->text('用途'),
                    ]);
                    $consumable->forceFill([
                        'created_by' => $this->users->id($record->creator()),
                        'updated_by' => $userId,
                        'created_at' => $record->createdAt(),
                        'updated_at' => $record->updatedAt(),
                    ])->saveQuietly();
                }

                ConsumableStockMovement::query()->where('consumable_id', $consumable->id)->where('note', self::NOTE)->delete();
                $quantity = $record->decimal('在庫数');
                if ($quantity !== null && (float) $quantity !== 0.0) {
                    $movement = new ConsumableStockMovement([
                        'consumable_id' => $consumable->id,
                        'moved_on' => substr((string) ($record->updatedAt() ?? now()->toDateTimeString()), 0, 10),
                        'reason' => StockMovementReason::Adjustment->value,
                        'quantity' => $quantity,
                        'note' => self::NOTE,
                    ]);
                    $movement->forceFill(['created_by' => $userId, 'created_at' => $record->updatedAt(), 'updated_at' => $record->updatedAt()])->saveQuietly();
                }

                $created ? $log->created() : $log->updated();
            }
        });
    }
}
