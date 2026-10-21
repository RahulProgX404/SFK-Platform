<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\ConsumableOrders\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\ConsumableOrderResource;
use Modules\Purchasing\Models\ConsumableOrder;

/** @property ConsumableOrder $record */
final class EditConsumableOrder extends AppEditRecord
{
    protected static string $resource = ConsumableOrderResource::class;

    public function getTitle(): string
    {
        return ConsumableOrderResource::titleOf($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
