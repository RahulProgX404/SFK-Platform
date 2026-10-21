<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Employees\Schemas;

use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Modules\Core\Access\EmployeeDataAccess;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\MasterData\Domain\Employees\EmployeeFields;
use Modules\MasterData\Filament\Resources\Employees\EmployeeLayout;
use Modules\MasterData\Filament\Resources\Employees\EmployeeResource;
use Modules\MasterData\Filament\Resources\Employees\Tables\EmployeesTable;

/**
 * Record page of 02. 従業員マスタ, laid out like the legacy record (EmployeeLayout). 作業単価 / 残業単価 are shown only
 * to administrators and the employee themselves (EmployeeDataAccess).
 */
final class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components(EmployeeLayout::components(
                self::entry(...),
                fn (?User $record): bool => EmployeeDataAccess::canViewRates(EmployeeResource::actor(), $record),
            ));
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(EmployeeLayout::label($name))->placeholder('—');

        return match (true) {
            $name === 'name_kana' => $entry->state(fn (User $record): string => EmployeesTable::nameKana($record)),
            $name === 'employee_categories' => $entry->state(fn (User $record): string => EmployeesTable::categoryNames($record)),
            $name === 'employment_status' => $entry->formatStateUsing(fn (mixed $state): string => $state instanceof EmploymentStatus ? $state->label() : ''),
            in_array($name, EmployeeFields::RATES, true) => $entry
                // hidden from serialization, so read from the attributes
                ->state(fn (User $record): mixed => $record->getAttribute($name))
                ->formatStateUsing(fn (mixed $state): string => EmployeeFields::formatRate($state)),
            $name === 'created_at', $name === 'updated_at' => $entry->dateTime('Y-m-d H:i'),
            default => $entry,
        };
    }
}
