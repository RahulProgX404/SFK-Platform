<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Consumables;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\MasterData\Filament\Resources\Consumables\Pages\ConsumableListViewSettings;
use Modules\MasterData\Filament\Resources\Consumables\Pages\ConsumableSettings;
use Modules\MasterData\Filament\Resources\Consumables\Pages\CreateConsumable;
use Modules\MasterData\Filament\Resources\Consumables\Pages\EditConsumable;
use Modules\MasterData\Filament\Resources\Consumables\Pages\ListConsumables;
use Modules\MasterData\Filament\Resources\Consumables\Pages\ViewConsumable;
use Modules\MasterData\Filament\Resources\Consumables\Schemas\ConsumableForm;
use Modules\MasterData\Filament\Resources\Consumables\Schemas\ConsumableInfolist;
use Modules\MasterData\Filament\Resources\Consumables\Tables\ConsumablesTable;
use Modules\MasterData\Models\Consumable;
use UnitEnum;

/**
 * App 19. 消耗品マスタ (SYSTEM_BRAIN APP_19) in the 白石封筒 dashboard. Access follows the app's permissions and status
 * (AppResource). Like the legacy app, 一括削除 starts switched off. Records carry 在庫数 (Consumable::withStock()).
 */
final class ConsumableResource extends AppResource
{
    protected static ?string $model = Consumable::class;

    protected static ?string $slug = 'consumables';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MasterData->value;

    protected static ?string $navigationLabel = '消耗品マスタ';

    protected static ?string $modelLabel = '消耗品';

    protected static ?string $pluralModelLabel = '消耗品';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 19;

    public static function appCode(): string
    {
        return 'consumables-master';
    }

    public static function appName(): string
    {
        return '19. 消耗品マスタ';
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    public static function form(Schema $schema): Schema
    {
        return ConsumableForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConsumableInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConsumablesTable::configure($table);
    }

    /** @return Builder<Consumable> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Consumable> $query */
        $query = parent::getEloquentQuery();

        return $query->withStock();
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsumables::route('/'),
            'create' => CreateConsumable::route('/create'),
            'views' => ConsumableListViewSettings::route('/views'),
            'settings' => ConsumableSettings::route('/settings'),
            'view' => ViewConsumable::route('/{record}'),
            'edit' => EditConsumable::route('/{record}/edit'),
        ];
    }
}
