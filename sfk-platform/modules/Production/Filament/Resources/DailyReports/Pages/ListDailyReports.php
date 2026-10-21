<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\DailyReports\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Production\Filament\Resources\DailyReports\DailyReportResource;
use Modules\Production\Filament\Resources\DailyReports\Tables\DailyReportsTable;

final class ListDailyReports extends AppListRecords
{
    protected static string $resource = DailyReportResource::class;

    public function listColumns(): array
    {
        return DailyReportsTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_08 views.md). */
    public function defaultListViews(): array
    {
        return DailyReportsTable::defaultViews();
    }

    public function filterFields(): array
    {
        return DailyReportsTable::filterFields();
    }

    public function inlineFields(): array
    {
        return DailyReportsTable::inlineFields();
    }
}
