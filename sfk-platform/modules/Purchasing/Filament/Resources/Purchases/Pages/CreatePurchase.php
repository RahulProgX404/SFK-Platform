<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Purchases\Pages;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseResource;
use Modules\Purchasing\Models\Purchase;

/**
 * New purchase of 05. 納品(仕入)入力: numbered on save (BR-PU-01); with 仕入原価超過 checked its 通知先 are notified
 * (BR-PU-05, SendsOverrunAlert).
 */
final class CreatePurchase extends AppCreateRecord
{
    use SendsOverrunAlert;

    protected static string $resource = PurchaseResource::class;

    protected static ?string $title = '納品(仕入)を追加';

    protected function afterCreate(): void
    {
        parent::afterCreate();

        $record = $this->getRecord();
        if ($record instanceof Purchase) {
            $this->sendOverrunAlert($record);
        }
    }

    /**
     * レコードを再利用する: 納品(仕入)入力No is numbered anew; the legacy id is not copied.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['purchase_number', 'legacy_id', 'total'];
    }

    /** 通知先 goes with the copy. */
    protected function mutateDuplicateData(array $data, Model $source): array
    {
        if ($source instanceof Purchase) {
            $data['notified_users'] = $source->notifiedUsers()->pluck('users.id')->map(fn (mixed $id): string => (string) $id)->all();
        }

        return $data;
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
