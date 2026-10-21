<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Purchases\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseResource;

/** アプリの設定 → 一覧 of 05. 納品(仕入)入力. */
final class PurchaseListViewSettings extends AppListViewSettings
{
    protected static string $resource = PurchaseResource::class;
}
