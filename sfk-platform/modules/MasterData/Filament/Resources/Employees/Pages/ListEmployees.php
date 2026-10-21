<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Employees\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\MasterData\Filament\Resources\Employees\EmployeeResource;
use Modules\MasterData\Filament\Resources\Employees\Tables\EmployeesTable;

/** No inlineFields(): the pencil opens the edit page, which goes to ユーザー管理. */
final class ListEmployees extends AppListRecords
{
    protected static string $resource = EmployeeResource::class;

    public function listColumns(): array
    {
        return EmployeesTable::columns();
    }

    /** The legacy view 在籍中の従業員一覧 (SYSTEM_BRAIN APP_02 views.md). */
    public function defaultListViews(): array
    {
        return EmployeesTable::defaultViews();
    }

    public function filterFields(): array
    {
        return EmployeesTable::filterFields();
    }
}
