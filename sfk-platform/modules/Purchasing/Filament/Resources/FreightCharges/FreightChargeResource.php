<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\FreightCharges;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\Purchasing\Filament\Resources\FreightCharges\Pages\CreateFreightCharge;
use Modules\Purchasing\Filament\Resources\FreightCharges\Pages\EditFreightCharge;
use Modules\Purchasing\Filament\Resources\FreightCharges\Pages\FreightChargeListViewSettings;
use Modules\Purchasing\Filament\Resources\FreightCharges\Pages\FreightChargeSettings;
use Modules\Purchasing\Filament\Resources\FreightCharges\Pages\ListFreightCharges;
use Modules\Purchasing\Filament\Resources\FreightCharges\Pages\ViewFreightCharge;
use Modules\Purchasing\Filament\Resources\FreightCharges\Tables\FreightChargesTable;
use Modules\Purchasing\Models\FreightCharge;
use UnitEnum;

/**
 * App 15. 運送請求データ取込 (SYSTEM_BRAIN APP_15) in the 白石封筒 dashboard: carriers' billing data, read from CSV files
 * and posted as purchases of 05. 納品(仕入)入力. Access follows the app's permissions and status (AppResource). Like the
 * legacy app, 一括削除 starts switched off.
 */
final class FreightChargeResource extends AppResource
{
    protected static ?string $model = FreightCharge::class;

    protected static ?string $slug = 'freight-charges';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowUp;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Purchasing->value;

    protected static ?string $navigationLabel = '運送請求データ取込';

    protected static ?string $modelLabel = '運送請求データ';

    protected static ?string $pluralModelLabel = '運送請求データ';

    protected static ?int $navigationSort = 15;

    public static function appCode(): string
    {
        return 'freight-billing';
    }

    public static function appName(): string
    {
        return '15. 運送請求データ取込';
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    public static function form(Schema $schema): Schema
    {
        return FreightChargeLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FreightChargeLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return FreightChargesTable::configure($table);
    }

    /** 「運送請求データID 12」 */
    public static function titleOf(FreightCharge $charge): string
    {
        return FreightChargeLayout::label('id').' '.$charge->id;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFreightCharges::route('/'),
            'create' => CreateFreightCharge::route('/create'),
            'views' => FreightChargeListViewSettings::route('/views'),
            'settings' => FreightChargeSettings::route('/settings'),
            'view' => ViewFreightCharge::route('/{record}'),
            'edit' => EditFreightCharge::route('/{record}/edit'),
        ];
    }
}
