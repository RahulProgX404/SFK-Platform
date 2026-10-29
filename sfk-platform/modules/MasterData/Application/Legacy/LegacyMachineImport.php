<?php

declare(strict_types=1);

namespace Modules\MasterData\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\MasterData\Models\Machine;

/**
 * Takes over 03. 機械マスタ (legacy app 8, SYSTEM_BRAIN APP_03): 機械マスタID kept (id and legacy_id), 機械コード, 機械名
 * and the unit prices (加工単価(枚), 型替単価(回), 内職単価(H), 機械単価(H), 印刷単価, 版替単価, 練インキ単価) as stored.
 */
final class LegacyMachineImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
    ) {}

    public function legacyAppId(): string
    {
        return '8';
    }

    public function label(): string
    {
        return '03. 機械マスタ';
    }

    public function step(): int
    {
        return 20;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        DB::transaction(function () use ($export, $log): void {
            foreach ($export->records() as $record) {
                $name = $record->text('機械名');
                if ($name === null) {
                    $log->skipped('機械マスタID '.$record->id().': 機械名がありません');

                    continue;
                }

                $updatedBy = $this->users->id($record->modifier());
                [$id, $created] = $this->rows->save('machines', $record->id(), [
                    'code' => $record->text('機械コード'),
                    'name' => $name,
                    'processing_rate' => $record->decimal('加工単価_枚_'),
                    'changeover_rate' => $record->decimal('型替単価_回_'),
                    'handwork_rate' => $record->decimal('内職単価_H_'),
                    'machine_rate' => $record->decimal('機械単価_H_'),
                    'printing_rate' => $record->decimal('印刷単価'),
                    'plate_change_rate' => $record->decimal('版替単価'),
                    'ink_mixing_rate' => $record->decimal('練インキ単価'),
                    'notes' => $record->text('備考'),
                    'created_by' => $this->users->id($record->creator()),
                    'updated_by' => $updatedBy,
                    'created_at' => $record->createdAt(),
                    'updated_at' => $record->updatedAt(),
                ]);

                if ($created) {
                    $this->rows->startHistory(Machine::query()->findOrFail($id), $updatedBy, $record->updatedAt());
                    $log->created();
                } else {
                    $log->updated();
                }
            }

            $this->rows->syncIdSequence('machines');
        });
    }
}
