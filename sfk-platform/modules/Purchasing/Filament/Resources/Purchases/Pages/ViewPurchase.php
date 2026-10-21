<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Purchases\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Purchasing\Domain\PurchasingFields;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseResource;
use Modules\Purchasing\Models\Purchase;

/**
 * A purchase of 05. 納品(仕入)入力.
 *
 * @property Purchase $record
 */
final class ViewPurchase extends AppViewRecord
{
    protected static string $resource = PurchaseResource::class;

    public function getTitle(): string
    {
        return (string) $this->record->purchase_number;
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return PurchasingFields::PURCHASE;
    }
}
