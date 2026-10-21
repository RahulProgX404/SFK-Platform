<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\LeaveRequests\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestResource;
use Modules\HumanResources\Models\LeaveRequest;

/** @property LeaveRequest $record */
final class EditLeaveRequest extends AppEditRecord
{
    protected static string $resource = LeaveRequestResource::class;

    public function getTitle(): string
    {
        return LeaveRequestResource::titleOf($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
