<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\SafetyChecks\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\HumanResources\Filament\Resources\SafetyChecks\SafetyCheckResource;
use Modules\HumanResources\Filament\Resources\SafetyChecks\Tables\SafetyChecksTable;

final class ListSafetyChecks extends AppListRecords
{
    protected static string $resource = SafetyCheckResource::class;

    public function listColumns(): array
    {
        return SafetyChecksTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_17 views.md). */
    public function defaultListViews(): array
    {
        return SafetyChecksTable::defaultViews();
    }

    public function filterFields(): array
    {
        return SafetyChecksTable::filterFields();
    }

    public function inlineFields(): array
    {
        return SafetyChecksTable::inlineFields();
    }
}
