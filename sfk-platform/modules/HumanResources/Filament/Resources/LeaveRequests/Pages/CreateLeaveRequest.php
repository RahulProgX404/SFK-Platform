<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\LeaveRequests\Pages;

use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestResource;

/** New request of 16. 申請管理; it starts 申請前 with its 作成者 as 作業者. */
final class CreateLeaveRequest extends AppCreateRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected static ?string $title = '申請を追加';

    /**
     * レコードを再利用する: the copy starts 申請前; the legacy id is not copied.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['legacy_id', 'status'];
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
