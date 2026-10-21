<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\AlcoholCheckResource;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\Tables\AlcoholChecksTable;

final class ListAlcoholChecks extends AppListRecords
{
    protected static string $resource = AlcoholCheckResource::class;

    public function listColumns(): array
    {
        return AlcoholChecksTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_18 views.md). */
    public function defaultListViews(): array
    {
        return AlcoholChecksTable::defaultViews();
    }

    public function filterFields(): array
    {
        return AlcoholChecksTable::filterFields();
    }

    public function inlineFields(): array
    {
        return AlcoholChecksTable::inlineFields();
    }
}
