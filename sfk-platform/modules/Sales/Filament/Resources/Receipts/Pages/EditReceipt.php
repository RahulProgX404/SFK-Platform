<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receipts\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Sales\Filament\Resources\Receipts\ReceiptResource;
use Modules\Sales\Models\Receipt;

/** @property Receipt $record */
final class EditReceipt extends AppEditRecord
{
    protected static string $resource = ReceiptResource::class;

    public function getTitle(): string
    {
        return (string) $this->record->invoice?->number ?: 'レコード番号 '.$this->record->id;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
