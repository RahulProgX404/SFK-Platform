<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\BagSchedules\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Production\Filament\Resources\BagSchedules\BagScheduleResource;
use Modules\Production\Models\BagSchedule;

/** @property BagSchedule $record */
final class EditBagSchedule extends AppEditRecord
{
    protected static string $resource = BagScheduleResource::class;

    public function getTitle(): string
    {
        return BagScheduleResource::titleOf($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
