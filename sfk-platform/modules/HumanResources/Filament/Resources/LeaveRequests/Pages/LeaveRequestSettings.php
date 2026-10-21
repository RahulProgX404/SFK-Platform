<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\LeaveRequests\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestResource;

/** アプリの設定 → 設定 of 16. 申請管理. */
final class LeaveRequestSettings extends AppSettings
{
    protected static string $resource = LeaveRequestResource::class;
}
