<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Sales\Filament\Resources\Receivables\ReceivableMonthResource;
use Modules\Sales\Models\ReceivableMonth;

/**
 * 編集 of a month of 23 / 23-2: 消費税端数調整 only (the other figures are recalculated, SalesObserver).
 *
 * @property ReceivableMonth $record
 */
abstract class EditReceivableMonth extends AppEditRecord
{
    public function getTitle(): string
    {
        return ReceivableMonthResource::titleOf($this->record);
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
