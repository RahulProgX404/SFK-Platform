<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\SalesPlans;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Analytics\Filament\Resources\SalesPlans\Pages\CreateSalesPlan;
use Modules\Analytics\Filament\Resources\SalesPlans\Pages\EditSalesPlan;
use Modules\Analytics\Filament\Resources\SalesPlans\Pages\ListSalesPlans;
use Modules\Analytics\Filament\Resources\SalesPlans\Pages\SalesPlanListViewSettings;
use Modules\Analytics\Filament\Resources\SalesPlans\Pages\SalesPlanSettings;
use Modules\Analytics\Filament\Resources\SalesPlans\Pages\ViewSalesPlan;
use Modules\Analytics\Filament\Resources\SalesPlans\Tables\SalesPlansTable;
use Modules\Analytics\Models\SalesPlan;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use UnitEnum;

/**
 * App 25. 販売先別売上計画 (SYSTEM_BRAIN APP_25) in the 白石封筒 dashboard: plan and actual per customer and business year
 * (May–April); the actuals can be taken from 06. 売上入力 (売上実績を反映). Access follows the app's permissions and status
 * (AppResource).
 */
final class SalesPlanResource extends AppResource
{
    protected static ?string $model = SalesPlan::class;

    protected static ?string $slug = 'sales-plans';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Analytics->value;

    protected static ?string $navigationLabel = '販売先別売上計画';

    protected static ?string $modelLabel = '売上計画';

    protected static ?string $pluralModelLabel = '売上計画';

    protected static ?int $navigationSort = 25;

    public static function appCode(): string
    {
        return 'sales-plan';
    }

    public static function appName(): string
    {
        return '25. 販売先別売上計画';
    }

    public static function form(Schema $schema): Schema
    {
        return SalesPlanLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesPlanLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesPlansTable::configure($table);
    }

    /** 「得意先名 2025年05月」 */
    public static function titleOf(SalesPlan $plan): string
    {
        return trim(($plan->customer->name ?? '').' '.($plan->yearLabel() ?? '')) ?: SalesPlanLayout::label('id').' '.$plan->id;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesPlans::route('/'),
            'create' => CreateSalesPlan::route('/create'),
            'views' => SalesPlanListViewSettings::route('/views'),
            'settings' => SalesPlanSettings::route('/settings'),
            'view' => ViewSalesPlan::route('/{record}'),
            'edit' => EditSalesPlan::route('/{record}/edit'),
        ];
    }
}
