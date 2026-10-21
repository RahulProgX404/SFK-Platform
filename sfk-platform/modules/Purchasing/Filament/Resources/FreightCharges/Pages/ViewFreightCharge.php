<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\FreightCharges\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Purchasing\Domain\PurchasingFields;
use Modules\Purchasing\Filament\Resources\FreightCharges\FreightChargeResource;
use Modules\Purchasing\Models\FreightCharge;

/**
 * A record of 15. 運送請求データ取込.
 *
 * @property FreightCharge $record
 */
final class ViewFreightCharge extends AppViewRecord
{
    protected static string $resource = FreightChargeResource::class;

    public function getTitle(): string
    {
        return FreightChargeResource::titleOf($this->record);
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return PurchasingFields::FREIGHT;
    }
}
