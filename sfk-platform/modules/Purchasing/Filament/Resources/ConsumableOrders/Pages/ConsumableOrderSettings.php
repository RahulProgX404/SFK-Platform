<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\ConsumableOrders\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\ConsumableOrderResource;

/** アプリの設定 → 設定 of 20. 消耗品発注. */
final class ConsumableOrderSettings extends AppSettings
{
    protected static string $resource = ConsumableOrderResource::class;
}
