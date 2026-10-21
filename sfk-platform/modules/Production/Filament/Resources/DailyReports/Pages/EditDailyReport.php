<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\DailyReports\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Production\Filament\Resources\DailyReports\DailyReportResource;
use Modules\Production\Models\DailyReport;

/** @property DailyReport $record */
final class EditDailyReport extends AppEditRecord
{
    protected static string $resource = DailyReportResource::class;

    public function getTitle(): string
    {
        return DailyReportResource::titleOf($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
