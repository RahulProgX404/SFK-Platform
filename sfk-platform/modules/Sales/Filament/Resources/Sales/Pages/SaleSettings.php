<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Sales\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Sales\Filament\Resources\Sales\SaleResource;

/** アプリの設定 → 設定 of 06. 売上入力. */
final class SaleSettings extends AppSettings
{
    protected static string $resource = SaleResource::class;
}
