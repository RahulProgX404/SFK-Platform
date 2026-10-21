<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payables\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Purchasing\Filament\Resources\Payables\PayableResource;
use Modules\Purchasing\Models\PayableMonth;

/**
 * 編集 of a month of 23. 買掛管理: 消費税端数調整 only (the month and the following ones are recalculated, PurchasingObserver).
 *
 * @property PayableMonth $record
 */
final class EditPayable extends AppEditRecord
{
    protected static string $resource = PayableResource::class;

    public function getTitle(): string
    {
        return PayableResource::titleOf($this->record);
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ['tax_adjustment' => (int) ($data['tax_adjustment'] ?? 0)];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
