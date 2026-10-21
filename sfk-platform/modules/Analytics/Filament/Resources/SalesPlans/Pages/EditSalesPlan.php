<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\SalesPlans\Pages;

use Modules\Analytics\Filament\Resources\SalesPlans\SalesPlanResource;
use Modules\Analytics\Models\SalesPlan;
use Modules\Core\Filament\AppPages\AppEditRecord;

/** @property SalesPlan $record */
final class EditSalesPlan extends AppEditRecord
{
    protected static string $resource = SalesPlanResource::class;

    public function getTitle(): string
    {
        return SalesPlanResource::titleOf($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
