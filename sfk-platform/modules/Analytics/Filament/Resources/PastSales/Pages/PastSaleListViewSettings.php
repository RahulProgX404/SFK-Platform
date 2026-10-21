<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\PastSales\Pages;

use Modules\Analytics\Filament\Resources\PastSales\PastSaleResource;
use Modules\Core\Filament\AppPages\AppListViewSettings;

/** アプリの設定 → 一覧 of 22. 過去の売上データ. */
final class PastSaleListViewSettings extends AppListViewSettings
{
    protected static string $resource = PastSaleResource::class;
}
