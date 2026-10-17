<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Machines\Schemas;

use App\Models\User;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use LogicException;
use Modules\MasterData\Domain\Machines\MachineFields;
use Modules\MasterData\Filament\Resources\Machines\MachineLayout;

/**
 * Create / edit form of 03. 機械マスタ, laid out like the legacy form (MachineLayout). 機械コード and 機械名 are unique
 * (APP_03); 機械名 is also required, as the record title every lookup uses. The rates are yen with up to 4 decimals.
 */
final class MachineForm
{
    public static function configure(Schema $schema): Schema
    {
        $superAdmin = auth()->user() instanceof User && auth()->user()->isSuperAdmin();

        return $schema
            ->columns(1)
            ->components(MachineLayout::components(self::input(...), withFooter: false, withDeveloperFields: $superAdmin));
    }

    private static function input(string $name): Component
    {
        $label = MachineLayout::label($name);

        return match (true) {
            $name === 'code' => TextInput::make($name)->label($label)->maxLength(64)->unique(ignoreRecord: true),
            $name === 'name' => TextInput::make($name)->label($label)->required()->maxLength(64)->unique(ignoreRecord: true),
            in_array($name, MachineFields::RATES, true) => TextInput::make($name)
                ->label($label)
                ->numeric()
                ->minValue(0)
                ->maxValue(9999999999)
                ->step('any')
                ->rule('decimal:0,4')
                ->prefix('¥'),
            $name === 'notes' => Textarea::make($name)->label($label)->rows(3)->maxLength(4000),
            $name === 'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => throw new LogicException("No form input for [{$name}]."),
        };
    }
}
