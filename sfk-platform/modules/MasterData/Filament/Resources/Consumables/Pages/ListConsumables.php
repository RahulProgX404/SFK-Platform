<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Consumables\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\MasterData\Filament\Resources\Consumables\ConsumableResource;
use Modules\MasterData\Filament\Resources\Consumables\Tables\ConsumablesTable;

final class ListConsumables extends AppListRecords
{
    protected static string $resource = ConsumableResource::class;

    public function listColumns(): array
    {
        return ConsumablesTable::columns();
    }

    /** The legacy view 消耗品一覧 (SYSTEM_BRAIN APP_19 views.md). */
    public function defaultListViews(): array
    {
        return ConsumablesTable::defaultViews();
    }

    public function filterFields(): array
    {
        return ConsumablesTable::filterFields();
    }

    public function inlineFields(): array
    {
        return ConsumablesTable::inlineFields();
    }
}
