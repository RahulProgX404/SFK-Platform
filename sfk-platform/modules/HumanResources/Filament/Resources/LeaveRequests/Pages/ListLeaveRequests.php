<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\LeaveRequests\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestResource;
use Modules\HumanResources\Filament\Resources\LeaveRequests\Tables\LeaveRequestsTable;

final class ListLeaveRequests extends AppListRecords
{
    protected static string $resource = LeaveRequestResource::class;

    public function listColumns(): array
    {
        return LeaveRequestsTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_16 views.md). */
    public function defaultListViews(): array
    {
        return LeaveRequestsTable::defaultViews();
    }

    public function filterFields(): array
    {
        return LeaveRequestsTable::filterFields();
    }

    public function inlineFields(): array
    {
        return LeaveRequestsTable::inlineFields();
    }
}
