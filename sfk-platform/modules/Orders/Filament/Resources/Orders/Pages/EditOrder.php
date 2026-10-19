<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Orders\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Models\Order;

/** @property Order $record */
final class EditOrder extends AppEditRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return $this->record->order_number;
    }

    /** The tables are saved after the record: recalculate, then keep the version in the 変更履歴. */
    protected function afterSave(): void
    {
        $this->record->refreshTotals();

        parent::afterSave();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
