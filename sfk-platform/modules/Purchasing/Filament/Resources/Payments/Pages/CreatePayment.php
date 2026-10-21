<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payments\Pages;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\Purchasing\Filament\Resources\Payments\PaymentResource;
use Modules\Purchasing\Models\Payment;
use Modules\Purchasing\Models\PaymentLine;

final class CreatePayment extends AppCreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected static ?string $title = '支払を追加';

    /**
     * レコードを再利用する: the legacy id and the derived total are not copied.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['legacy_id', 'total'];
    }

    /** The rows go with the copy (出金日 follows the new 支払日). */
    protected function mutateDuplicateData(array $data, Model $source): array
    {
        if ($source instanceof Payment) {
            $data['lines'] = $source->lines->map(fn (PaymentLine $row): array => array_intersect_key($row->attributesToArray(), array_flip(PaymentLine::COLUMNS)))->all();
        }

        return $data;
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
