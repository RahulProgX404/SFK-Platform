<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Machines;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\MasterData\Filament\Resources\Machines\Pages\CreateMachine;
use Modules\MasterData\Filament\Resources\Machines\Pages\EditMachine;
use Modules\MasterData\Filament\Resources\Machines\Pages\ListMachines;
use Modules\MasterData\Filament\Resources\Machines\Pages\MachineListViewSettings;
use Modules\MasterData\Filament\Resources\Machines\Pages\MachineSettings;
use Modules\MasterData\Filament\Resources\Machines\Pages\ViewMachine;
use Modules\MasterData\Filament\Resources\Machines\Schemas\MachineForm;
use Modules\MasterData\Filament\Resources\Machines\Schemas\MachineInfolist;
use Modules\MasterData\Filament\Resources\Machines\Tables\MachinesTable;
use Modules\MasterData\Models\Machine;
use UnitEnum;

/**
 * App 03. 機械マスタ (SYSTEM_BRAIN APP_03) in the 白石封筒 dashboard. Access follows the app's permissions and status
 * (AppResource): it appears on アプリ設定 as 開発中, an administrator places it in a space, and employees then use it
 * per 閲覧・追加・編集・削除・ファイル書き出し. Like the legacy app, 一括削除 starts switched off.
 */
final class MachineResource extends AppResource
{
    protected static ?string $model = Machine::class;

    protected static ?string $slug = 'machines';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MasterData->value;

    protected static ?string $navigationLabel = '機械マスタ';

    protected static ?string $modelLabel = '機械';

    protected static ?string $pluralModelLabel = '機械';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    public static function appCode(): string
    {
        return 'machine-master';
    }

    public static function appName(): string
    {
        return '03. 機械マスタ';
    }

    /** SYSTEM_BRAIN APP_03 README (purpose of the app). */
    public static function appDescription(): string
    {
        return "機械マスタアプリ\n機械の情報を登録・管理するアプリです。";
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    public static function form(Schema $schema): Schema
    {
        return MachineForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MachineInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MachinesTable::configure($table);
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMachines::route('/'),
            'create' => CreateMachine::route('/create'),
            'views' => MachineListViewSettings::route('/views'),
            'settings' => MachineSettings::route('/settings'),
            'view' => ViewMachine::route('/{record}'),
            'edit' => EditMachine::route('/{record}/edit'),
        ];
    }
}
