<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Quotations\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Orders\Filament\Resources\Quotations\QuotationResource;
use Modules\Orders\Models\Quotation;

/** @property Quotation $record */
final class EditQuotation extends AppEditRecord
{
    protected static string $resource = QuotationResource::class;

    public function getTitle(): string
    {
        return QuotationResource::titleOf($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
