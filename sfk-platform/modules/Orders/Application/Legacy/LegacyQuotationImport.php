<?php

declare(strict_types=1);

namespace Modules\Orders\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyRecord;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\Orders\Models\Quotation;

/**
 * Takes over 09. 見積書管理 (legacy app 14, SYSTEM_BRAIN APP_09, BP-02): 見積書管理ID kept, 得意先 by its 得意先ID, 担当者,
 * the specification texts and the 見積内容 tiers (単価 × 枚数). 見積金額(税抜) is derived from the tiers (a difference
 * from the stored value is reported). 見積書PDF files are not in the export (reported).
 */
final class LegacyQuotationImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
    ) {}

    public function legacyAppId(): string
    {
        return '14';
    }

    public function label(): string
    {
        return '09. 見積書管理';
    }

    public function step(): int
    {
        return 50;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        $partners = $this->rows->ids('partners');

        DB::transaction(function () use ($export, $log, $partners): void {
            foreach ($export->records() as $record) {
                $label = '見積書管理ID '.$record->id();
                $quotedOn = $record->date('見積日付') ?? substr((string) $record->createdAt(), 0, 10);

                $customerId = null;
                if (($partner = $record->int('得意先ID')) !== null && ($customerId = $partners[$partner] ?? null) === null) {
                    $log->warn("{$label}: 得意先「{$record->text('得意先名')}」（得意先ID {$partner}）が見つかりません");
                }

                $updatedBy = $this->users->id($record->modifier());
                [$id, $created] = $this->rows->save('quotations', $record->id(), [
                    'quoted_on' => $quotedOn,
                    'customer_id' => $customerId,
                    'quote_number' => $record->text('見積書番号'),
                    'product_name' => $record->text('品名'),
                    'size' => $record->text('サイズ'),
                    'paper' => $record->text('紙'),
                    'printing' => $record->text('印刷'),
                    'window' => $record->text('窓'),
                    'flap_glue' => $record->text('口糊'),
                    'free_text_1' => $record->text('その他フリー入力_1_'),
                    'free_text_2' => $record->text('その他フリー入力_2_'),
                    'free_text_3' => $record->text('その他フリー入力_3_'),
                    'notes' => $record->text('備考'),
                    'created_by' => $this->users->id($record->creator()),
                    'updated_by' => $updatedBy,
                    'created_at' => $record->createdAt(),
                    'updated_at' => $record->updatedAt(),
                ]);

                $this->rows->replaceRows('quotation_lines', 'quotation_id', $id, $this->lines($record));
                $quotation = Quotation::query()->findOrFail($id);
                $quotation->assignees()->sync(array_values(array_filter(array_map(fn (array $user): ?int => $this->users->id($user), $record->users('担当者')))));
                $quotation->refreshTotals();

                $stored = $record->int('見積金額_税抜_');
                if ($stored !== null && $stored !== $quotation->total) {
                    $log->warn("{$label}: 見積金額(税抜) {$stored} は見積内容から {$quotation->total} になりました");
                }
                if (($files = $record->files('見積書PDF')) !== []) {
                    $log->warn("{$label}: 見積書PDF は移行されません（".implode('、', $files).'）');
                }

                if ($created) {
                    $this->rows->startHistory($quotation, $updatedBy, $record->updatedAt());
                    $log->created();
                } else {
                    $log->updated();
                }
            }

            $this->rows->syncIdSequence('quotations');
        });
    }

    /** @return list<array<string, mixed>> 見積内容 rows with a 単価 or 枚数 */
    private function lines(LegacyRecord $record): array
    {
        $lines = [];
        foreach ($record->table('見積内容') as $row) {
            if ($row->isBlank('単価', '枚数')) {
                continue;
            }
            $lines[] = [
                'sort' => count($lines),
                'sheets' => $row->decimal('枚数'),
                'unit_price' => $row->decimal('単価'),
                'created_at' => $record->createdAt(),
                'updated_at' => $record->updatedAt(),
            ];
        }

        return $lines;
    }
}
