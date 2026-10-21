<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\AccidentReports\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Production\Filament\Resources\AccidentReports\AccidentReportResource;
use Modules\Production\Models\AccidentReport;

/** @property AccidentReport $record */
final class EditAccidentReport extends AppEditRecord
{
    protected static string $resource = AccidentReportResource::class;

    public function getTitle(): string
    {
        return AccidentReportResource::titleOf($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
