<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receipts;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\Sales\Filament\Resources\Receipts\Pages\CreateReceipt;
use Modules\Sales\Filament\Resources\Receipts\Pages\EditReceipt;
use Modules\Sales\Filament\Resources\Receipts\Pages\ListReceipts;
use Modules\Sales\Filament\Resources\Receipts\Pages\ReceiptListViewSettings;
use Modules\Sales\Filament\Resources\Receipts\Pages\ReceiptSettings;
use Modules\Sales\Filament\Resources\Receipts\Pages\ViewReceipt;
use Modules\Sales\Filament\Resources\Receipts\Tables\ReceiptsTable;
use Modules\Sales\Models\Receipt;
use UnitEnum;

/**
 * App 21. 売入金管理（売上） (SYSTEM_BRAIN APP_21) in the 白石封筒 dashboard: the record of an invoice is made with the
 * invoice (BR-SA-13); the 入金明細 entered here settle it and recalculate 23 / 23-2 (BR-AR-04/06). Access follows the
 * app's permissions and status (AppResource). Like the legacy app, 一括削除 starts switched off.
 */
final class ReceiptResource extends AppResource
{
    protected static ?string $model = Receipt::class;

    protected static ?string $slug = 'receipts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Sales->value;

    protected static ?string $navigationLabel = '売入金管理（売上）';

    protected static ?string $modelLabel = '入金';

    protected static ?string $pluralModelLabel = '入金';

    protected static ?int $navigationSort = 21;

    public static function appCode(): string
    {
        return 'receipt';
    }

    public static function appName(): string
    {
        return '21. 売入金管理（売上）';
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    public static function form(Schema $schema): Schema
    {
        return ReceiptLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReceiptLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return ReceiptsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReceipts::route('/'),
            'create' => CreateReceipt::route('/create'),
            'views' => ReceiptListViewSettings::route('/views'),
            'settings' => ReceiptSettings::route('/settings'),
            'view' => ViewReceipt::route('/{record}'),
            'edit' => EditReceipt::route('/{record}/edit'),
        ];
    }
}
