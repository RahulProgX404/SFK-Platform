<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Purchases;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\Purchasing\Filament\Resources\Purchases\Pages\CreatePurchase;
use Modules\Purchasing\Filament\Resources\Purchases\Pages\EditPurchase;
use Modules\Purchasing\Filament\Resources\Purchases\Pages\ListPurchases;
use Modules\Purchasing\Filament\Resources\Purchases\Pages\PurchaseListViewSettings;
use Modules\Purchasing\Filament\Resources\Purchases\Pages\PurchaseSettings;
use Modules\Purchasing\Filament\Resources\Purchases\Pages\ViewPurchase;
use Modules\Purchasing\Filament\Resources\Purchases\Tables\PurchasesTable;
use Modules\Purchasing\Models\Purchase;
use UnitEnum;

/**
 * App 05. 納品(仕入)入力 (SYSTEM_BRAIN APP_05) in the 白石封筒 dashboard: supplier deliveries, numbered (BR-PU-01), counted
 * in their job's 仕入 costs and in the supplier's 買掛 (23. 買掛管理) on every save and delete by anyone — the legacy sync
 * ran for one user only (D-05). Access follows the app's permissions and status (AppResource).
 */
final class PurchaseResource extends AppResource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $slug = 'purchases';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Purchasing->value;

    protected static ?string $navigationLabel = '納品(仕入)入力';

    protected static ?string $modelLabel = '納品(仕入)';

    protected static ?string $pluralModelLabel = '納品(仕入)';

    protected static ?string $recordTitleAttribute = 'purchase_number';

    protected static ?int $navigationSort = 5;

    public static function appCode(): string
    {
        return 'purchase-entry';
    }

    public static function appName(): string
    {
        return '05. 納品(仕入)入力';
    }

    /** SYSTEM_BRAIN APP_05 README (app description in the legacy system). */
    public static function appDescription(): string
    {
        return "納品(仕入)入力アプリ\n仕入処理に使用するアプリです。";
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchasesTable::configure($table);
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['purchase_number', 'product_name'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
            'create' => CreatePurchase::route('/create'),
            'views' => PurchaseListViewSettings::route('/views'),
            'settings' => PurchaseSettings::route('/settings'),
            'view' => ViewPurchase::route('/{record}'),
            'edit' => EditPurchase::route('/{record}/edit'),
        ];
    }
}
