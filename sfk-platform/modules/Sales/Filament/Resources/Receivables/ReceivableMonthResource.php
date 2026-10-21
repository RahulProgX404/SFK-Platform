<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Filament\Resources\Receivables\Tables\ReceivablesTable;
use Modules\Sales\Models\ReceivableMonth;
use UnitEnum;

/**
 * The screens 23. 売掛管理 and 23-2. 売掛管理(締日別) share, over the `receivable_months` of their basis. The months are
 * written by ReceivableLedger from the sales and receipts (the legacy month-creation and 一括更新 buttons are not needed),
 * so there is no 新規作成 and no 削除 (a deleted month would be recalculated at once); 編集 changes 消費税端数調整 only.
 */
abstract class ReceivableMonthResource extends AppResource
{
    protected static ?string $model = ReceivableMonth::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Sales->value;

    protected static ?string $modelLabel = '売掛';

    protected static ?string $pluralModelLabel = '売掛';

    abstract public static function basis(): ReceivableBasis;

    /** @return Builder<ReceivableMonth> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ReceivableMonth> $query */
        $query = parent::getEloquentQuery();

        return $query->ofBasis(static::basis());
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
        return ReceivableLayout::form($schema, static::basis());
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReceivableLayout::infolist($schema, static::basis());
    }

    public static function table(Table $table): Table
    {
        return ReceivablesTable::configure($table, static::basis());
    }

    /** 「得意先名 2026年10月」 */
    public static function titleOf(ReceivableMonth $month): string
    {
        return $month->customer->name.' '.$month->monthLabel();
    }
}
