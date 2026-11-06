<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\BagSchedules\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Production\Filament\Resources\BagSchedules\BagScheduleResource;
use Modules\Production\Filament\Resources\BagSchedules\Pages\Concerns\TakesSalesValues;
use Modules\Production\Models\BagSchedule;

/** @property BagSchedule $record */
final class EditBagSchedule extends AppEditRecord
{
    use TakesSalesValues;

    protected static string $resource = BagScheduleResource::class;

    /**
     * 品名 of a schedule of the legacy app: the job's or the free one, as the board shows it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);
        $data['product_name'] = filled($data['product_name'] ?? null) ? $data['product_name'] : $this->record->productName();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->takeSalesValues(parent::mutateFormDataBeforeSave($data));
    }

    public function getTitle(): string
    {
        return BagScheduleResource::titleOf($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
