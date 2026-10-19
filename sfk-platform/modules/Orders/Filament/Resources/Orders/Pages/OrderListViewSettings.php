<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Orders\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Orders\Filament\Resources\Orders\OrderResource;

/** アプリの設定 → 一覧 of 04. 受注入力. */
final class OrderListViewSettings extends AppListViewSettings
{
    protected static string $resource = OrderResource::class;
}
