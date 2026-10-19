<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Orders;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\Orders\Filament\Resources\Orders\Pages\CreateOrder;
use Modules\Orders\Filament\Resources\Orders\Pages\EditOrder;
use Modules\Orders\Filament\Resources\Orders\Pages\ListOrders;
use Modules\Orders\Filament\Resources\Orders\Pages\OrderListViewSettings;
use Modules\Orders\Filament\Resources\Orders\Pages\OrderSettings;
use Modules\Orders\Filament\Resources\Orders\Pages\ViewOrder;
use Modules\Orders\Filament\Resources\Orders\Schemas\OrderForm;
use Modules\Orders\Filament\Resources\Orders\Schemas\OrderInfolist;
use Modules\Orders\Filament\Resources\Orders\Tables\OrdersTable;
use Modules\Orders\Models\Order;
use UnitEnum;

/**
 * App 04. 受注入力 (SYSTEM_BRAIN APP_04, legacy app 9) in the 白石封筒 dashboard. Access follows the app's permissions
 * and status (AppResource): it appears on アプリ設定 as 開発中, an administrator places it in a space, and employees
 * then use it per 閲覧・追加・編集・削除. Like the legacy template, 一括削除 starts switched off.
 */
final class OrderResource extends AppResource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Orders->value;

    protected static ?string $navigationLabel = '受注入力';

    protected static ?string $modelLabel = '受注';

    protected static ?string $pluralModelLabel = '受注';

    protected static ?string $recordTitleAttribute = 'order_number';

    protected static ?int $navigationSort = 4;

    public static function appCode(): string
    {
        return 'order-entry';
    }

    public static function appName(): string
    {
        return '04. 受注入力';
    }

    /** SYSTEM_BRAIN APP_04 README (purpose of the app). */
    public static function appDescription(): string
    {
        return "受注入力アプリ\n受注情報の入力・管理を行うアプリです。\nこのアプリで「作業明細表」の帳票作成が可能です。";
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['order_number', 'product_name'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'views' => OrderListViewSettings::route('/views'),
            'settings' => OrderSettings::route('/settings'),
            'view' => ViewOrder::route('/{record}'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
