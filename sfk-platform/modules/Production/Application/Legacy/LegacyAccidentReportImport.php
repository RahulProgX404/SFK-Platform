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
use Modules\Production\Domain\ProductionChoices;
use Modules\Production\Models\AccidentReport;

/**
 * Takes over 14. 事故報告管理 (legacy app 19, SYSTEM_BRAIN APP_14): レコード番号 kept, 事故発生日, 報告者 / 製造者 (02 by
 * name; a report without its 報告者 is skipped), 注番, 担当機械 (by its 機械マスタID), 営業関係者, the texts and the 損害
 * rows (項目, 枚数, 原価, 数量). 合計損害額 is derived from the rows.
 */
final class LegacyAccidentReportImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
    ) {}

    public function legacyAppId(): string
    {
        return '19';
    }

    public function label(): string
    {
        return '14. 事故報告管理';
    }

    public function step(): int
    {
        return 90;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        $machines = $this->rows->ids('machines');

        DB::transaction(function () use ($export, $log, $machines): void {
            foreach ($export->records() as $record) {
                $label = 'レコード番号 '.$record->id();
                $reporterId = $this->users->named($record->text('報告者氏名'))?->id;
                if ($reporterId === null) {
                    $log->skipped("{$label}: 報告者「{$record->text('報告者氏名')}」が見つかりません");

                    continue;
                }

                $number = $record->text('注番');
                $orderId = $number !== null ? DB::table('orders')->where('order_number', $number)->value('id') : null;
                if ($number !== null && $orderId === null) {
                    $log->warn("{$label}: 注番「{$number}」の受注が見つかりません");
                }

                $updatedBy = $this->users->id($record->modifier());
                [$id, $created] = $this->rows->save('accident_reports', $record->id(), [
                    'occurred_on' => $record->date('事故発生日') ?? substr((string) $record->createdAt(), 0, 10),
                    'reporter_id' => $reporterId,
                    'order_id' => $orderId,
                    'quantity' => $record->decimal('数量'),
                    'machine_id' => $machines[$record->int('機械マスタID') ?? 0] ?? null,
                    'maker1_id' => $this->users->named($record->text('製造者1'))?->id,
                    'maker2_id' => $this->users->named($record->text('製造者2'))?->id,
                    'description' => $record->text('事故内容_具体的な状況_'),
                    'cause' => $record->text('発生した原因'),
                    'response' => $record->text('事故後の対処方法'),
                    'prevention' => $record->text('再発防止方法'),
                    'notes' => $record->text('備考'),
                    'created_by' => $this->users->id($record->creator()),
                    'updated_by' => $updatedBy,
                    'created_at' => $record->createdAt(),
                    'updated_at' => $record->updatedAt(),
                ]);

                $this->rows->replaceRows('accident_damages', 'accident_report_id', $id, $this->damages($record, $label, $log));
                $report = AccidentReport::query()->findOrFail($id);
                $report->salesUsers()->sync(array_values(array_filter(array_map(fn (array $user): ?int => $this->users->id($user), $record->users('営業関係者')))));
                $report->refreshTotals();

                if ($created) {
                    $this->rows->startHistory($report, $updatedBy, $record->updatedAt());
                    $log->created();
                } else {
                    $log->updated();
                }
            }

            $this->rows->syncIdSequence('accident_reports');
        });
    }

    /** @return list<array<string, mixed>> */
    private function damages(LegacyRecord $record, string $label, LegacyImportLog $log): array
    {
        $rows = [];
        foreach ($record->table('損害') as $row) {
            $rows[] = [
                'sort' => count($rows),
                'item' => $log->choice($row->text('項目'), ProductionChoices::DAMAGE_ITEMS, $label, '損害 項目'),
                'sheets' => $row->decimal('枚数'),
                'cost' => $row->decimal('原価'),
                'quantity' => $row->decimal('数量_0'),
                'created_at' => $record->createdAt(),
                'updated_at' => $record->updatedAt(),
            ];
        }

        return $rows;
    }
}
