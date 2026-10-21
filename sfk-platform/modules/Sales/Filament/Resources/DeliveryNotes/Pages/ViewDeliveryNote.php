<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\DeliveryNotes\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Sales\Domain\Documents\DocumentFields;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\Sales\Models\DeliveryNote;

/** @property DeliveryNote $record */
final class ViewDeliveryNote extends AppViewRecord
{
    protected static string $resource = DeliveryNoteResource::class;

    public function getTitle(): string
    {
        return (string) $this->record->number;
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return DocumentFields::DELIVERY_NOTE;
    }
}
