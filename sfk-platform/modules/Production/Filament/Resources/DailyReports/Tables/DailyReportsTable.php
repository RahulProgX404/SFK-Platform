<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\DailyReports\Tables;

use App\Models\User;
use Closure;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Domain\Consumables\ConsumableKind;
use Modules\MasterData\Models\Consumable;
use Modules\MasterData\Models\ConsumableStockMovement;
use Modules\MasterData\Models\Machine;
use Modules\Orders\Domain\Orders\OrderChoices;
use Modules\Orders\Models\Order;
use Modules\Production\Domain\DailyReports\TaskCosting;
use Modules\Production\Domain\ProductionChoices;
use Modules\Production\Filament\Resources\DailyReports\DailyReportLayout;
use Modules\Production\Filament\Resources\DailyReports\DailyReportResource;
use Modules\Production\Models\DailyReport;

/**
 * Record list of 08. 日報入力 with the legacy view 日報一覧 (SYSTEM_BRAIN APP_08 views.md, newest first); （すべて） shows
 * columns() in the legacy order — the rates and money figures for administrators only. The search box replaces
 * 日報一覧(検索特化); 日報実績 (a dashboard plugin) is not rebuilt. インライン編集 changes the entered values of a task
 * (not the lookups 作業者 / 注番 / 機械名 / ダンボール名称).
 */
final class DailyReportsTable
{
    public const VIEW_LIST = 'list';

    /** 日報一覧 */
    public const LIST_COLUMNS = ['worked_on', 'user_id', 'order_id', 'product_name', 'machine_id', 'cardboard_id', 'cardboard_used', 'glue', 'glue_cost',
        'started_at', 'ended_at', 'changeover_minutes', 'overtime_started_at', 'overtime_ended_at', 'changeover_count', 'produced', 'crew', 'per_person',
        'quality_checks', 'gloves', 'damaged_before', 'damaged_after', 'difficulty', 'notes'];

    /** The legacy （すべて） order. */
    private const ALL_COLUMNS = ['id', 'worked_on', 'user_id', 'worker_code', 'employee_code', 'sales_staff_code', 'department_code', 'work_rate', 'labor_cost',
        'machine_id', 'order_id', 'product_name', 'order_quantity', 'manufactured_quantity', 'manufacturing_remaining', 'cardboard_stock', 'cardboard_used',
        'cardboard_stock_after', 'glue', 'started_at', 'ended_at', 'changeover_minutes', 'changeover_count', 'produced', 'crew', 'per_person', 'quality_checks',
        'gloves', 'damaged_before', 'damaged_after', 'current_status', 'new_status', 'difficulty', 'notes', 'work_hours', 'machine_code', 'processing_rate',
        'changeover_rate', 'handwork_rate', 'machine_rate', 'printing_rate', 'plate_change_rate', 'ink_mixing_rate', 'machine_fee', 'sheet_fee', 'changeover_fee',
        'internal_cost', 'cardboard_cost', 'update_status', 'break_time', 'partition_stock', 'partition_used', 'partition_cost', 'partition_stock_after',
        'cardboard_id', 'partition_id', 'manufacturing_sales', 'sheet_and_changeover_fee', 'overtime_started_at', 'overtime_ended_at', 'overtime_break', 'day_type',
        'overtime_hours', 'overtime_labor_cost', 'overtime_rate'];

    /** Rates and money figures: administrators only. */
    private const COST_COLUMNS = ['work_rate', 'labor_cost', 'processing_rate', 'changeover_rate', 'handwork_rate', 'machine_rate', 'printing_rate',
        'plate_change_rate', 'ink_mixing_rate', 'machine_fee', 'sheet_fee', 'changeover_fee', 'internal_cost', 'cardboard_cost', 'partition_cost',
        'manufacturing_sales', 'sheet_and_changeover_fee', 'overtime_labor_cost', 'overtime_rate'];

    /** Calculated columns => their SQL (sorting). */
    private const EXPRESSIONS = [
        'labor_cost' => 'daily_reports.work_rate * daily_reports.work_hours',
        'machine_fee' => 'daily_reports.machine_rate * daily_reports.work_hours',
        'sheet_fee' => 'daily_reports.produced * daily_reports.processing_rate',
        'changeover_fee' => 'daily_reports.changeover_rate * daily_reports.changeover_count',
        'sheet_and_changeover_fee' => 'coalesce(daily_reports.produced * daily_reports.processing_rate, 0) + coalesce(daily_reports.changeover_rate * daily_reports.changeover_count, 0)',
        'overtime_labor_cost' => 'daily_reports.overtime_hours * daily_reports.overtime_rate',
        'per_person' => 'daily_reports.produced / nullif(daily_reports.crew, 0)',
    ];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (self::visible(self::ALL_COLUMNS) as $name) {
            $columns[$name] = DailyReportLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        return [ListView::table(self::VIEW_LIST, '日報一覧', self::LIST_COLUMNS, [['field' => 'id', 'direction' => 'desc']])];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = self::visible($livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS);

        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                if ($query->getQuery()->columns === null) {
                    $query->select('daily_reports.*');
                }

                // 在庫数 / 在庫数(仕切) of the boxes now (null while their stock is not kept)
                return $query->with(['worker', 'order', 'machine', 'cardboard', 'partition'])
                    ->addSelect(['cardboard_stock' => self::stockQuery('cardboard_id'), 'partition_stock' => self::stockQuery('partition_id')]);
            })
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(DailyReportResource::class))
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->emptyStateHeading('日報がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_08 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => DailyReportLayout::label($name);
        $boxes = fn (): array => Consumable::query()->where('kind', ConsumableKind::Cardboard->value)->orderByDesc('name')->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all();

        return [
            FilterField::date('worked_on', $label('worked_on')),
            FilterField::user('user_id', $label('user_id')),
            FilterField::text('order_id', $label('order_id'))->relationship('order', 'order_number'),
            FilterField::text('product_name', $label('product_name'))->relationship('order', 'product_name'),
            FilterField::choice('machine_id', $label('machine_id'), fn (): array => Machine::query()->orderByDesc('id')->pluck('name', 'id')
                ->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all()),
            FilterField::choice('cardboard_id', $label('cardboard_id'), $boxes),
            FilterField::number('cardboard_used', $label('cardboard_used')),
            FilterField::choice('partition_id', $label('partition_id'), $boxes),
            FilterField::number('partition_used', $label('partition_used')),
            FilterField::choice('glue', $label('glue'), ProductionChoices::options(ProductionChoices::GLUES)),
            FilterField::number('glue_cost', $label('glue_cost')),
            FilterField::number('changeover_minutes', $label('changeover_minutes')),
            FilterField::number('changeover_count', $label('changeover_count')),
            FilterField::number('produced', $label('produced')),
            FilterField::number('crew', $label('crew')),
            FilterField::choice('day_type', $label('day_type'), ProductionChoices::options(ProductionChoices::DAY_TYPES)),
            FilterField::number('damaged_before', $label('damaged_before')),
            FilterField::number('damaged_after', $label('damaged_after')),
            FilterField::choice('new_status', $label('new_status'), OrderChoices::options(OrderChoices::STATUSES)),
            FilterField::choice('difficulty', $label('difficulty'), ProductionChoices::options(ProductionChoices::DIFFICULTIES)),
            FilterField::textarea('notes', $label('notes')),
            FilterField::number('work_hours', $label('work_hours')),
            FilterField::number('overtime_hours', $label('overtime_hours')),
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
            InlineField::date('worked_on')->required(),
            InlineField::number('cardboard_used', 4),
            InlineField::number('partition_used', 4),
            InlineField::select('glue', ProductionChoices::options(ProductionChoices::GLUES)),
            InlineField::number('glue_cost', 4),
            InlineField::time('started_at')->required(),
            InlineField::time('ended_at')->required(),
            InlineField::time('break_time'),
            InlineField::number('changeover_minutes', 4),
            InlineField::number('changeover_count', 4),
            InlineField::number('produced', 4)->required(),
            InlineField::number('crew', 4)->required()->minValue(1),
            InlineField::select('day_type', ProductionChoices::options(ProductionChoices::DAY_TYPES))->required(),
            InlineField::time('overtime_started_at'),
            InlineField::time('overtime_ended_at'),
            InlineField::time('overtime_break'),
            InlineField::checkboxes('quality_checks', ProductionChoices::options(ProductionChoices::QUALITY_CHECKS))->required(),
            InlineField::checkbox('gloves', ProductionChoices::GLOVES),
            InlineField::number('damaged_before', 4)->required(),
            InlineField::number('damaged_after', 4)->required()->minValue(0),
            InlineField::select('difficulty', ProductionChoices::options(ProductionChoices::DIFFICULTIES)),
            InlineField::textarea('notes')->maxLength(4000),
        ];
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private static function visible(array $names): array
    {
        return array_values(array_filter($names, fn (string $name): bool => DailyReportLayout::showsCosts() || ! in_array($name, self::COST_COLUMNS, true)));
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(DailyReportLayout::label($name))->placeholder('');
        $number = fn (Closure $value): AppTextColumn => $column->alignEnd()->state(fn (DailyReport $record): string => AppFormat::number($value($record)));
        $price = fn (Closure $value): AppTextColumn => $column->alignEnd()->state(fn (DailyReport $record): string => AppFormat::price($value($record)));
        $by = fn (string $model, string $attribute, string $key): Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(
            $model::query()->select($attribute)->whereColumn((new $model)->getQualifiedKeyName(), "daily_reports.{$key}"),
            $direction,
        );
        $stock = fn (DailyReport $record, string $alias): ?string => is_numeric($value = $record->getAttribute($alias)) ? (string) $value : null;
        $byAlias = fn (Builder $query, string $direction): Builder => $query->orderBy($name, $direction);
        $expression = fn (string $sql): Closure => fn (Builder $query, string $direction): Builder => $query->orderByRaw($sql.' '.($direction === 'desc' ? 'desc' : 'asc'));
        $sheetFee = fn (DailyReport $record): string => TaskCosting::product($record->produced, $record->processing_rate);
        $changeoverFee = fn (DailyReport $record): string => TaskCosting::product($record->changeover_rate, $record->changeover_count);
        $time = fn (DailyReport $record): ?string => is_string($value = $record->getAttribute($name)) ? substr($value, 0, 5) : null;

        return match ($name) {
            'worked_on' => $column->date('Y-m-d'),
            // user names open the ユーザー情報 popup, so the cell is not a link to the record
            'user_id' => $column
                ->state(fn (DailyReport $record): HtmlString => UserPopup::html($record->worker))
                ->html()
                ->disabledClick()
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('worker', fn (Builder $q): Builder => $q->whereLike('name', "%{$search}%")))
                ->sortable(query: $by(User::class, 'name', 'user_id')),
            'worker_code', 'employee_code', 'sales_staff_code', 'department_code' => $column
                ->state(fn (DailyReport $record): ?string => AppFormat::text($record->worker->getAttribute($name)))
                ->sortable(query: $by(User::class, $name, 'user_id')),
            'order_id' => $column->state(fn (DailyReport $record): ?string => $record->order?->order_number)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('order', fn (Builder $q): Builder => $q->whereLike('order_number', "%{$search}%")))
                ->sortable(query: $by(Order::class, 'order_number', 'order_id')),
            'product_name' => $column->state(fn (DailyReport $record): ?string => $record->order?->product_name)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('order', fn (Builder $q): Builder => $q->whereLike('product_name', "%{$search}%")))
                ->sortable(query: $by(Order::class, 'product_name', 'order_id')),
            'order_quantity' => $number(fn (DailyReport $record): mixed => $record->order?->quantity)->sortable(query: $by(Order::class, 'quantity', 'order_id')),
            'manufactured_quantity', 'manufacturing_remaining' => $number(fn (DailyReport $record): mixed => $record->order?->getAttribute($name))
                ->sortable(query: $by(Order::class, $name, 'order_id')),
            'current_status' => $column->state(fn (DailyReport $record): ?string => $record->order?->status)->sortable(query: $by(Order::class, 'status', 'order_id')),
            'machine_id' => $column->state(fn (DailyReport $record): ?string => $record->machine?->name)->sortable(query: $by(Machine::class, 'name', 'machine_id')),
            'machine_code' => $column->state(fn (DailyReport $record): ?string => $record->machine?->code)->sortable(query: $by(Machine::class, 'code', 'machine_id')),
            'cardboard_id', 'partition_id' => $column->state(fn (DailyReport $record): ?string => $record->getRelationValue($name === 'cardboard_id' ? 'cardboard' : 'partition')?->name)
                ->sortable(query: $by(Consumable::class, 'name', $name)),
            'cardboard_stock', 'partition_stock' => $number(fn (DailyReport $record): ?string => $stock($record, $name))->sortable(query: $byAlias),
            'cardboard_stock_after' => $number(fn (DailyReport $record): ?string => self::stockAfter($stock($record, 'cardboard_stock'), $record->cardboard_used)),
            'partition_stock_after' => $number(fn (DailyReport $record): ?string => self::stockAfter($stock($record, 'partition_stock'), $record->partition_used)),
            'cardboard_used', 'partition_used', 'changeover_minutes', 'changeover_count', 'produced', 'crew', 'damaged_before', 'damaged_after' => $number(
                fn (DailyReport $record): mixed => $record->getAttribute($name),
            ),
            'per_person' => $number(fn (DailyReport $record): ?int => TaskCosting::perPerson($record->produced, $record->crew))
                ->sortable(query: $expression(self::EXPRESSIONS['per_person'])),
            'work_hours', 'overtime_hours' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::number($state)),
            'started_at', 'ended_at', 'break_time', 'overtime_started_at', 'overtime_ended_at', 'overtime_break' => $column->state($time),
            'quality_checks' => $column->state(fn (DailyReport $record): ?string => implode('、', (array) $record->quality_checks) ?: null),
            'gloves' => $column->state(fn (DailyReport $record): ?string => $record->gloves ? ProductionChoices::GLOVES : null),
            'update_status' => $column->state(fn (DailyReport $record): ?string => $record->update_status ? ProductionChoices::UPDATE_STATUS : null),
            'labor_cost' => $price(fn (DailyReport $record): string => TaskCosting::product(AppFormat::text($record->work_rate), $record->work_hours))
                ->sortable(query: $expression(self::EXPRESSIONS[$name])),
            'overtime_labor_cost' => $price(fn (DailyReport $record): string => TaskCosting::product($record->overtime_hours, AppFormat::text($record->overtime_rate)))
                ->sortable(query: $expression(self::EXPRESSIONS[$name])),
            'machine_fee' => $price(fn (DailyReport $record): string => TaskCosting::product($record->machine_rate, $record->work_hours))
                ->sortable(query: $expression(self::EXPRESSIONS[$name])),
            'sheet_fee' => $price($sheetFee)->sortable(query: $expression(self::EXPRESSIONS[$name])),
            'changeover_fee' => $price($changeoverFee)->sortable(query: $expression(self::EXPRESSIONS[$name])),
            'sheet_and_changeover_fee' => $price(fn (DailyReport $record): string => bcadd($sheetFee($record), $changeoverFee($record), 4))
                ->sortable(query: $expression(self::EXPRESSIONS[$name])),
            'internal_cost', 'manufacturing_sales' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            'work_rate', 'overtime_rate', 'glue_cost', 'cardboard_cost', 'partition_cost', 'processing_rate', 'changeover_rate', 'handwork_rate', 'machine_rate',
            'printing_rate', 'plate_change_rate', 'ink_mixing_rate' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::price($state)),
            default => $column,
        };
    }

    /**
     * Sum of the stock movements of the box in `$key`.
     *
     * @return Builder<ConsumableStockMovement>
     */
    private static function stockQuery(string $key): Builder
    {
        return ConsumableStockMovement::query()->selectRaw('sum(quantity)')->whereColumn('consumable_stock_movements.consumable_id', "daily_reports.{$key}");
    }

    /** 在庫数更新値 = 在庫数 − 使用数 (null while the stock is not kept). */
    private static function stockAfter(?string $stock, ?string $used): ?string
    {
        return $stock === null ? null : bcsub($stock, $used ?? '0', 4);
    }
}
