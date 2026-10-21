<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Employees\Pages;

use App\Models\User;
use Filament\Resources\Pages\Page;
use Modules\MasterData\Filament\Resources\Employees\EmployeeResource;

/** Pencil of 02. 従業員マスタ: the employee is changed on their user screen in ユーザー管理 (administrators only). */
final class EditEmployee extends Page
{
    protected static string $resource = EmployeeResource::class;

    public function mount(int|string $record): void
    {
        $user = EmployeeResource::resolveRecordRouteBinding($record);
        abort_unless($user instanceof User, 404);
        abort_unless(EmployeeResource::canEdit($user), 403);

        $this->redirect(EmployeeResource::settingsEditUrl($user));
    }
}
