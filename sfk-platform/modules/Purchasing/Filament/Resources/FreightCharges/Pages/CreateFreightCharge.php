<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\FreightCharges\Pages;

use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\Purchasing\Filament\Resources\FreightCharges\FreightChargeResource;

final class CreateFreightCharge extends AppCreateRecord
{
    protected static string $resource = FreightChargeResource::class;

    protected static ?string $title = '運送請求データを追加';

    /**
     * レコードを再利用する: the purchase it was posted as and the legacy id are not copied.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['purchase_id', 'legacy_id'];
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
