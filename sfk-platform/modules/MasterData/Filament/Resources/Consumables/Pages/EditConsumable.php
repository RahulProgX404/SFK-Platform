<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Consumables\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\MasterData\Filament\Resources\Consumables\ConsumableResource;
use Modules\MasterData\Models\Consumable;

/** @property Consumable $record */
final class EditConsumable extends AppEditRecord
{
    protected static string $resource = ConsumableResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
