<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\AlcoholChecks;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages\AlcoholCheckListViewSettings;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages\AlcoholCheckSettings;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages\CreateAlcoholCheck;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages\EditAlcoholCheck;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages\ListAlcoholChecks;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages\ViewAlcoholCheck;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\Tables\AlcoholChecksTable;
use Modules\HumanResources\Models\AlcoholCheck;
use UnitEnum;

/**
 * App 18. アルコールチェック (SYSTEM_BRAIN APP_18) in the 白石封筒 dashboard: the drivers' alcohol checks before and after
 * driving (安全運転管理者による酒気帯び確認; the records are kept). Access follows the app's permissions and status
 * (AppResource). 一括削除 starts switched off.
 */
final class AlcoholCheckResource extends AppResource
{
    protected static ?string $model = AlcoholCheck::class;

    protected static ?string $slug = 'alcohol-checks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources->value;

    protected static ?string $navigationLabel = 'アルコールチェック';

    protected static ?string $modelLabel = 'アルコールチェック';

    protected static ?string $pluralModelLabel = 'アルコールチェック';

    protected static ?int $navigationSort = 18;

    public static function appCode(): string
    {
        return 'alcohol-check';
    }

    public static function appName(): string
    {
        return '18. アルコールチェック';
    }

    /** SYSTEM_BRAIN APP_18 README (app description in the legacy system). */
    public static function appDescription(): string
    {
        return 'アルコールチェックアプリ';
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    public static function form(Schema $schema): Schema
    {
        return AlcoholCheckLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AlcoholCheckLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return AlcoholChecksTable::configure($table);
    }

    /** 「2026-10-21 氏名」 */
    public static function titleOf(AlcoholCheck $check): string
    {
        return $check->checked_on->format('Y-m-d').' '.$check->driver->name;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAlcoholChecks::route('/'),
            'create' => CreateAlcoholCheck::route('/create'),
            'views' => AlcoholCheckListViewSettings::route('/views'),
            'settings' => AlcoholCheckSettings::route('/settings'),
            'view' => ViewAlcoholCheck::route('/{record}'),
            'edit' => EditAlcoholCheck::route('/{record}/edit'),
        ];
    }
}
