<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\BagSchedules\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Production\Filament\Resources\BagSchedules\BagScheduleResource;

/** アプリの設定 → 設定 of 12. 製袋予定管理. */
final class BagScheduleSettings extends AppSettings
{
    protected static string $resource = BagScheduleResource::class;
}
