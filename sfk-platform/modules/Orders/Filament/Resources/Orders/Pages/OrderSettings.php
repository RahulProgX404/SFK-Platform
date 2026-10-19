<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Orders\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Orders\Filament\Resources\Orders\OrderResource;

/** アプリの設定 → 設定 of 04. 受注入力. */
final class OrderSettings extends AppSettings
{
    protected static string $resource = OrderResource::class;
}
