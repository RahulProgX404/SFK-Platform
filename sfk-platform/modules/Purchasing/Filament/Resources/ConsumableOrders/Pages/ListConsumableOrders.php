<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\ConsumableOrders\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\ConsumableOrderResource;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\Tables\ConsumableOrdersTable;

final class ListConsumableOrders extends AppListRecords
{
    protected static string $resource = ConsumableOrderResource::class;

    public function listColumns(): array
    {
        return ConsumableOrdersTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_20 views.md). */
    public function defaultListViews(): array
    {
        return ConsumableOrdersTable::defaultViews();
    }

    public function filterFields(): array
    {
        return ConsumableOrdersTable::filterFields();
    }

    public function inlineFields(): array
    {
        return ConsumableOrdersTable::inlineFields();
    }
}
