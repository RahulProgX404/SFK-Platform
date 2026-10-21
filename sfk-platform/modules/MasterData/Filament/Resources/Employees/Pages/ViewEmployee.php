<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Employees\Pages;

use App\Models\User;
use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\MasterData\Filament\Resources\Employees\EmployeeResource;

/** @property User $record */
final class ViewEmployee extends AppViewRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }
}
