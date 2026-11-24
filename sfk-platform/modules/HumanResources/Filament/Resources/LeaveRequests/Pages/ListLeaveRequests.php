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

    /** Speed: 申請者 / 作業者 / 作成者 / 承認者 are all users: one query for the page (was one each) (AppListRecords::batchedTableRelations). */
    protected function batchedTableRelations(): array
    {
        return ['applicant', 'worker', 'creator', 'approver'];
    }
}
