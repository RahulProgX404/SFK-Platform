<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Consumables\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\MasterData\Domain\Consumables\ConsumableFields;
use Modules\MasterData\Filament\Resources\Consumables\ConsumableResource;
use Modules\MasterData\Models\Consumable;

/** @property Consumable $record */
final class ViewConsumable extends AppViewRecord
{
    protected static string $resource = ConsumableResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return ConsumableFields::LABELS;
    }
}
