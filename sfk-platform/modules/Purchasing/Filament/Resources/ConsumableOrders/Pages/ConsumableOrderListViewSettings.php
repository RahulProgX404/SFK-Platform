<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\ConsumableOrders\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\ConsumableOrderResource;

/** アプリの設定 → 一覧 of 20. 消耗品発注. */
final class ConsumableOrderListViewSettings extends AppListViewSettings
{
    protected static string $resource = ConsumableOrderResource::class;
}
