<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Invoices;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\Sales\Filament\Resources\Invoices\Pages\CreateInvoice;
use Modules\Sales\Filament\Resources\Invoices\Pages\EditInvoice;
use Modules\Sales\Filament\Resources\Invoices\Pages\InvoiceListViewSettings;
use Modules\Sales\Filament\Resources\Invoices\Pages\InvoiceSettings;
use Modules\Sales\Filament\Resources\Invoices\Pages\ListInvoices;
use Modules\Sales\Filament\Resources\Invoices\Pages\ViewInvoice;
use Modules\Sales\Filament\Resources\Invoices\Tables\InvoicesTable;
use Modules\Sales\Models\Invoice;
use UnitEnum;

/**
 * App 11. 請求書管理(売上) (SYSTEM_BRAIN APP_11) in the 白石封筒 dashboard: invoices are made from 06. 売上入力 (請求書を作成,
 * with the balances of 23-2) and can be corrected or typed here. Access follows the app's permissions and status
 * (AppResource). Like the legacy app, 一括削除 starts switched off.
 */
final class InvoiceResource extends AppResource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $slug = 'invoices';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyYen;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Sales->value;

    protected static ?string $navigationLabel = '請求書管理(売上)';

    protected static ?string $modelLabel = '請求書';

    protected static ?string $pluralModelLabel = '請求書';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?int $navigationSort = 11;

    public static function appCode(): string
    {
        return 'invoice';
    }

    public static function appName(): string
    {
        return '11. 請求書管理(売上)';
    }

    /** SYSTEM_BRAIN APP_11 README (app description in the legacy system). */
    public static function appDescription(): string
    {
        return "請求書管理アプリ\n請求書を作成するためのアプリです。";
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    public static function form(Schema $schema): Schema
    {
        return InvoiceLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InvoiceLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['number'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'views' => InvoiceListViewSettings::route('/views'),
            'settings' => InvoiceSettings::route('/settings'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }
}
