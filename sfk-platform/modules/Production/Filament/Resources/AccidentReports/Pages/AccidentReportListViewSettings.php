<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\AccidentReports\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Production\Filament\Resources\AccidentReports\AccidentReportResource;

/** アプリの設定 → 一覧 of 14. 事故報告管理. */
final class AccidentReportListViewSettings extends AppListViewSettings
{
    protected static string $resource = AccidentReportResource::class;
}
