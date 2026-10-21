<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\BagSchedules\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Production\Filament\Resources\BagSchedules\BagScheduleResource;

/** アプリの設定 → 一覧 of 12. 製袋予定管理. */
final class BagScheduleListViewSettings extends AppListViewSettings
{
    protected static string $resource = BagScheduleResource::class;
}
