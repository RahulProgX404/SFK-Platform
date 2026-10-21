<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\DailyReports\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Production\Filament\Resources\DailyReports\DailyReportResource;

/** アプリの設定 → 設定 of 08. 日報入力. */
final class DailyReportSettings extends AppSettings
{
    protected static string $resource = DailyReportResource::class;
}
