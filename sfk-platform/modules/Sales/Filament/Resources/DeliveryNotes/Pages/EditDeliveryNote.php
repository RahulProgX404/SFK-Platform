<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\DeliveryNotes\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\Sales\Models\DeliveryNote;

/** @property DeliveryNote $record */
final class EditDeliveryNote extends AppEditRecord
{
    protected static string $resource = DeliveryNoteResource::class;

    public function getTitle(): string
    {
        return (string) $this->record->number;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
