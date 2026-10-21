<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receipts\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Sales\Domain\Documents\DocumentFields;
use Modules\Sales\Filament\Resources\Receipts\ReceiptResource;
use Modules\Sales\Models\Receipt;

/** @property Receipt $record */
final class ViewReceipt extends AppViewRecord
{
    protected static string $resource = ReceiptResource::class;

    public function getTitle(): string
    {
        return (string) $this->record->invoice?->number ?: 'レコード番号 '.$this->record->id;
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return DocumentFields::RECEIPT;
    }
}
