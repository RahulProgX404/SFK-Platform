<?php

declare(strict_types=1);

namespace Modules\Production\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyRecord;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\Orders\Domain\Orders\OrderChoices;
use Modules\Production\Application\DailyReports\JobProduction;
use Modules\Production\Domain\ProductionChoices;
use Modules\Production\Models\DailyReport;

/**
 * Daily reports of the legacy apps 08. 日報入力 and 08N. 【新】日報入力 (SYSTEM_BRAIN APP_08 / APP_08N, BP-04) into the
 * new 08 — one record per task: 作業者 by 作業員コード (else name, 02 taken over before; a task without one is skipped),
 * 注番, 機械 (by 機械コード / name), ダンボール / 仕切 (19 by name), 糊, the times, 出来上 and the checks. The rates and
 * prices are the ones the report copied (snapshots); 作業時間 / 残業時間 / 社内原価 / 製造売上 are derived again
 * (TaskCosting, BR-PR-01..03).
 *
 * Afterwards the jobs add up their reports again (JobProduction: 製造済数量 / 社内原価 / 製造売上); a 製造済数量 that
 * differs from the one 04 stored is reported. The jobs keep their taken-over ステータス (ステータス更新値 is kept on the
 * reports, not applied again).
 */
abstract class LegacyDailyReports implements LegacyImporter
{
    /** @var array<string, array<string, int>> */
    private array $lookups = [];

    public function __construct(
        protected readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
        private readonly JobProduction $production,
    ) {}

    /**
     * The tasks of a legacy record: [legacy id, label, header, task] — the task's fields, else the header's.
     *
     * @return iterable<array{0: int, 1: string, 2: LegacyRecord, 3: LegacyRecord}>
     */
    abstract protected function tasks(LegacyRecord $record): iterable;

    /** Whether the legacy number also becomes the report's id (the record numbers of 08: yes). */
    protected function keepsIds(): bool
    {
        return true;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        $this->lookups = [
            'workers' => DB::table('users')->whereNotNull('worker_code')->pluck('id', 'worker_code')->all(),
            'orders' => DB::table('orders')->pluck('id', 'order_number')->all(),
            'machineCodes' => DB::table('machines')->whereNotNull('code')->pluck('id', 'code')->all(),
            'machineNames' => DB::table('machines')->pluck('id', 'name')->all(),
            'consumables' => DB::table('consumables')->pluck('id', 'name')->all(),
        ];
        $touched = [];
        $batch = 0;

        DB::beginTransaction();
        try {
            foreach ($export->records() as $record) {
                foreach ($this->tasks($record) as [$legacyId, $label, $header, $task]) {
                    $orderId = $this->importTask($legacyId, $label, $header, $task, $log);
                    if ($orderId !== null) {
                        $touched[$orderId] = true;
                    }
                }
                if (++$batch % 500 === 0) {
                    DB::commit();
                    DB::beginTransaction();
                }
            }
            $this->rows->syncIdSequence('daily_reports');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $this->refreshJobs(array_keys($touched), $log);
    }

    /** @return int|null the job of the task */
    private function importTask(int $legacyId, string $label, LegacyRecord $header, LegacyRecord $task, LegacyImportLog $log): ?int
    {
        $from = fn (string $code): LegacyRecord => $task->has($code) ? $task : $header;
        $text = fn (string $code): ?string => $from($code)->text($code);
        $decimal = fn (string $code): ?string => $from($code)->decimal($code);
        $time = fn (string $code): ?string => $from($code)->time($code);

        $workerName = $text('作業者_0') ?? $text('作業者名');
        $workerId = $this->lookups['workers'][$text('作業員コード') ?? ''] ?? $this->users->named($workerName)?->id;
        if ($workerId === null) {
            $log->skipped("{$label}: 作業者「".($workerName ?? $text('作業員コード') ?? '').'」が見つかりません');

            return null;
        }

        $number = $text('注番_0');
        $orderId = $number !== null ? ($this->lookups['orders'][$number] ?? null) : null;
        if ($number !== null && $orderId === null) {
            $log->warn("{$label}: 注番「{$number}」の受注が見つかりません");
        }

        $machineId = $this->lookups['machineCodes'][$text('機械コード') ?? ''] ?? $this->lookups['machineNames'][$text('機械名') ?? ''] ?? null;
        if ($machineId === null && $text('機械名') !== null) {
            $log->warn("{$label}: 機械「{$text('機械名')}」が見つかりません");
        }

        $box = function (string $code) use ($text, $label, $log): ?int {
            $name = $text($code);
            $id = $name !== null ? ($this->lookups['consumables'][$name] ?? null) : null;
            if ($name !== null && $id === null) {
                $log->warn("{$label}: {$code}「{$name}」が19. 消耗品マスタにありません");
            }

            return $id;
        };

        $checks = array_values(array_intersect(ProductionChoices::QUALITY_CHECKS, $from('品質チェック_0')->choices('品質チェック_0')));
        $updatedBy = $this->users->id($header->modifier());
        [$id, $created] = $this->rows->save('daily_reports', $legacyId, [
            'worked_on' => $from('作業日')->date('作業日') ?? substr((string) $header->createdAt(), 0, 10),
            'user_id' => $workerId,
            'work_rate' => $from('作業単価')->int('作業単価'),
            'overtime_rate' => $from('残業単価')->int('残業単価'),
            'order_id' => $orderId,
            'machine_id' => $machineId,
            'cardboard_id' => $box('ダンボール名称'),
            'cardboard_used' => $decimal('使用数_0'),
            'partition_id' => $box('ダンボール名称_仕切_'),
            'partition_used' => $decimal('使用数_仕切_'),
            'glue' => $log->keptChoice($text('糊_0'), ProductionChoices::GLUES, $label, '糊'),
            'glue_cost' => $decimal('糊代'),
            'started_at' => $time('開始時間_0'),
            'ended_at' => $time('終了時間_0'),
            'break_time' => $time('休憩時間'),
            'changeover_minutes' => $decimal('型変作業時間_0'),
            'changeover_count' => $decimal('回数_0'),
            'produced' => $decimal('出来上_0'),
            'crew' => $decimal('作業人数_0'),
            'day_type' => $log->choice($text('日種別'), ProductionChoices::DAY_TYPES, $label, '日種別') ?? ProductionChoices::DAY_TYPES[0],
            'overtime_started_at' => $time('残業開始時間'),
            'overtime_ended_at' => $time('残業終了時間'),
            'overtime_break' => $time('残業休憩時間'),
            'quality_checks' => json_encode($checks),
            'gloves' => $from('手袋_0')->checked('手袋_0'),
            'damaged_before' => $decimal('ケガ_前__0'),
            'damaged_after' => $decimal('ケガ_後__0'),
            'update_status' => $from('ステータスの更新')->checked('ステータスの更新'),
            // the legacy 発送作業中 / 印刷完了 were never statuses of 04: kept on the report, not applied
            'new_status' => $log->keptChoice($text('ステータス更新値'), OrderChoices::STATUSES, $label, 'ステータス更新値'),
            'difficulty' => $log->keptChoice($text('難易度_0'), ProductionChoices::DIFFICULTIES, $label, '難易度'),
            'notes' => $text('備考_0'),
            'cardboard_cost' => $decimal('ダンボール原価'),
            'partition_cost' => $decimal('ダンボール原価_仕切_'),
            'processing_rate' => $decimal('加工単価_枚_'),
            'changeover_rate' => $decimal('型替単価_回_'),
            'handwork_rate' => $decimal('内職単価_H_'),
            'machine_rate' => $decimal('機械単価_H_'),
            'printing_rate' => $decimal('印刷単価'),
            'plate_change_rate' => $decimal('版替単価'),
            'ink_mixing_rate' => $decimal('練インキ単価'),
            'created_by' => $this->users->id($header->creator()),
            'updated_by' => $updatedBy,
            'created_at' => $header->createdAt(),
            'updated_at' => $header->updatedAt(),
        ], keepId: $this->keepsIds());

        // the figures from the taken-over inputs (no lookups again: the copied rates stay)
        $report = DailyReport::query()->findOrFail($id);
        $report->fillFigures();
        $report->saveQuietly();

        if ($created) {
            $this->rows->startHistory($report, $updatedBy, $header->updatedAt());
            $log->created();
        } else {
            $log->updated();
        }

        return $orderId;
    }

    /**
     * The jobs add up their reports again; a 製造済数量 different from the stored one is reported.
     *
     * @param  list<int>  $orderIds
     */
    private function refreshJobs(array $orderIds, LegacyImportLog $log): void
    {
        foreach (array_chunk($orderIds, 300) as $chunk) {
            DB::transaction(function () use ($chunk, $log): void {
                $before = DB::table('orders')->whereIn('id', $chunk)->pluck('manufactured_quantity', 'id')->all();
                foreach ($chunk as $orderId) {
                    $this->production->refresh($orderId);
                }
                foreach (DB::table('orders')->whereIn('id', $chunk)->get(['id', 'order_number', 'manufactured_quantity']) as $order) {
                    $stored = (float) ($before[$order->id] ?? 0);
                    $derived = (float) $order->manufactured_quantity;
                    if (abs($stored - $derived) > 0.0001) {
                        $log->warn("注番 {$order->order_number}: 製造済数量 ".self::number($stored).' は日報の合計で '.self::number($derived).' になりました');
                    }
                }
            });
        }
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
