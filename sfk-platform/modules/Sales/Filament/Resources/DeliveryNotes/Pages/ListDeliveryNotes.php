<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\DeliveryNotes\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\Sales\Filament\Resources\DeliveryNotes\Tables\DeliveryNotesTable;

final class ListDeliveryNotes extends AppListRecords
{
    protected static string $resource = DeliveryNoteResource::class;

    public function listColumns(): array
    {
        return DeliveryNotesTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN views.md of the app). */
    public function defaultListViews(): array
    {
        return DeliveryNotesTable::defaultViews();
    }

    public function filterFields(): array
    {
        return DeliveryNotesTable::filterFields();
    }

    public function inlineFields(): array
    {
        return DeliveryNotesTable::inlineFields();
    }
}
