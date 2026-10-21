<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\BagSchedules\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Production\Domain\ProductionFields;
use Modules\Production\Filament\Resources\BagSchedules\BagScheduleResource;
use Modules\Production\Models\BagSchedule;

/**
 * A record of 12. 製袋予定管理.
 *
 * @property BagSchedule $record
 */
final class ViewBagSchedule extends AppViewRecord
{
    protected static string $resource = BagScheduleResource::class;

    public function getTitle(): string
    {
        return BagScheduleResource::titleOf($this->record);
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return ProductionFields::SCHEDULE;
    }
}
