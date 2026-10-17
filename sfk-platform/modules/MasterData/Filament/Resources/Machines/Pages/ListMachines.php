<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Machines\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\MasterData\Filament\Resources\Machines\MachineResource;
use Modules\MasterData\Filament\Resources\Machines\Tables\MachinesTable;

final class ListMachines extends AppListRecords
{
    protected static string $resource = MachineResource::class;

    public function listColumns(): array
    {
        return MachinesTable::columns();
    }

    /** The legacy view 機械一覧 (SYSTEM_BRAIN APP_03 views.md). */
    public function defaultListViews(): array
    {
        return MachinesTable::defaultViews();
    }

    public function filterFields(): array
    {
        return MachinesTable::filterFields();
    }

    public function inlineFields(): array
    {
        return MachinesTable::inlineFields();
    }
}
