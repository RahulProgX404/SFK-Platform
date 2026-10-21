<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\FreightCharges\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Purchasing\Filament\Resources\FreightCharges\FreightChargeResource;

/** アプリの設定 → 一覧 of 15. 運送請求データ取込. */
final class FreightChargeListViewSettings extends AppListViewSettings
{
    protected static string $resource = FreightChargeResource::class;
}
