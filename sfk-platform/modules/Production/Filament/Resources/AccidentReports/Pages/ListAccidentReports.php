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

    /** Speed: 報告者 / 作成者 1 / 作成者 2 are all users: one query for the page (was one each) (AppListRecords::batchedTableRelations). */
    protected function batchedTableRelations(): array
    {
        return ['reporter', 'maker1', 'maker2'];
    }
}
