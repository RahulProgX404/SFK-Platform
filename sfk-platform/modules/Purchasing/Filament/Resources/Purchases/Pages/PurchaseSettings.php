<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Purchases\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseResource;

/** アプリの設定 → 設定 of 05. 納品(仕入)入力. */
final class PurchaseSettings extends AppSettings
{
    protected static string $resource = PurchaseResource::class;
}
