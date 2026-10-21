<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\BagSchedules\Pages;

use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\Production\Filament\Resources\BagSchedules\BagScheduleResource;

final class CreateBagSchedule extends AppCreateRecord
{
    protected static string $resource = BagScheduleResource::class;

    protected static ?string $title = '製袋予定を追加';

    /**
     * レコードを再利用する: the legacy id is not copied.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['legacy_id'];
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
