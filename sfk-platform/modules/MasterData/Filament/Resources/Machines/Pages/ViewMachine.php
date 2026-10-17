<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Machines\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\MasterData\Domain\Machines\MachineFields;
use Modules\MasterData\Filament\Resources\Machines\MachineResource;
use Modules\MasterData\Models\Machine;

/** @property Machine $record */
final class ViewMachine extends AppViewRecord
{
    protected static string $resource = MachineResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return MachineFields::LABELS;
    }
}
