<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Invoices\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Sales\Filament\Resources\Invoices\InvoiceResource;
use Modules\Sales\Models\Invoice;

/** @property Invoice $record */
final class EditInvoice extends AppEditRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return (string) $this->record->number;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
