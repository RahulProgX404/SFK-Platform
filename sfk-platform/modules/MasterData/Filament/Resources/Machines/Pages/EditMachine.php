<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Machines\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\MasterData\Filament\Resources\Machines\MachineResource;
use Modules\MasterData\Models\Machine;

/** @property Machine $record */
final class EditMachine extends AppEditRecord
{
    protected static string $resource = MachineResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
