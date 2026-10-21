<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\PastSales\Pages;

use Modules\Analytics\Filament\Resources\PastSales\PastSaleResource;
use Modules\Core\Filament\AppPages\AppSettings;

/** アプリの設定 → 設定 of 22. 過去の売上データ. */
final class PastSaleSettings extends AppSettings
{
    protected static string $resource = PastSaleResource::class;
}
