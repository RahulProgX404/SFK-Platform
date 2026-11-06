<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Employees\Pages;

use App\Models\User;
use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Core\Filament\Users\UserDetailWidgets;
use Modules\MasterData\Filament\Resources\Employees\EmployeeResource;

/** @property User $record */
final class ViewEmployee extends AppViewRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    /** Cards of other modules about this person (Core UserDetailWidgets, e.g. 個人の概要), above the record (not printed). */
    protected function getHeaderWidgets(): array
    {
        return $this->printMode ? [] : app(UserDetailWidgets::class)->for($this->record);
    }

    public function getHeaderWidgetsColumns(): int
    {
        return 1;
    }
}
