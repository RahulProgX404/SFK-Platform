<?php

declare(strict_types=1);

namespace Modules\Production\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\Production\Domain\ProductionChoices;
use Modules\Production\Models\BagSchedule;

/**
 * Takes over 12. 製袋予定管理 (legacy app 17, SYSTEM_BRAIN APP_12, BP-04): 製袋予定ID kept, 開始 / 終了日時, 機械 (by its
 * 機械レコード番号, 03 taken over before), 使用状況, 部数, 備考 and the job by its 注番 (04 taken over before).
 *
 * The legacy app kept 品名(フリー) on schedules entered 「受注から注番を取得」 without a job; the new one keeps only the
 * fields of the chosen 入力方法, so a schedule whose 注番 is not a job becomes 「フリー入力」 with the 注番 / 品名 it
 * showed (reported when it had a 注番).
 */
final class LegacyBagScheduleImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
    ) {}

    public function legacyAppId(): string
    {
        return '17';
    }

    public function label(): string
    {
        return '12. 製袋予定管理';
    }

    public function step(): int
    {
        return 70;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        $machines = $this->rows->ids('machines');
        $machineCodes = DB::table('machines')->whereNotNull('code')->pluck('id', 'code')->all();

        DB::transaction(function () use ($export, $log, $machines, $machineCodes): void {
            foreach ($export->records() as $record) {
                $label = '製袋予定ID '.$record->id();

                $machineId = $machines[$record->int('機械レコード番号') ?? 0] ?? $machineCodes[$record->text('機械コード') ?? ''] ?? null;
                if ($machineId === null && $record->text('機械名') !== null) {
                    $log->warn("{$label}: 機械「{$record->text('機械名')}」が見つかりません");
                }

                $number = $record->text('注番') ?? $record->text('注番_P_');
                $orderId = $number !== null ? DB::table('orders')->where('order_number', $number)->value('id') : null;
                $free = $orderId === null;
                if ($free && $record->text('注番') !== null) {
                    $log->warn("{$label}: 注番「{$number}」の受注が見つからないため、フリー入力にしました");
                }

                $updatedBy = $this->users->id($record->modifier());
                [$id, $created] = $this->rows->save('bag_schedules', $record->id(), [
                    'starts_at' => $record->dateTime('開始日時'),
                    'ends_at' => $record->dateTime('終了日時'),
                    'machine_id' => $machineId,
                    'usage_status' => $log->choice($record->text('使用状況'), array_keys(ProductionChoices::USAGE_STATUSES), $label, '使用状況'),
                    'entry_mode' => $free ? ProductionChoices::ENTRY_FREE : ProductionChoices::ENTRY_FROM_ORDER,
                    'order_id' => $orderId !== null ? (int) $orderId : null,
                    'free_order_number' => $free ? ($record->text('注番_フリー_') ?? $number) : null,
                    'free_product_name' => $free ? ($record->text('品名_フリー_') ?? $record->text('品名_P_') ?? $record->text('品名')) : null,
                    'copies' => $record->decimal('部数'),
                    'notes' => $record->text('備考'),
                    'created_by' => $this->users->id($record->creator()),
                    'updated_by' => $updatedBy,
                    'created_at' => $record->createdAt(),
                    'updated_at' => $record->updatedAt(),
                ]);

                if ($created) {
                    $this->rows->startHistory(BagSchedule::query()->findOrFail($id), $updatedBy, $record->updatedAt());
                    $log->created();
                } else {
                    $log->updated();
                }
            }

            $this->rows->syncIdSequence('bag_schedules');
        });
    }
}
