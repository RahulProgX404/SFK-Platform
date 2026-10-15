<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Positions;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Models\Position;
use Modules\Settings\Filament\NavigationGroup;
use Modules\Settings\Filament\Resources\Positions\Pages\ManagePositions;
use Modules\Settings\Filament\Support\OptionListResource;
use UnitEnum;

/** 役職: the options of the user's optional 役職 (company designation) dropdown. */
final class PositionResource extends OptionListResource
{
    protected static ?string $model = Position::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Users->value;

    protected static ?string $navigationLabel = '役職';

    protected static ?string $modelLabel = '役職';

    protected static ?string $pluralModelLabel = '役職';

    protected static ?int $navigationSort = 3;

    public static function optionLabel(): string
    {
        return '役職';
    }

    public static function optionExample(): string
    {
        return '例：課長';
    }

    public static function createOption(array $data): Position
    {
        return Position::createAtEnd((string) $data['name'], (bool) ($data['is_active'] ?? true));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePositions::route('/'),
        ];
    }
}
