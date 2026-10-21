<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\FreightCharges\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Purchasing\Filament\Resources\FreightCharges\FreightChargeResource;

/** アプリの設定 → 設定 of 15. 運送請求データ取込. */
final class FreightChargeSettings extends AppSettings
{
    protected static string $resource = FreightChargeResource::class;
}
