<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\AccidentReports\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Production\Domain\ProductionFields;
use Modules\Production\Filament\Resources\AccidentReports\AccidentReportResource;
use Modules\Production\Models\AccidentReport;

/**
 * A record of 14. 事故報告管理.
 *
 * @property AccidentReport $record
 */
final class ViewAccidentReport extends AppViewRecord
{
    protected static string $resource = AccidentReportResource::class;

    public function getTitle(): string
    {
        return AccidentReportResource::titleOf($this->record);
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return ProductionFields::ACCIDENT;
    }
}
