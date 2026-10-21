<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\AccidentReports\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Production\Filament\Resources\AccidentReports\AccidentReportResource;
use Modules\Production\Filament\Resources\AccidentReports\Tables\AccidentReportsTable;

final class ListAccidentReports extends AppListRecords
{
    protected static string $resource = AccidentReportResource::class;

    public function listColumns(): array
    {
        return AccidentReportsTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_14 views.md). */
    public function defaultListViews(): array
    {
        return AccidentReportsTable::defaultViews();
    }

    public function filterFields(): array
    {
        return AccidentReportsTable::filterFields();
    }

    public function inlineFields(): array
    {
        return AccidentReportsTable::inlineFields();
    }
}
