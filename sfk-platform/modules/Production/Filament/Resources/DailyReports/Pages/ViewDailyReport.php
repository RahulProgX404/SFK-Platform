<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\DailyReports\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Production\Domain\ProductionFields;
use Modules\Production\Filament\Resources\DailyReports\DailyReportResource;
use Modules\Production\Models\DailyReport;

/**
 * A record of 08. 日報入力.
 *
 * @property DailyReport $record
 */
final class ViewDailyReport extends AppViewRecord
{
    protected static string $resource = DailyReportResource::class;

    public function getTitle(): string
    {
        return DailyReportResource::titleOf($this->record);
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return ProductionFields::DAILY_REPORT;
    }
}
