<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payments\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Purchasing\Domain\PurchasingFields;
use Modules\Purchasing\Filament\Resources\Payments\PaymentResource;
use Modules\Purchasing\Models\Payment;

/**
 * A payment run of 07. 支払入力.
 *
 * @property Payment $record
 */
final class ViewPayment extends AppViewRecord
{
    /** 仕入先 of every line (one query) besides 作成者 / 更新者. */
    protected function batchedRelations(): array
    {
        return [...parent::batchedRelations(), 'lines.supplier'];
    }

    protected static string $resource = PaymentResource::class;

    public function getTitle(): string
    {
        return PaymentResource::titleOf($this->record);
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return PurchasingFields::PAYMENT;
    }
}
