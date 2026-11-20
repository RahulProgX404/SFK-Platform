<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Invoices\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Sales\Domain\Documents\DocumentFields;
use Modules\Sales\Filament\Resources\Invoices\InvoiceResource;
use Modules\Sales\Models\Invoice;

/** @property Invoice $record */
final class ViewInvoice extends AppViewRecord
{
    /** 得意先 besides 作成者 / 更新者 (one query for them, one for 得意先). */
    protected function batchedRelations(): array
    {
        return [...parent::batchedRelations(), 'customer'];
    }

    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return (string) $this->record->number;
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return DocumentFields::INVOICE;
    }
}
