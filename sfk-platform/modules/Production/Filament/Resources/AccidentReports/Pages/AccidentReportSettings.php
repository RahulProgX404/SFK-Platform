<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\AccidentReports\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Production\Filament\Resources\AccidentReports\AccidentReportResource;

/** アプリの設定 → 設定 of 14. 事故報告管理. */
final class AccidentReportSettings extends AppSettings
{
    protected static string $resource = AccidentReportResource::class;
}
