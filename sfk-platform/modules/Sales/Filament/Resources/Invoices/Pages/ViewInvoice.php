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
