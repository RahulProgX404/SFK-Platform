<?php

declare(strict_types=1);

namespace Modules\Analytics\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Analytics\Models\SalesPlan;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyPartners;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;

/**
 * Takes over 25. 販売先別売上計画 (legacy app 41, SYSTEM_BRAIN APP_25, BP-09): レコード番号 kept, 得意先 (by its 得意先ID),
 * 中分類, 日付, 計画値, the monthly plans (_05月_計画 …) and actuals (_05月 … — entered by hand in the legacy app, kept
 * as entered); 年間合計 is derived from the actuals.
 */
final class LegacySalesPlanImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
        private readonly LegacyPartners $partners,
    ) {}

    public function legacyAppId(): string
    {
        return '41';
    }

    public function label(): string
    {
        return '25. 販売先別売上計画';
    }

    public function step(): int
    {
        return 210;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        DB::transaction(function () use ($export, $log): void {
            foreach ($export->records() as $record) {
                $label = 'レコード番号 '.$record->id();
                $customerId = $this->partners->find($record->int('得意先ID'), $record->exactText('得意先名'));
                if ($customerId === null && $record->text('得意先名') !== null) {
                    $log->warn("{$label}: 得意先「{$record->text('得意先名')}」が見つかりません");
                }

                $months = [];
                foreach (FiscalYear::MONTHS as $month) {
                    $key = sprintf('m%02d', $month);
                    $months['plan_'.$key] = $record->int(sprintf('_%02d月_計画', $month));
                    $months['actual_'.$key] = $record->int(sprintf('_%02d月', $month));
                }
                $actualTotal = array_sum(array_map('intval', array_filter($months, fn (string $key): bool => str_starts_with($key, 'actual_'), ARRAY_FILTER_USE_KEY)));

                $updatedBy = $this->users->id($record->modifier());
                [$id, $created] = $this->rows->save('sales_plans', $record->id(), [
                    'customer_id' => $customerId,
                    'sub_category' => $record->text('中分類'),
                    'planned_on' => $record->date('日付'),
                    'plan_total' => $record->int('計画値'),
                    ...$months,
                    'actual_total' => $actualTotal,
                    'notes' => $record->text('備考'),
                    'created_by' => $this->users->id($record->creator()),
                    'updated_by' => $updatedBy,
                    'created_at' => $record->createdAt(),
                    'updated_at' => $record->updatedAt(),
                ]);

                if ($created) {
                    $this->rows->startHistory(SalesPlan::query()->findOrFail($id), $updatedBy, $record->updatedAt());
                    $log->created();
                } else {
                    $log->updated();
                }
            }

            $this->rows->syncIdSequence('sales_plans');
        });
    }
}
