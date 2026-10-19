<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Orders\Tables;

use App\Models\User;
use Closure;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Filters\FilterCondition;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Models\Machine;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Domain\Orders\OrderChoices;
use Modules\Orders\Filament\Resources\Orders\OrderFormat;
use Modules\Orders\Filament\Resources\Orders\OrderLayout;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Models\Order;

/**
 * Record list of 04. 受注入力. The legacy views (SYSTEM_BRAIN APP_04 views.md, newest 受注入力ID first) are the app's
 * default views, in legacy order: 自分が担当の受注一覧 (担当者 = ログインユーザー), 受注一覧, 売上完了 (売上残数量 = 0),
 * 未売上あり (≥ 1), 未製造あり (製造残数量 ≥ 1), 製造完了 (= 0), 発送完了済, 製袋予定一覧 and 製袋予定表出力 (status before
 * gluing, with a 機械; by 機械 and 完成日). Not rebuilt: the duplicate 「自分が担当の受注一覧 - コピー」 and the plugin view
 * 受注一覧(検索特化) (the search box replaces it). （すべて） shows columns() in order. filterFields() are the fields of
 * 絞り込む and of the 一覧 settings; inlineFields() those インライン編集 changes, with the rules of OrderForm (得意先 required,
 * 数量 > 0).
 */
final class OrdersTable
{
    /** 絞り込む value of a checked check box. */
    private const CHECKED = 'checked';

    /** Columns every legacy order view starts with. */
    private const BASE = ['order_number', 'status', 'customer_id', 'order_date', 'assignees', 'product_name', 'quantity'];

    private const DUE = ['unit', 'sample', 'spare', 'due_date', 'am_pm', 'due_time'];

    private const ESTIMATE = ['sales_amount', 'estimated_cost_total', 'estimated_profit', 'profit_rate'];

    /** Every column, in the order of the legacy （すべて） (helper and key fields left out). */
    public const ALL_COLUMNS = [
        'id', 'status', 'order_number', 'assignees', 'customer_id', 'product_name', 'is_fsc', 'category1', 'quantity', 'unit', 'sample', 'spare', 'due_date',
        'due_time', 'order_date', 'expected_hours', 'manufactured_quantity', 'manufacturing_remaining', 'sold_quantity', 'sales_remaining',
        'paper_subtotal', 'data_supplier_id', 'data_supply', 'designer_id', 'film_supplier_id', 'design_fee', 'film_supply', 'plate_supplier_id',
        'plate_version', 'plate_last_used_on', 'plate_count', 'plate_unit_price', 'plate_amount', 'plate_storage', 'plate_size', 'manufacturer_id',
        'bag_schedule', 'width', 'height', 'flap', 'bottom', 'unfolded_width', 'unfolded_height', 'bag_unit_price', 'bag_manual', 'bag_manual_amount',
        'bag_amount', 'envelope_shape', 'envelope_other', 'legacy_size_no', 'pasting', 'as_per_artwork', 'flap_type', 'die_cut', 'flap_glue', 'side_glue',
        'die', 'window', 'window_width', 'window_height', 'window_from_left', 'window_from_bottom', 'carton_number', 'per_case', 'per_case_specified',
        'partition_case', 'band', 'band_sheets', 'has_label', 'case_cost', 'shipping_cost', 'sales_amount', 'estimated_cost_total', 'estimated_profit',
        'profit_rate', 'printed_on', 'print_location', 'delivered_on', 'delivery_location', 'delivery_service', 'completed_on', 'remarks', 'memo',
        'worker_message', 'notes', 'customer_code', 'am_pm', 'closing_day', 'bag_subtotal', 'category2', 'sold_amount', 'purchase_amount',
        'purchase_cutting_amount', 'purchase_plate_amount', 'purchase_stored_plate_amount', 'purchase_spot_color_amount', 'internal_cost',
        'purchase_cost_total', 'actual_cost_total', 'actual_sales_amount', 'plate_notes', 'machine_id', 'manufacturing_sales', 'size_no',
        'customer_rounding', 'previous_order_date', 'previous_order_number', 'previous_order_quantity', 'profit', 'created_at', 'updated_at',
    ];

    private const YEN = [
        'paper_subtotal', 'design_fee', 'plate_amount', 'bag_manual_amount', 'bag_amount', 'case_cost', 'shipping_cost', 'sales_amount', 'estimated_cost_total',
        'estimated_profit', 'bag_subtotal', 'sold_amount', 'purchase_amount', 'purchase_cutting_amount', 'purchase_plate_amount',
        'purchase_stored_plate_amount', 'purchase_spot_color_amount', 'internal_cost', 'purchase_cost_total', 'actual_cost_total', 'actual_sales_amount',
        'manufacturing_sales', 'profit',
    ];

    private const NUMBERS = [
        'quantity', 'sample', 'spare', 'expected_hours', 'manufactured_quantity', 'manufacturing_remaining', 'sold_quantity', 'sales_remaining', 'plate_count',
        'width', 'height', 'flap', 'bottom', 'unfolded_width', 'unfolded_height', 'legacy_size_no', 'window_width', 'window_height', 'window_from_left',
        'window_from_bottom', 'per_case', 'band_sheets', 'previous_order_quantity',
    ];

    private const DATES = ['due_date', 'order_date', 'plate_last_used_on', 'printed_on', 'delivered_on', 'completed_on', 'previous_order_date'];

    private const FLAGS = [
        'is_fsc' => '115', 'as_per_artwork' => '原稿通り', 'per_case_specified' => '有', 'partition_case' => '有', 'band' => '有', 'has_label' => '有',
        'bag_manual' => '手動',
    ];

    private const PARTNERS = [
        'customer_id' => 'customer', 'plate_supplier_id' => 'plateSupplier', 'manufacturer_id' => 'manufacturer', 'data_supplier_id' => 'dataSupplier',
        'film_supplier_id' => 'filmSupplier',
    ];

    private const SELECTS = [
        'status' => OrderChoices::STATUSES, 'category1' => OrderChoices::CATEGORIES1, 'category2' => OrderChoices::CATEGORIES2, 'unit' => OrderChoices::UNITS,
        'plate_version' => OrderChoices::PLATE_VERSIONS, 'plate_storage' => OrderChoices::PLATE_STORAGES, 'plate_size' => OrderChoices::PLATE_SIZES,
        'bag_schedule' => OrderChoices::BAG_SCHEDULES, 'envelope_shape' => OrderChoices::ENVELOPE_SHAPES, 'pasting' => OrderChoices::PASTINGS,
        'flap_type' => OrderChoices::FLAP_TYPES, 'die_cut' => OrderChoices::DIE_CUTS, 'flap_glue' => OrderChoices::FLAP_GLUES,
        'side_glue' => OrderChoices::SIDE_GLUES, 'window' => OrderChoices::WINDOWS, 'data_supply' => OrderChoices::SUPPLIES,
        'film_supply' => OrderChoices::SUPPLIES,
    ];

    /**
     * Every column (name => label), in the order of （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (self::ALL_COLUMNS as $name) {
            $columns[$name] = OrderLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> the legacy views, in legacy order (topmost = default) */
    public static function defaultViews(): array
    {
        $newest = [['field' => 'id', 'direction' => 'desc']];
        $remaining = fn (string $field, string $operator, string $value): array => [['field' => $field, 'operator' => $operator, 'values' => [$value]]];

        return [
            ListView::table('mine', '自分が担当の受注一覧', [
                ...self::BASE, ...self::DUE, 'sales_amount', 'estimated_cost_total', 'estimated_profit', 'actual_sales_amount', 'internal_cost',
                'purchase_cost_total', 'actual_cost_total', 'profit_rate',
            ], $newest, [['field' => 'assignees', 'operator' => 'in', 'values' => [FilterField::LOGIN_USER]]]),
            ListView::table('list', '受注一覧', [
                ...self::BASE, ...self::DUE, 'manufacturing_remaining', 'sales_amount', 'estimated_cost_total', 'estimated_profit', 'manufacturing_sales',
                'actual_sales_amount', 'purchase_cost_total', 'internal_cost', 'actual_cost_total', 'profit_rate', 'is_fsc',
            ], $newest),
            ListView::table('sold', '売上完了', [...self::BASE, 'sold_quantity', ...self::DUE, ...self::ESTIMATE], $newest, $remaining('sales_remaining', 'eq', '0')),
            ListView::table('unsold', '未売上あり', [
                ...self::BASE, ...self::DUE, 'sales_amount', 'sold_quantity', 'sales_remaining', 'estimated_cost_total', 'estimated_profit', 'sold_amount',
                'profit_rate', 'actual_cost_total',
            ], $newest, $remaining('sales_remaining', 'gte', '1')),
            ListView::table('unmanufactured', '未製造あり', [...self::BASE, 'manufacturing_remaining', ...self::DUE, ...self::ESTIMATE], $newest, $remaining('manufacturing_remaining', 'gte', '1')),
            ListView::table('manufactured', '製造完了', [...self::BASE, 'manufactured_quantity', ...self::DUE, ...self::ESTIMATE], $newest, $remaining('manufacturing_remaining', 'eq', '0')),
            ListView::table('shipped', '発送完了済', [...self::BASE, ...self::DUE, ...self::ESTIMATE], $newest, [
                ['field' => 'status', 'operator' => 'in', 'values' => [OrderChoices::STATUS_SHIPPED]],
            ]),
            ListView::table('bag_schedule', '製袋予定一覧', ['bag_schedule', 'order_number', 'customer_id', 'product_name', 'quantity', 'delivered_on', 'completed_on', 'status'], $newest),
            ListView::table('bag_schedule_print', '製袋予定表出力', [
                'status', 'machine_id', 'order_number', 'customer_id', 'product_name', 'quantity', 'delivered_on', 'delivery_service', 'completed_on', 'remarks',
                'die_cut', 'window', 'flap_glue',
            ], [['field' => 'machine_id', 'direction' => 'asc'], ['field' => 'completed_on', 'direction' => 'asc']], [
                ['field' => 'status', 'operator' => 'not_in', 'values' => OrderChoices::STATUSES_AFTER_GLUING],
                ['field' => 'machine_id', 'operator' => 'not_in', 'values' => ['']],
            ]),
        ];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::BASE;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'customer', 'plateSupplier', 'manufacturer', 'dataSupplier', 'filmSupplier', 'machine', 'designer.employeeCategories', 'designer.position',
                'assignees.employeeCategories', 'assignees.position',
            ]))
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
            ->recordActions(AppRecordActions::make(OrderResource::class))
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->emptyStateHeading('受注がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_04 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => OrderLayout::label($name);
        /** @param list<string> $choices */
        $choice = fn (string $name, array $choices): FilterField => FilterField::choice($name, $label($name), OrderChoices::options($choices));
        $number = fn (string $name): FilterField => FilterField::number($name, $label($name));
        $date = fn (string $name): FilterField => FilterField::date($name, $label($name));
        $text = fn (string $name): FilterField => FilterField::text($name, $label($name));

        return [
            $number('id'),
            $choice('status', OrderChoices::STATUSES),
            $text('order_number'),
            FilterField::user('assignees', $label('assignees'))->relationship('assignees', 'id'),
            FilterField::text('customer_id', $label('customer_id'))->query(self::partnerName('customer')),
            $text('closing_day'),
            $text('product_name'),
            FilterField::choice('is_fsc', $label('is_fsc'), [self::CHECKED => OrderChoices::FSC_CODE])->query(self::flag('is_fsc')),
            $choice('category1', OrderChoices::CATEGORIES1),
            $choice('category2', OrderChoices::CATEGORIES2),
            $number('quantity'),
            $choice('unit', OrderChoices::UNITS),
            $number('sample'),
            $number('spare'),
            $date('due_date'),
            $date('order_date'),
            $number('expected_hours'),
            $number('manufactured_quantity'),
            $number('manufacturing_remaining'),
            $number('sold_quantity'),
            $number('sales_remaining'),
            $number('paper_subtotal'),
            FilterField::text('plate_supplier_id', $label('plate_supplier_id'))->query(self::partnerName('plateSupplier')),
            $choice('plate_version', OrderChoices::PLATE_VERSIONS),
            $date('plate_last_used_on'),
            $number('plate_count'),
            $number('plate_amount'),
            $choice('plate_storage', OrderChoices::PLATE_STORAGES),
            $choice('plate_size', OrderChoices::PLATE_SIZES),
            $number('design_fee'),
            FilterField::text('manufacturer_id', $label('manufacturer_id'))->query(self::partnerName('manufacturer')),
            $choice('bag_schedule', OrderChoices::BAG_SCHEDULES),
            $choice('envelope_shape', OrderChoices::ENVELOPE_SHAPES),
            $text('size_no'),
            $choice('pasting', OrderChoices::PASTINGS),
            $choice('flap_type', OrderChoices::FLAP_TYPES),
            $choice('die_cut', OrderChoices::DIE_CUTS),
            $choice('flap_glue', OrderChoices::FLAP_GLUES),
            $choice('side_glue', OrderChoices::SIDE_GLUES),
            $text('die'),
            $choice('window', OrderChoices::WINDOWS),
            $text('carton_number'),
            $number('bag_amount'),
            $number('bag_subtotal'),
            $number('case_cost'),
            $number('shipping_cost'),
            $number('sales_amount'),
            $number('estimated_cost_total'),
            $number('estimated_profit'),
            $number('profit'),
            $number('profit_rate'),
            $date('printed_on'),
            $text('print_location'),
            $date('delivered_on'),
            $text('delivery_location'),
            $text('delivery_service'),
            $date('completed_on'),
            $text('remarks'),
            FilterField::textarea('memo', $label('memo')),
            FilterField::textarea('worker_message', $label('worker_message')),
            FilterField::textarea('notes', $label('notes')),
            $number('internal_cost'),
            $number('actual_cost_total'),
            $number('purchase_amount'),
            $number('purchase_cutting_amount'),
            $number('purchase_plate_amount'),
            $number('purchase_stored_plate_amount'),
            $number('purchase_spot_color_amount'),
            $number('purchase_cost_total'),
            $number('actual_sales_amount'),
            $number('sold_amount'),
            FilterField::choice('machine_id', $label('machine_id'), fn (): array => Machine::query()->orderBy('name')->pluck('name', 'id')
                ->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all())
                ->query(self::key('machine_id'))
                ->sortable(),
            FilterField::user('created_by', $label('creator')),
            FilterField::dateTime('created_at', $label('created_at')),
            FilterField::user('updated_by', $label('updater')),
            FilterField::dateTime('updated_at', $label('updated_at')),
        ];
    }

    /**
     * Fields of インライン編集, checked like OrderForm (得意先(受注先) required, 数量 > 0 — legacy save check).
     *
     * @return list<InlineField>
     */
    public static function inlineFields(): array
    {
        $select = fn (string $name): InlineField => InlineField::select($name, OrderChoices::options(self::SELECTS[$name]));
        $quantity = fn (string $name): InlineField => InlineField::number($name, 4)->minValue(0)->maxValue(999999999999);
        $amount = fn (string $name): InlineField => InlineField::number($name)->minValue(0)->maxValue(99999999999);

        return [
            $select('status'),
            InlineField::users('assignees'),
            InlineField::select('customer_id', self::customerOptions())->required(),
            InlineField::text('product_name')->maxLength(255),
            InlineField::checkbox('is_fsc', OrderChoices::FSC_CODE),
            $select('category1'),
            $select('category2'),
            $quantity('quantity')->required()->greaterThan(0),
            $select('unit'),
            $quantity('sample'),
            $quantity('spare'),
            InlineField::date('due_date'),
            InlineField::time('due_time'),
            InlineField::date('order_date'),
            $quantity('expected_hours'),
            $quantity('manufactured_quantity'),
            $quantity('sold_quantity'),
            $select('plate_version'),
            InlineField::date('plate_last_used_on'),
            $select('plate_storage')->required(),
            $select('plate_size'),
            $amount('design_fee'),
            $select('bag_schedule'),
            $select('envelope_shape'),
            InlineField::text('envelope_other')->maxLength(255),
            InlineField::text('size_no')->maxLength(64),
            $select('pasting'),
            $select('flap_type'),
            $select('die_cut'),
            $select('flap_glue'),
            $select('side_glue'),
            InlineField::text('die')->maxLength(255),
            $select('window'),
            InlineField::text('carton_number')->maxLength(64),
            $amount('case_cost'),
            $amount('shipping_cost'),
            $amount('sales_amount'),
            InlineField::date('printed_on'),
            InlineField::text('print_location')->maxLength(255),
            InlineField::date('delivered_on'),
            InlineField::text('delivery_location')->maxLength(255),
            InlineField::text('delivery_service')->maxLength(255),
            InlineField::date('completed_on'),
            InlineField::text('remarks')->maxLength(255),
            InlineField::textarea('memo'),
            InlineField::textarea('worker_message'),
            InlineField::textarea('notes'),
            $amount('internal_cost'),
            $amount('purchase_amount'),
            $amount('purchase_cutting_amount'),
            $amount('purchase_plate_amount'),
            $amount('purchase_stored_plate_amount'),
            $amount('purchase_spot_color_amount'),
            $amount('sold_amount'),
        ];
    }

    /** @return array<int, string> 得意先(受注先) choices: 得意先 / その他 (BR-MD-02), newest first */
    private static function customerOptions(): array
    {
        /** @var array<int, string> $options */
        $options = Partner::query()
            ->where(fn (Builder $q) => $q->where('is_customer', true)->orWhere('is_other', true))
            ->orderByDesc('id')
            ->pluck('name', 'id')
            ->all();

        return $options;
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)
            ->label(OrderLayout::label($name))
            ->placeholder('');

        return match (true) {
            $name === 'order_number' => $column->searchable()->sortable(),
            $name === 'product_name' => $column->searchable()->sortable(),
            in_array($name, self::YEN, true) => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => OrderFormat::yen($state)),
            in_array($name, self::NUMBERS, true) => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => OrderFormat::field($name, $state)),
            in_array($name, ['plate_unit_price', 'bag_unit_price'], true) => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => OrderFormat::price($state)),
            $name === 'profit_rate' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => OrderFormat::percent($state)),
            isset(self::FLAGS[$name]) => $column->state(fn (Order $record): string => $record->getAttribute($name) ? self::FLAGS[$name] : ''),
            in_array($name, self::DATES, true) => $column->date('Y-m-d'),
            $name === 'due_time' => $column->formatStateUsing(fn (mixed $state): string => OrderFormat::time($state)),
            $name === 'created_at', $name === 'updated_at' => $column->dateTime('Y-m-d H:i'),
            // 得意先(受注先): searchable by name; 昇順 by name
            $name === 'customer_id' => $column
                ->state(fn (Order $record): ?string => $record->customer?->name)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('customer', fn (Builder $q) => $q->whereLike('name', "%{$search}%")))
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Partner::query()->select('name')->whereColumn('partners.id', 'orders.customer_id')->limit(1),
                    $direction,
                )),
            isset(self::PARTNERS[$name]) => $column
                ->state(fn (Order $record): ?string => ($partner = $record->{self::PARTNERS[$name]}) instanceof Partner ? $partner->name : null),
            $name === 'machine_id' => $column->state(fn (Order $record): ?string => $record->machine?->name),
            $name === 'customer_code' => $column
                ->state(fn (Order $record): ?string => $record->customer?->code)
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Partner::query()->select('code')->whereColumn('partners.id', 'orders.customer_id')->limit(1),
                    $direction,
                )),
            // AM/PM follows 時間 (BR-OR-13)
            $name === 'am_pm' => $column
                ->state(fn (Order $record): string => $record->amPm())
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('due_time', $direction)),
            // names open the ユーザー情報 popup, so the cell is not a link to the record
            $name === 'assignees' => $column
                ->state(fn (Order $record): HtmlString => UserPopup::list($record->assignees))
                ->html()
                ->disabledClick()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    User::query()->select('users.name')
                        ->join('order_assignees', 'order_assignees.user_id', '=', 'users.id')
                        ->whereColumn('order_assignees.order_id', 'orders.id')
                        ->orderBy('users.name')
                        ->limit(1),
                    $direction,
                )),
            $name === 'designer_id' => $column->state(fn (Order $record): HtmlString => UserPopup::html($record->designer))->html()->disabledClick(),
            default => $column,
        };
    }

    /**
     * A lookup field filtered by the partner's name, like the legacy text lookup (含む / 含まない / ＝ / ≠ / いずれか).
     *
     * @return Closure(Builder<*>, FilterCondition): void
     */
    private static function partnerName(string $relation): Closure
    {
        return function (Builder $query, FilterCondition $condition) use ($relation): void {
            $value = $condition->values[0] ?? '';
            $named = fn (Builder $q) => match ($condition->operator) {
                FilterOperator::Contains, FilterOperator::NotContains => $q->whereLike('name', '%'.$value.'%'),
                FilterOperator::OneOf => $q->whereIn('name', $condition->values),
                default => $q->where('name', $value),
            };

            match ($condition->operator) {
                FilterOperator::NotContains, FilterOperator::NotEquals => $value === '' && $condition->operator === FilterOperator::NotEquals
                    ? $query->whereHas($relation)
                    : $query->whereDoesntHave($relation, $named),
                default => $value === '' && $condition->operator === FilterOperator::Equals
                    ? $query->whereDoesntHave($relation)
                    : $query->whereHas($relation, $named),
            };
        };
    }

    /**
     * A check box stored as a boolean: CHECKED = checked, "--" = not checked.
     *
     * @return Closure(Builder<*>, FilterCondition): void
     */
    private static function flag(string $column): Closure
    {
        return function (Builder $query, FilterCondition $condition) use ($column): void {
            $values = array_map(fn (string $value): bool => $value === self::CHECKED, $condition->values);
            $condition->operator === FilterOperator::NotIn
                ? $query->whereNotIn($column, array_values(array_unique($values)))
                : $query->whereIn($column, array_values(array_unique($values)));
        };
    }

    /**
     * A choice stored as a key (e.g. 機械): "--" = none.
     *
     * @return Closure(Builder<*>, FilterCondition): void
     */
    private static function key(string $column): Closure
    {
        return function (Builder $query, FilterCondition $condition) use ($column): void {
            $chosen = array_values(array_map('intval', array_filter($condition->values, fn (string $value): bool => $value !== '')));
            $blank = in_array('', $condition->values, true);
            $qualified = $query->qualifyColumn($column);

            if ($condition->operator === FilterOperator::NotIn) {
                $blank ? $query->whereNotNull($qualified) : $query->where(fn (Builder $q) => $q->whereNull($qualified)->orWhereNotIn($qualified, $chosen));
                if ($blank && $chosen !== []) {
                    $query->whereNotIn($qualified, $chosen);
                }

                return;
            }

            $query->where(function (Builder $q) use ($qualified, $chosen, $blank): void {
                if ($chosen !== []) {
                    $q->whereIn($qualified, $chosen);
                }
                if ($blank) {
                    $q->orWhereNull($qualified);
                }
            });
        };
    }
}
