<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\FreightCharges\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Purchasing\Filament\Resources\FreightCharges\FreightChargeResource;
use Modules\Purchasing\Models\FreightCharge;

/** @property FreightCharge $record */
final class EditFreightCharge extends AppEditRecord
{
    protected static string $resource = FreightChargeResource::class;

    public function getTitle(): string
    {
        return FreightChargeResource::titleOf($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
