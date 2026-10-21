<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\PastSales\Pages;

use Modules\Analytics\Filament\Resources\PastSales\PastSaleResource;
use Modules\Analytics\Models\PastSale;
use Modules\Core\Filament\AppPages\AppEditRecord;

/** @property PastSale $record */
final class EditPastSale extends AppEditRecord
{
    protected static string $resource = PastSaleResource::class;

    public function getTitle(): string
    {
        return PastSaleResource::titleOf($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
