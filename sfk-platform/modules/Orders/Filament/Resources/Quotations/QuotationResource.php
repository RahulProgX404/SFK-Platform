<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Quotations;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\Orders\Filament\Resources\Quotations\Pages\CreateQuotation;
use Modules\Orders\Filament\Resources\Quotations\Pages\EditQuotation;
use Modules\Orders\Filament\Resources\Quotations\Pages\ListQuotations;
use Modules\Orders\Filament\Resources\Quotations\Pages\QuotationListViewSettings;
use Modules\Orders\Filament\Resources\Quotations\Pages\QuotationSettings;
use Modules\Orders\Filament\Resources\Quotations\Pages\ViewQuotation;
use Modules\Orders\Filament\Resources\Quotations\Tables\QuotationsTable;
use Modules\Orders\Models\Quotation;
use UnitEnum;

/**
 * App 09. 見積書管理 (SYSTEM_BRAIN APP_09) in the 白石封筒 dashboard: quotations with quantity tiers; the quotation sheet is
 * printed with 印刷する and kept as 見積書PDF. Access follows the app's permissions and status (AppResource).
 */
final class QuotationResource extends AppResource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $slug = 'quotations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Orders->value;

    protected static ?string $navigationLabel = '見積書管理';

    protected static ?string $modelLabel = '見積書';

    protected static ?string $pluralModelLabel = '見積書';

    protected static ?string $recordTitleAttribute = 'quote_number';

    protected static ?int $navigationSort = 9;

    public static function appCode(): string
    {
        return 'quotation';
    }

    public static function appName(): string
    {
        return '09. 見積書管理';
    }

    public static function form(Schema $schema): Schema
    {
        return QuotationLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return QuotationLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return QuotationsTable::configure($table);
    }

    /** 見積書番号, else 「見積書管理ID 12」 */
    public static function titleOf(Quotation $quotation): string
    {
        return filled($quotation->quote_number) ? (string) $quotation->quote_number : QuotationLayout::label('id').' '.$quotation->id;
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['quote_number', 'product_name'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuotations::route('/'),
            'create' => CreateQuotation::route('/create'),
            'views' => QuotationListViewSettings::route('/views'),
            'settings' => QuotationSettings::route('/settings'),
            'view' => ViewQuotation::route('/{record}'),
            'edit' => EditQuotation::route('/{record}/edit'),
        ];
    }
}
