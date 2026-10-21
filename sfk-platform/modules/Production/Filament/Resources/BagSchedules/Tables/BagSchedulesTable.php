<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\BagSchedules\Tables;

use Closure;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Filters\FilterCondition;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Models\Machine;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Order;
use Modules\Production\Domain\ProductionChoices;
use Modules\Production\Domain\Schedules\GanttChart;
use Modules\Production\Filament\Resources\BagSchedules\BagScheduleLayout;
use Modules\Production\Filament\Resources\BagSchedules\BagScheduleResource;
use Modules\Production\Models\BagSchedule;

/**
 * Record list of 12. 製袋予定管理 with the legacy views (SYSTEM_BRAIN APP_12 views.md): 日次 / 週次 / 月次 / 年次ガントチャート
 * (カスタマイズ形式, ListBagSchedules) and 帳票出力用(テスト) (使用状況 not 完了, by 機械コード — the list printed as the
 * schedule); （すべて） shows columns() in order, the list is by 機械コード and 開始日時. インライン編集 changes 使用状況, 部数
 * and 備考.
 */
final class BagSchedulesTable
{
    public const VIEW_PRINT = 'print';

    public const PRINT_COLUMNS = ['machine_id', 'usage_status', 'order_number_p', 'customer', 'product_name_p', 'copies', 'brought_on', 'brought_by', 'delivered_on',
        'flap_glue', 'order_notes'];

    /** Gantt view names by scale. */
    public const GANTT_VIEWS = [
        GanttChart::DAY => '日次ガントチャート',
        GanttChart::WEEK => '週次ガントチャート',
        GanttChart::MONTH => '月次ガントチャート',
        GanttChart::YEAR => '年次ガントチャート',
    ];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', 'starts_at', 'ends_at', 'usage_status', 'machine_id', 'order_id', 'product_name', 'copies', 'notes', 'free_product_name', 'machine_code',
            'free_order_number', 'entry_mode', 'brought_on', 'delivered_on', 'order_notes', 'customer', 'quantity', 'brought_by', 'die_cut', 'window', 'flap_glue',
            'order_number_p', 'product_name_p', 'created_at'] as $name) {
            $columns[$name] = BagScheduleLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        $views = [];
        foreach (self::GANTT_VIEWS as $scale => $name) {
            $views[] = ListView::custom('gantt-'.$scale, $name, 'gantt-'.$scale);
        }
        $views[] = ListView::table(self::VIEW_PRINT, '帳票出力用(テスト)', self::PRINT_COLUMNS, [],
            [['field' => 'usage_status', 'operator' => FilterOperator::NotIn->value, 'values' => ['完了']]]);

        return $views;
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::PRINT_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['machine', 'order.customer']))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            // like the schedule print: by machine, then by time
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy(self::machineColumn('code'))->orderBy('bag_schedules.starts_at'))
            ->recordActions(AppRecordActions::make(BagScheduleResource::class))
            ->emptyStateIcon(Heroicon::OutlinedChartBar)
            ->emptyStateHeading('製袋予定がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_12 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => BagScheduleLayout::label($name);

        return [
            FilterField::dateTime('starts_at', $label('starts_at')),
            FilterField::dateTime('ends_at', $label('ends_at')),
            FilterField::choice('machine_id', $label('machine_id'), fn (): array => Machine::query()->orderByDesc('id')->pluck('name', 'id')
                ->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all()),
            FilterField::choice('usage_status', $label('usage_status'), ProductionChoices::options(array_keys(ProductionChoices::USAGE_STATUSES))),
            FilterField::choice('entry_mode', $label('entry_mode'), ProductionChoices::options([ProductionChoices::ENTRY_FROM_ORDER, ProductionChoices::ENTRY_FREE])),
            FilterField::text('order_id', $label('order_id'))->query(self::filterOrderNumber(...)),
            FilterField::text('free_order_number', $label('free_order_number')),
            FilterField::text('free_product_name', $label('free_product_name')),
            FilterField::number('copies', $label('copies')),
            FilterField::text('notes', $label('notes')),
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
        return [
            InlineField::select('usage_status', ProductionChoices::options(array_keys(ProductionChoices::USAGE_STATUSES))),
            InlineField::number('copies', 4)->minValue(0)->maxValue(999999999999),
            InlineField::text('notes')->maxLength(255),
        ];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(BagScheduleLayout::label($name))->placeholder('');
        $machine = fn (string $attribute): Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(self::machineColumn($attribute), $direction);
        $order = fn (string $attribute): Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(
            Order::query()->select("orders.{$attribute}")->whereColumn('orders.id', 'bag_schedules.order_id'),
            $direction,
        );

        return match (true) {
            in_array($name, ['free_order_number', 'free_product_name', 'notes'], true) => $column->searchable(),
            $name === 'starts_at', $name === 'ends_at', $name === 'created_at' => $column->dateTime('Y-m-d H:i'),
            $name === 'copies' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::number($state)),
            $name === 'machine_id' => $column->state(fn (BagSchedule $record): ?string => $record->machine?->name)->sortable(query: $machine('name')),
            $name === 'machine_code' => $column->state(fn (BagSchedule $record): ?string => $record->machine?->code)->sortable(query: $machine('code')),
            $name === 'order_id' => $column
                ->state(fn (BagSchedule $record): ?string => $record->order?->order_number)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('order', fn (Builder $q): Builder => $q->whereLike('order_number', "%{$search}%")))
                ->sortable(query: $order('order_number')),
            $name === 'product_name' => $column->state(fn (BagSchedule $record): ?string => $record->order?->product_name)->sortable(query: $order('product_name')),
            $name === 'order_number_p' => $column->state(fn (BagSchedule $record): ?string => $record->orderNumber())
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Order::query()->selectRaw('coalesce(max(orders.order_number), bag_schedules.free_order_number)')->whereColumn('orders.id', 'bag_schedules.order_id'),
                    $direction,
                )),
            $name === 'product_name_p' => $column->state(fn (BagSchedule $record): ?string => $record->productName())
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Order::query()->selectRaw('coalesce(max(orders.product_name), bag_schedules.free_product_name)')->whereColumn('orders.id', 'bag_schedules.order_id'),
                    $direction,
                )),
            $name === 'customer' => $column->state(fn (BagSchedule $record): ?string => $record->order?->customer?->name)
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Partner::query()->select('partners.name')->join('orders', 'orders.customer_id', '=', 'partners.id')->whereColumn('orders.id', 'bag_schedules.order_id'),
                    $direction,
                )),
            isset(BagScheduleLayout::ORDER_VALUES[$name]) => $column
                ->state(fn (BagSchedule $record): ?string => BagScheduleLayout::orderValue($record->order, $name))
                ->sortable(query: $order(BagScheduleLayout::ORDER_VALUES[$name])),
            default => $column,
        };
    }

    /** @return Builder<Machine> a column of the row's machine, for sorting */
    private static function machineColumn(string $attribute): Builder
    {
        return Machine::query()->select("machines.{$attribute}")->whereColumn('machines.id', 'bag_schedules.machine_id');
    }

    /**
     * 注番 is the job's number, compared like a text field.
     *
     * @param  Builder<*>  $query
     */
    private static function filterOrderNumber(Builder $query, FilterCondition $condition): void
    {
        $value = $condition->values[0] ?? '';
        $has = fn (callable $where): Builder => $query->whereHas('order', $where);

        match ($condition->operator) {
            FilterOperator::Contains => $has(fn (Builder $q): Builder => $q->whereLike('order_number', '%'.$value.'%')),
            FilterOperator::NotContains => $query->whereDoesntHave('order', fn (Builder $q): Builder => $q->whereLike('order_number', '%'.$value.'%')),
            FilterOperator::Equals => $value === '' ? $query->whereDoesntHave('order') : $has(fn (Builder $q): Builder => $q->where('order_number', $value)),
            FilterOperator::NotEquals => $value === '' ? $query->whereHas('order') : $query->whereDoesntHave('order', fn (Builder $q): Builder => $q->where('order_number', $value)),
            FilterOperator::OneOf => $has(fn (Builder $q): Builder => $q->whereIn('order_number', $condition->values)),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
