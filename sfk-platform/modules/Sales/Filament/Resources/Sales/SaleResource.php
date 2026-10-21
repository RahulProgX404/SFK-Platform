<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Sales;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\Sales\Filament\Resources\Sales\Pages\CreateSale;
use Modules\Sales\Filament\Resources\Sales\Pages\EditSale;
use Modules\Sales\Filament\Resources\Sales\Pages\ListSales;
use Modules\Sales\Filament\Resources\Sales\Pages\SaleListViewSettings;
use Modules\Sales\Filament\Resources\Sales\Pages\SaleSettings;
use Modules\Sales\Filament\Resources\Sales\Pages\ViewSale;
use Modules\Sales\Filament\Resources\Sales\Schemas\SaleForm;
use Modules\Sales\Filament\Resources\Sales\Schemas\SaleInfolist;
use Modules\Sales\Filament\Resources\Sales\Tables\SalesTable;
use Modules\Sales\Models\Sale;
use UnitEnum;

/**
 * App 06. 売上入力 (SYSTEM_BRAIN APP_06) in the 白石封筒 dashboard. Access follows the app's permissions and status
 * (AppResource); a sale set to 編集 「不可」 is changed and deleted by administrators only (BR-SA-16). Like the legacy app,
 * 一括削除 starts switched off.
 */
final class SaleResource extends AppResource
{
    protected static ?string $model = Sale::class;

    protected static ?string $slug = 'sales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Sales->value;

    protected static ?string $navigationLabel = '売上入力';

    protected static ?string $modelLabel = '売上';

    protected static ?string $pluralModelLabel = '売上';

    protected static ?string $recordTitleAttribute = 'sales_number';

    protected static ?int $navigationSort = 6;

    public static function appCode(): string
    {
        return 'sales-entry';
    }

    public static function appName(): string
    {
        return '06. 売上入力';
    }

    /** SYSTEM_BRAIN APP_06 README (app description in the legacy system). */
    public static function appDescription(): string
    {
        return "売上入力アプリ\n注番に対する売上処理に使用するアプリです。\n受注入力アプリからショートカット登録も可能です。";
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    public static function form(Schema $schema): Schema
    {
        return SaleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SaleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesTable::configure($table);
    }

    /** 編集 「不可」: administrators only (BR-SA-16). */
    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && self::unlockedFor($record);
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && self::unlockedFor($record);
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['sales_number', 'product_name'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSales::route('/'),
            'create' => CreateSale::route('/create'),
            'views' => SaleListViewSettings::route('/views'),
            'settings' => SaleSettings::route('/settings'),
            'view' => ViewSale::route('/{record}'),
            'edit' => EditSale::route('/{record}/edit'),
        ];
    }

    private static function unlockedFor(Model $record): bool
    {
        return ! ($record instanceof Sale && $record->locked) || SaleForm::isAdministrator();
    }
}
