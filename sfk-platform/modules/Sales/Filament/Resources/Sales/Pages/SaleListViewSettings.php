<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Sales\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Sales\Filament\Resources\Sales\SaleResource;

/** アプリの設定 → 一覧 of 06. 売上入力. */
final class SaleListViewSettings extends AppListViewSettings
{
    protected static string $resource = SaleResource::class;
}
