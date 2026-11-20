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
    /** 請求書, 得意先 and the receipt of each line (one query per table) besides 作成者 / 更新者. */
    protected function batchedRelations(): array
    {
        return [...parent::batchedRelations(), 'invoice', 'customer', 'lines.receipt'];
    }

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
