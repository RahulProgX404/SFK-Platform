<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payments\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Purchasing\Filament\Resources\Payments\PaymentResource;
use Modules\Purchasing\Models\Payment;

/** @property Payment $record */
final class EditPayment extends AppEditRecord
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string
    {
        return PaymentResource::titleOf($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
