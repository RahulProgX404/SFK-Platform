<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\LeaveRequests\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestResource;

/** アプリの設定 → 一覧 of 16. 申請管理. */
final class LeaveRequestListViewSettings extends AppListViewSettings
{
    protected static string $resource = LeaveRequestResource::class;
}
