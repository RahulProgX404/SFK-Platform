<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payables;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\Purchasing\Filament\Resources\Payables\Pages\EditPayable;
use Modules\Purchasing\Filament\Resources\Payables\Pages\ListPayables;
use Modules\Purchasing\Filament\Resources\Payables\Pages\PayableListViewSettings;
use Modules\Purchasing\Filament\Resources\Payables\Pages\PayableSettings;
use Modules\Purchasing\Filament\Resources\Payables\Pages\ViewPayable;
use Modules\Purchasing\Filament\Resources\Payables\Tables\PayablesTable;
use Modules\Purchasing\Models\PayableMonth;
use UnitEnum;

/**
 * App 23. 買掛管理 (SYSTEM_BRAIN APP_23B) in the 白石封筒 dashboard: 買掛 per supplier × calendar month. The months are
 * written by PayableLedger from the purchases (05) and payments (07) — the legacy month-creation, 再集計 and 一括更新
 * buttons are not needed — so there is no 新規作成 and no 削除 (a deleted month would be recalculated at once); 編集
 * changes 消費税端数調整 only. Access follows the app's permissions and status (AppResource).
 */
final class PayableResource extends AppResource
{
    protected static ?string $model = PayableMonth::class;

    protected static ?string $slug = 'payables';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Accounting->value;

    protected static ?string $navigationLabel = '買掛管理';

    protected static ?string $modelLabel = '買掛';

    protected static ?string $pluralModelLabel = '買掛';

    protected static ?int $navigationSort = 24;

    public static function appCode(): string
    {
        return 'payable';
    }

    public static function appName(): string
    {
        return '23. 買掛管理';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PayableLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PayableLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return PayablesTable::configure($table);
    }

    /** 「仕入先名 2026年10月」 */
    public static function titleOf(PayableMonth $month): string
    {
        return $month->supplier->name.' '.$month->monthLabel();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayables::route('/'),
            'views' => PayableListViewSettings::route('/views'),
            'settings' => PayableSettings::route('/settings'),
            'view' => ViewPayable::route('/{record}'),
            'edit' => EditPayable::route('/{record}/edit'),
        ];
    }
}
