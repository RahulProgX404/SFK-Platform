<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Employees\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\MasterData\Filament\Resources\Employees\EmployeeResource;

/** アプリの設定 → 一覧 of 02. 従業員マスタ. */
final class EmployeeListViewSettings extends AppListViewSettings
{
    protected static string $resource = EmployeeResource::class;
}
