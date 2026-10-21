<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\ConsumableOrders;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\Pages\ConsumableOrderListViewSettings;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\Pages\ConsumableOrderSettings;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\Pages\CreateConsumableOrder;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\Pages\EditConsumableOrder;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\Pages\ListConsumableOrders;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\Pages\ViewConsumableOrder;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\Tables\ConsumableOrdersTable;
use Modules\Purchasing\Models\ConsumableOrder;
use UnitEnum;

/**
 * App 20. 消耗品発注 (SYSTEM_BRAIN APP_20) in the 白石封筒 dashboard: purchase orders of consumables with the legacy
 * プロセス管理 未処理 → 確認中 → 承認済 (BR-PU-09, ConsumableOrderWorkflow; administrators approve); the 発注書 is printed
 * with 印刷する. A row's 納品日 puts its 数量 into the stock of 19. 消耗品マスタ (ConsumableReceipt). Access
 * follows the app's permissions and status (AppResource). Like the legacy app, 一括削除 starts switched off.
 */
final class ConsumableOrderResource extends AppResource
{
    protected static ?string $model = ConsumableOrder::class;

    protected static ?string $slug = 'consumable-orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Purchasing->value;

    protected static ?string $navigationLabel = '消耗品発注';

    protected static ?string $modelLabel = '発注';

    protected static ?string $pluralModelLabel = '発注';

    protected static ?int $navigationSort = 20;

    public static function appCode(): string
    {
        return 'consumable-order';
    }

    public static function appName(): string
    {
        return '20. 消耗品発注';
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    public static function form(Schema $schema): Schema
    {
        return ConsumableOrderLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConsumableOrderLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return ConsumableOrdersTable::configure($table);
    }

    /** 「レコード番号 12」 */
    public static function titleOf(ConsumableOrder $order): string
    {
        return ConsumableOrderLayout::label('id').' '.$order->id;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsumableOrders::route('/'),
            'create' => CreateConsumableOrder::route('/create'),
            'views' => ConsumableOrderListViewSettings::route('/views'),
            'settings' => ConsumableOrderSettings::route('/settings'),
            'view' => ViewConsumableOrder::route('/{record}'),
            'edit' => EditConsumableOrder::route('/{record}/edit'),
        ];
    }
}
