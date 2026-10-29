<?php

declare(strict_types=1);

namespace Modules\Analytics\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Analytics\Models\PastSale;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;

/**
 * Takes over 22. 過去の売上データ (legacy app 35, SYSTEM_BRAIN APP_22, BP-09): レコード番号 kept, 担当者 (a name, as in the
 * legacy app), 異常値フラグ, 決算最終月初 and the twelve monthly amounts May–April (_5月 … _4月); 年間合計 is derived
 * (a difference from the stored 合計 is reported). Records without 決算最終月初 keep none (the legacy 年月日 of those is
 * the placeholder 1970年01月01日).
 */
final class LegacyPastSaleImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
    ) {}

    public function legacyAppId(): string
    {
        return '35';
    }

    public function label(): string
    {
        return '22. 過去の売上データ';
    }

    public function step(): int
    {
        return 200;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        DB::transaction(function () use ($export, $log): void {
            foreach ($export->records() as $record) {
                $label = 'レコード番号 '.$record->id();
                $months = [];
                foreach (FiscalYear::MONTHS as $month) {
                    $months[sprintf('m%02d', $month)] = $record->int('_'.$month.'月');
                }
                $total = array_sum(array_map('intval', $months));
                if (($stored = $record->int('合計')) !== null && $stored !== $total) {
                    $log->warn("{$label}: 合計 {$stored} は再計算で {$total} になりました");
                }

                $updatedBy = $this->users->id($record->modifier());
                [$id, $created] = $this->rows->save('past_sales', $record->id(), [
                    'salesperson' => $record->text('担当者'),
                    'flag' => $log->choice($record->text('異常値フラグ'), PastSale::FLAGS, $label, '異常値フラグ') ?? PastSale::FLAGS[0],
                    'fiscal_month_start' => $record->date('決算最終月初'),
                    ...$months,
                    'total' => $total,
                    'record_key' => $record->text('レコードキー'),
                    'created_by' => $this->users->id($record->creator()),
                    'updated_by' => $updatedBy,
                    'created_at' => $record->createdAt(),
                    'updated_at' => $record->updatedAt(),
                ]);

                if ($created) {
                    $this->rows->startHistory(PastSale::query()->findOrFail($id), $updatedBy, $record->updatedAt());
                    $log->created();
                } else {
                    $log->updated();
                }
            }

            $this->rows->syncIdSequence('past_sales');
        });
    }
}
