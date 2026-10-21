<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Employees\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\MasterData\Filament\Resources\Employees\EmployeeResource;

/** アプリの設定 → 設定 of 02. 従業員マスタ. */
final class EmployeeSettings extends AppSettings
{
    protected static string $resource = EmployeeResource::class;
}
