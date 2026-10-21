<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\PastSales;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Analytics\Filament\Resources\PastSales\Pages\CreatePastSale;
use Modules\Analytics\Filament\Resources\PastSales\Pages\EditPastSale;
use Modules\Analytics\Filament\Resources\PastSales\Pages\ListPastSales;
use Modules\Analytics\Filament\Resources\PastSales\Pages\PastSaleListViewSettings;
use Modules\Analytics\Filament\Resources\PastSales\Pages\PastSaleSettings;
use Modules\Analytics\Filament\Resources\PastSales\Pages\ViewPastSale;
use Modules\Analytics\Filament\Resources\PastSales\Tables\PastSalesTable;
use Modules\Analytics\Models\PastSale;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use UnitEnum;

/**
 * App 22. 過去の売上データ (SYSTEM_BRAIN APP_22) in the 白石封筒 dashboard: historical sales per 担当者 and business year, with
 * the legacy graphs 年間売上 and 担当者別売上比較 as views. The development copy 22D is not rebuilt. Access follows the
 * app's permissions and status (AppResource).
 */
final class PastSaleResource extends AppResource
{
    protected static ?string $model = PastSale::class;

    protected static ?string $slug = 'past-sales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Analytics->value;

    protected static ?string $navigationLabel = '過去の売上データ';

    protected static ?string $modelLabel = '過去の売上';

    protected static ?string $pluralModelLabel = '過去の売上';

    protected static ?int $navigationSort = 22;

    public static function appCode(): string
    {
        return 'past-sales';
    }

    public static function appName(): string
    {
        return '22. 過去の売上データ';
    }

    public static function form(Schema $schema): Schema
    {
        return PastSaleLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PastSaleLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return PastSalesTable::configure($table);
    }

    /** 「担当者 2024年」 */
    public static function titleOf(PastSale $sale): string
    {
        return trim(($sale->salesperson ?? '').' '.($sale->fiscalYear() ?? '')) ?: PastSaleLayout::label('id').' '.$sale->id;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPastSales::route('/'),
            'create' => CreatePastSale::route('/create'),
            'views' => PastSaleListViewSettings::route('/views'),
            'settings' => PastSaleSettings::route('/settings'),
            'view' => ViewPastSale::route('/{record}'),
            'edit' => EditPastSale::route('/{record}/edit'),
        ];
    }
}
