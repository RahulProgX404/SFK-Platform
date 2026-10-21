<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\DailyReports\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Production\Filament\Resources\DailyReports\DailyReportResource;

/** アプリの設定 → 一覧 of 08. 日報入力. */
final class DailyReportListViewSettings extends AppListViewSettings
{
    protected static string $resource = DailyReportResource::class;
}
