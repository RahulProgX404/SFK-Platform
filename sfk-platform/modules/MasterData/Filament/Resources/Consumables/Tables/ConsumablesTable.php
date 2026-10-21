<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Consumables\Tables;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Filters\FilterCondition;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Domain\Money\UnitPrice;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Domain\Consumables\BoxType;
use Modules\MasterData\Domain\Consumables\ConsumableFields;
use Modules\MasterData\Domain\Consumables\ConsumableKind;
use Modules\MasterData\Filament\Resources\Consumables\ConsumableLayout;
use Modules\MasterData\Filament\Resources\Consumables\ConsumableResource;
use Modules\MasterData\Filament\Resources\Consumables\Schemas\ConsumableInfolist;
use Modules\MasterData\Models\Consumable;
use Modules\MasterData\Models\Partner;

/**
 * Record list of 19. 消耗品マスタ. The legacy view 「消耗品一覧」 (SYSTEM_BRAIN APP_19 views.md: its columns, newest
 * レコード番号 first) is the default view, （すべて） shows columns() in order (the supplier's tax copies of the legacy
 * lookup are left out). The search box replaces 「消耗品一覧(検索特化)」. 在庫数 and 発注判定 come from the stock
 * movements; they sort and filter like the other fields.
 */
final class ConsumablesTable
{
    public const VIEW_LIST = 'list';

    /** Columns of the legacy view 消耗品一覧, in order. */
    public const LIST_COLUMNS = ['name', 'unit_price', 'amount', 'unit', 'stock', 'kind', 'lot_size', 'supplier_id', 'notes'];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', 'name', 'amount', 'unit_price', 'notes', 'supplier_id', 'supplier_code', 'purchase_category', 'unit', 'kind', 'stock', 'lot_size',
            'reorder_point', 'reorder_flag', 'box_type'] as $name) {
            $columns[$name] = ConsumableLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        return [ListView::table(self::VIEW_LIST, '消耗品一覧', self::LIST_COLUMNS, [['field' => 'id', 'direction' => 'desc']])];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('supplier'))
            ->columns([
                IconColumn::make('record')
                    ->label('')
                    ->state(true)
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('primary')
                    ->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(ConsumableResource::class))
            ->emptyStateIcon(Heroicon::OutlinedArchiveBox)
            ->emptyStateHeading('消耗品がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_19 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => ConsumableLayout::label($name);

        return [
            FilterField::text('name', $label('name')),
            FilterField::choice('box_type', $label('box_type'), BoxType::options()),
            FilterField::choice('kind', $label('kind'), ConsumableKind::options()),
            FilterField::number('reorder_point', $label('reorder_point')),
            FilterField::number('amount', $label('amount')),
            FilterField::number('unit_price', $label('unit_price')),
            FilterField::text('unit', $label('unit')),
            FilterField::number('stock', $label('stock'))->query(self::filterStock(...)),
            FilterField::number('lot_size', $label('lot_size')),
            FilterField::choice('supplier_id', $label('supplier_id'), fn (): array => Partner::query()->where('is_supplier', true)->orderByDesc('id')
                ->pluck('name', 'id')->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all()),
            FilterField::textarea('notes', $label('notes')),
            FilterField::choice('reorder_flag', $label('reorder_flag'), [ConsumableFields::REORDER => ConsumableFields::REORDER])->query(self::filterReorder(...)),
            FilterField::number('id', $label('id')),
            FilterField::user('created_by', $label('creator')),
            FilterField::dateTime('created_at', $label('created_at')),
            FilterField::user('updated_by', $label('updater')),
            FilterField::dateTime('updated_at', $label('updated_at')),
        ];
    }

    /**
     * Fields of インライン編集, checked like ConsumableForm (名称 required and unique). 在庫数 is not one: it moves by 入出庫.
     *
     * @return list<InlineField>
     */
    public static function inlineFields(): array
    {
        $decimal = fn (string $name): InlineField => InlineField::number($name, UnitPrice::SCALE)->minValue(0)->maxValue(9999999999);

        return [
            InlineField::text('name')->required()->maxLength(64)->unique('consumables'),
            InlineField::select('box_type', BoxType::options()),
            InlineField::select('kind', ConsumableKind::options()),
            $decimal('reorder_point'),
            $decimal('amount'),
            $decimal('unit_price'),
            InlineField::text('unit')->maxLength(64),
            $decimal('lot_size'),
            InlineField::textarea('notes'),
        ];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)
            ->label(ConsumableLayout::label($name))
            ->placeholder('');
        $quantity = fn (mixed $state): ?string => ConsumableInfolist::quantity(is_scalar($state) ? (string) $state : null);

        return match ($name) {
            'name' => $column->searchable()->sortable(),
            'unit', 'notes' => $column->searchable(),
            'amount', 'unit_price' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => UnitPrice::format(is_scalar($state) ? (string) $state : null)),
            'reorder_point', 'lot_size' => $column->alignEnd()->formatStateUsing($quantity),
            'stock' => $column
                ->alignEnd()
                ->state(fn (Consumable $record): ?string => $record->stock())
                ->formatStateUsing($quantity)
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(Consumable::stockQuery(), $direction)),
            'kind', 'box_type' => $column->formatStateUsing(fn (mixed $state): string => $state instanceof BackedEnum ? (string) $state->value : ''),
            'supplier_id' => $column
                ->state(fn (Consumable $record): ?string => $record->supplier?->name)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('supplier', fn (Builder $q): Builder => $q->whereLike('name', "%{$search}%")))
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Partner::query()->select('partners.name')->whereColumn('partners.id', 'consumables.supplier_id'),
                    $direction,
                )),
            'supplier_code' => $column
                ->state(fn (Consumable $record): ?string => $record->supplier?->code)
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Partner::query()->select('partners.code')->whereColumn('partners.id', 'consumables.supplier_id'),
                    $direction,
                )),
            'purchase_category' => $column
                ->state(fn (Consumable $record): ?string => $record->supplier?->purchase_category?->value)
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Partner::query()->select('partners.purchase_category')->whereColumn('partners.id', 'consumables.supplier_id'),
                    $direction,
                )),
            'reorder_flag' => $column
                ->state(fn (Consumable $record): string => $record->reorderFlag())
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                    '(case when consumables.reorder_point is null then null else ('.self::stockSql().') - consumables.reorder_point end) '.($direction === 'asc' ? 'asc' : 'desc'),
                )),
            default => $column,
        };
    }

    private static function stockSql(): string
    {
        return 'select sum(m.quantity) from consumable_stock_movements m where m.consumable_id = consumables.id';
    }

    /**
     * 在庫数 is the sum of the movements (records without any have no 在庫数, like a blank legacy field).
     *
     * @param  Builder<*>  $query
     */
    private static function filterStock(Builder $query, FilterCondition $condition): void
    {
        $stock = '('.self::stockSql().')';
        // a number, not text: SQLite ranks any text above every number
        $value = [(string) ($condition->values[0] ?? '')];

        match ($condition->operator) {
            FilterOperator::Equals => $query->whereRaw("{$stock} = cast(? as numeric)", $value),
            FilterOperator::NotEquals => $query->where(fn (Builder $q) => $q->whereDoesntHave('stockMovements')->orWhereRaw("{$stock} <> cast(? as numeric)", $value)),
            FilterOperator::LessOrEqual => $query->whereRaw("{$stock} <= cast(? as numeric)", $value),
            FilterOperator::GreaterOrEqual => $query->whereRaw("{$stock} >= cast(? as numeric)", $value),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * 発注判定 「要発注」: 在庫数 − 発注点 ≤ 0 (both filled); "--" (blank) = all others.
     *
     * @param  Builder<*>  $query
     */
    private static function filterReorder(Builder $query, FilterCondition $condition): void
    {
        $due = fn (Builder $q): Builder => $q->whereNotNull('consumables.reorder_point')->whereHas('stockMovements')
            ->whereRaw('('.self::stockSql().') - consumables.reorder_point <= 0');
        $values = $condition->values;
        $wantsDue = in_array(ConsumableFields::REORDER, $values, true);
        $wantsBlank = in_array('', $values, true);

        if ($condition->operator === FilterOperator::NotIn) {
            [$wantsDue, $wantsBlank] = [! $wantsDue, ! $wantsBlank];
        }

        match (true) {
            $wantsDue && $wantsBlank => null,
            $wantsDue => $query->where($due),
            $wantsBlank => $query->whereNot($due),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
