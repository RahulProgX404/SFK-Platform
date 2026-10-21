<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Employees\Pages;

use Filament\Resources\Pages\Page;
use Modules\MasterData\Filament\Resources\Employees\EmployeeResource;

/** ＋ of 02. 従業員マスタ: employees are registered in ユーザー管理 (administrators only), so this page goes there. */
final class CreateEmployee extends Page
{
    protected static string $resource = EmployeeResource::class;

    public function mount(): void
    {
        abort_unless(EmployeeResource::canCreate(), 403);

        $this->redirect(EmployeeResource::settingsCreateUrl());
    }
}
