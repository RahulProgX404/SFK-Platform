<?php

declare(strict_types=1);

namespace Modules\MasterData\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\MasterData\Domain\Consumables\BoxType;
use Modules\MasterData\Domain\Consumables\ConsumableKind;
use Modules\MasterData\Models\Consumable;

/**
 * Takes over 19. 消耗品マスタ (legacy app 29, SYSTEM_BRAIN APP_19): レコード番号 kept, 名称, 区分 (kind), カテゴリ
 * (box_type), prices, 発注点, ロット数 and the 仕入先 by its 得意先レコード番号 (01 taken over before). 在庫数 is not
 * taken over: it is derived from the stock movements (the legacy app held none; 13. 在庫管理 follows).
 */
final class LegacyConsumableImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
    ) {}

    public function legacyAppId(): string
    {
        return '29';
    }

    public function label(): string
    {
        return '19. 消耗品マスタ';
    }

    public function step(): int
    {
        return 40;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        $partners = $this->rows->ids('partners');

        DB::transaction(function () use ($export, $log, $partners): void {
            foreach ($export->records() as $record) {
                $label = 'レコード番号 '.$record->id();
                $name = $record->text('名称');
                if ($name === null) {
                    $log->skipped("{$label}: 名称がありません");

                    continue;
                }

                $supplierId = null;
                $partner = $record->int('得意先レコード番号');
                if ($partner !== null) {
                    $supplierId = $partners[$partner] ?? null;
                    if ($supplierId === null) {
                        $log->warn("{$label}: 仕入先「{$record->text('仕入先名')}」（得意先ID {$partner}）が見つかりません");
                    }
                }

                $kind = $record->text('区分');
                $boxType = $record->text('カテゴリ');
                if ($kind !== null && ConsumableKind::tryFrom($kind) === null) {
                    $log->warn("{$label}: 区分「{$kind}」は選択肢にないため空欄にしました");
                }
                if ($boxType !== null && BoxType::tryFrom($boxType) === null) {
                    $log->warn("{$label}: カテゴリ「{$boxType}」は選択肢にないため空欄にしました");
                }

                $updatedBy = $this->users->id($record->modifier());
                [$id, $created] = $this->rows->save('consumables', $record->id(), [
                    'name' => $name,
                    'kind' => ConsumableKind::tryFrom((string) $kind)?->value,
                    'box_type' => BoxType::tryFrom((string) $boxType)?->value,
                    'reorder_point' => $record->decimal('発注点'),
                    'amount' => $record->decimal('金額'),
                    'unit_price' => $record->decimal('単価'),
                    'unit' => $record->text('単位'),
                    'lot_size' => $record->decimal('ロット数'),
                    'supplier_id' => $supplierId,
                    'notes' => $record->text('備考'),
                    'created_by' => $this->users->id($record->creator()),
                    'updated_by' => $updatedBy,
                    'created_at' => $record->createdAt(),
                    'updated_at' => $record->updatedAt(),
                ]);

                if ($created) {
                    $this->rows->startHistory(Consumable::query()->findOrFail($id), $updatedBy, $record->updatedAt());
                    $log->created();
                } else {
                    $log->updated();
                }
            }

            $this->rows->syncIdSequence('consumables');
        });
    }
}
