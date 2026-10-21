<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\SalesPlans\Pages;

use Modules\Analytics\Filament\Resources\SalesPlans\SalesPlanResource;
use Modules\Core\Filament\AppPages\AppListViewSettings;

/** アプリの設定 → 一覧 of 25. 販売先別売上計画. */
final class SalesPlanListViewSettings extends AppListViewSettings
{
    protected static string $resource = SalesPlanResource::class;
}
