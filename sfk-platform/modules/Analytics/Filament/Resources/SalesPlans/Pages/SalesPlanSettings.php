<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\SalesPlans\Pages;

use Modules\Analytics\Filament\Resources\SalesPlans\SalesPlanResource;
use Modules\Core\Filament\AppPages\AppSettings;

/** アプリの設定 → 設定 of 25. 販売先別売上計画. */
final class SalesPlanSettings extends AppSettings
{
    protected static string $resource = SalesPlanResource::class;
}
