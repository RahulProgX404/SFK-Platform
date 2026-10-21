<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payables\Tables;

use Closure;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Filters\DateMode;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Domain\Tax\TaxMode;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Models\Partner;
use Modules\Purchasing\Domain\Payables\PayableCategories;
use Modules\Purchasing\Filament\Resources\Payables\PayableLayout;
use Modules\Purchasing\Filament\Resources\Payables\PayableResource;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseLayout;
use Modules\Purchasing\Models\PayableMonth;

/**
 * Record list of 23. 買掛管理 with the legacy views (SYSTEM_BRAIN APP_23B views.md): 先月買掛一覧 / 当月買掛一覧 (by 仕入カテゴリ,
 * then 得意先名), 買掛一覧 (newest 年月 first) and 買掛一覧(すべての項目); 先々月買掛一覧 was fixed to 2025年08月 and is not
 * carried over, the search box replaces 買掛一覧(検索特化). （すべて） shows columns() in order (the legacy key / date helper
 * fields and the duplicate 当月買掛残 are left out). インライン編集 changes 消費税端数調整 (the only figure entered by hand).
 */
final class PayablesTable
{
    public const VIEW_LAST_MONTH = 'last-month';

    public const VIEW_THIS_MONTH = 'this-month';

    public const VIEW_LIST = 'list';

    public const VIEW_ALL_FIELDS = 'all-fields';

    public const LIST_COLUMNS = ['year_month', 'supplier_id', 'purchase_category', 'previous_balance', 'paid', 'net_balance', 'purchase_amount', 'tax_amount',
        'month_total', 'closing_balance'];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $names = ['id', 'supplier_code', 'supplier_id', 'previous_balance', 'paid', 'net_balance', 'purchase_amount', 'month_total', 'year_month', 'month_start',
            'cutting_amount', 'plate_amount', 'stored_plate_amount', 'spot_color_amount', 'purchase_category', 'cat_materials_goods', ...PayableCategories::columns(),
            'closing_balance', 'tax_amount', 'tax_mode', 'rounding', 'calculation_unit', 'tax_adjustment', 'created_at'];

        $columns = [];
        foreach ($names as $name) {
            $columns[$name] = PayableLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        $month = fn (DateMode $mode): array => [['field' => 'month_start', 'operator' => FilterOperator::Equals->value, 'values' => [], 'mode' => $mode->value]];

        return [
            ListView::table(self::VIEW_LAST_MONTH, '先月買掛一覧', self::LIST_COLUMNS, [], $month(DateMode::LastMonth)),
            ListView::table(self::VIEW_THIS_MONTH, '当月買掛一覧', self::LIST_COLUMNS, [], $month(DateMode::ThisMonth)),
            ListView::table(self::VIEW_LIST, '買掛一覧', self::LIST_COLUMNS, [['field' => 'year_month', 'direction' => 'desc'], ['field' => 'id', 'direction' => 'desc']]),
            ListView::table(self::VIEW_ALL_FIELDS, '買掛一覧(すべての項目)', [
                'year_month', 'supplier_id', 'previous_balance', 'paid', 'net_balance', 'purchase_amount', 'tax_amount', 'month_total', 'closing_balance',
                'cutting_amount', 'plate_amount', 'stored_plate_amount', 'spot_color_amount', 'purchase_category', 'cat_materials_goods', ...PayableCategories::columns(),
            ], [['field' => 'id', 'direction' => 'desc']]),
        ];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('supplier'))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            // legacy sort of 先月 / 当月買掛一覧: 仕入カテゴリ, then 得意先名
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy(self::supplierColumn('purchase_category'), 'asc')->orderBy(self::supplierColumn('name'), 'asc'))
            ->recordActions(AppRecordActions::make(PayableResource::class))
            ->emptyStateIcon(Heroicon::OutlinedScale)
            ->emptyStateHeading('買掛がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_23B fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => PayableLayout::label($name);
        $modes = array_map(fn (TaxMode $mode): string => $mode->value, TaxMode::cases());

        return [
            FilterField::text('year_month', $label('year_month')),
            FilterField::choice('supplier_id', $label('supplier_id'), fn (): array => Partner::query()->where('is_supplier', true)->orderByDesc('id')
                ->pluck('name', 'id')->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all()),
            FilterField::choice('purchase_category', $label('purchase_category'), PurchaseLayout::categoryOptions())->relationship('supplier', 'purchase_category'),
            FilterField::choice('tax_mode', $label('tax_mode'), array_combine($modes, $modes))->relationship('supplier', 'supplier_tax_mode'),
            ...array_map(fn (string $name): FilterField => FilterField::number($name, $label($name)), PayableLayout::AMOUNTS),
            ...array_map(fn (string $name): FilterField => FilterField::number($name, $label($name)), PayableCategories::columns()),
            FilterField::date('month_start', $label('month_start')),
            FilterField::number('id', $label('id')),
            FilterField::user('created_by', $label('creator')),
            FilterField::dateTime('created_at', $label('created_at')),
            FilterField::user('updated_by', $label('updater')),
            FilterField::dateTime('updated_at', $label('updated_at')),
        ];
    }

    /** @return list<InlineField> */
    public static function inlineFields(): array
    {
        return [InlineField::number('tax_adjustment')->required()->minValue(-99999999999)->maxValue(99999999999)];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(PayableLayout::label($name))->placeholder('');
        $supplier = fn (string $attribute): Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(self::supplierColumn($attribute), $direction);

        return match (true) {
            in_array($name, PayableLayout::AMOUNTS, true), isset(PayableCategories::LABELS[$name]) => $column->alignEnd()
                ->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            $name === 'cat_materials_goods' => $column->alignEnd()
                ->state(fn (PayableMonth $record): string => AppFormat::yen($record->materialsAndGoods()))
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw('(payable_months.cat_materials + payable_months.cat_goods) '.($direction === 'desc' ? 'desc' : 'asc'))),
            $name === 'year_month' => $column->state(fn (PayableMonth $record): string => $record->monthLabel()),
            $name === 'month_start' => $column->date('Y-m-d'),
            $name === 'created_at' => $column->dateTime('Y-m-d H:i'),
            $name === 'supplier_id' => $column
                ->state(fn (PayableMonth $record): string => $record->supplier->name)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('supplier', fn (Builder $q): Builder => $q->whereLike('name', "%{$search}%")))
                ->sortable(query: $supplier('name')),
            isset(PayableLayout::MASTER[$name]) => $column
                ->state(fn (PayableMonth $record): ?string => AppFormat::text($record->supplier->getAttribute(PayableLayout::MASTER[$name])))
                ->sortable(query: $supplier(PayableLayout::MASTER[$name])),
            default => $column,
        };
    }

    /** @return Builder<Partner> a column of the row's supplier, for sorting */
    private static function supplierColumn(string $attribute): Builder
    {
        return Partner::query()->select("partners.{$attribute}")->whereColumn('partners.id', 'payable_months.supplier_id');
    }
}
