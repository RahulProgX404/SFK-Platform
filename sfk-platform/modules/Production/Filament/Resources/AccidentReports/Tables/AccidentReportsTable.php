<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\AccidentReports\Tables;

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
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Models\Machine;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Order;
use Modules\Production\Filament\Resources\AccidentReports\AccidentReportLayout;
use Modules\Production\Filament\Resources\AccidentReports\AccidentReportResource;
use Modules\Production\Models\AccidentReport;

/**
 * Record list of 14. 事故報告管理 with the legacy view 事故報告一覧 (SYSTEM_BRAIN APP_14 views.md: 事故発生日, 報告者氏名, 注番,
 * 得意先名, 品名, 数量, 合計損害額, newest first); （すべて） shows columns() in order and the search box replaces
 * 事故報告一覧(検索特化). インライン編集 changes 事故発生日, 数量 and 備考.
 */
final class AccidentReportsTable
{
    public const VIEW_LIST = 'list';

    public const LIST_COLUMNS = ['occurred_on', 'reporter_id', 'order_id', 'customer', 'product_name', 'quantity', 'total_damage'];

    private const USERS = ['reporter_id' => 'reporter', 'maker1_id' => 'maker1', 'maker2_id' => 'maker2'];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', 'occurred_on', 'reporter_id', 'order_id', 'customer', 'product_name', 'quantity', 'machine_id', 'maker1_id', 'maker2_id', 'sales_users',
            'description', 'cause', 'response', 'prevention', 'notes', 'total_damage', 'machine_record', 'reporter_code', 'customer_code', 'created_at'] as $name) {
            $columns[$name] = AccidentReportLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        return [ListView::table(self::VIEW_LIST, '事故報告一覧', self::LIST_COLUMNS, [['field' => 'id', 'direction' => 'desc']])];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['reporter', 'maker1', 'maker2', 'salesUsers', 'machine', 'order.customer']))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(AccidentReportResource::class))
            ->emptyStateIcon(Heroicon::OutlinedExclamationTriangle)
            ->emptyStateHeading('事故報告がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_14 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => AccidentReportLayout::label($name);

        return [
            FilterField::date('occurred_on', $label('occurred_on')),
            FilterField::user('reporter_id', $label('reporter_id')),
            FilterField::text('order_id', $label('order_id'))->query(self::filterOrderNumber(...)),
            FilterField::number('quantity', $label('quantity')),
            FilterField::choice('machine_id', $label('machine_id'), AccidentReportLayout::machineOptions(...)),
            FilterField::user('maker1_id', $label('maker1_id')),
            FilterField::user('maker2_id', $label('maker2_id')),
            FilterField::user('sales_users', $label('sales_users'))->relationship('salesUsers', 'id'),
            ...array_map(fn (string $name): FilterField => FilterField::textarea($name, $label($name)), ['description', 'cause', 'response', 'prevention', 'notes']),
            FilterField::number('total_damage', $label('total_damage')),
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
            InlineField::date('occurred_on')->required(),
            InlineField::number('quantity', 4)->minValue(-999999999999)->maxValue(999999999999),
            InlineField::textarea('notes'),
        ];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(AccidentReportLayout::label($name))->placeholder('');
        $order = fn (Closure $select): Closure => fn (Builder $query, string $direction): Builder => $query->orderBy($select(), $direction);
        $user = fn (string $relation): Closure => fn (AccidentReport $record): HtmlString => UserPopup::html(
            $record->getRelationValue($relation) instanceof User ? $record->getRelationValue($relation) : null,
        );

        return match (true) {
            in_array($name, ['description', 'cause', 'response', 'prevention', 'notes'], true) => $column->searchable()->limit(60),
            $name === 'occurred_on' => $column->date('Y-m-d'),
            $name === 'created_at' => $column->dateTime('Y-m-d H:i'),
            $name === 'quantity' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::number($state)),
            $name === 'total_damage' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            // user names open the ユーザー情報 popup, so the cell is not a link to the record
            isset(self::USERS[$name]) => $column->state($user(self::USERS[$name]))->html()->disabledClick()
                ->sortable(query: $order(fn (): Builder => User::query()->select('users.name')->whereColumn('users.id', "accident_reports.{$name}"))),
            $name === 'sales_users' => $column
                ->state(fn (AccidentReport $record): HtmlString => UserPopup::list($record->salesUsers))
                ->html()
                ->disabledClick()
                ->sortable(query: $order(fn (): Builder => User::query()->select('users.name')->join('accident_report_user', 'accident_report_user.user_id', '=', 'users.id')
                    ->whereColumn('accident_report_user.accident_report_id', 'accident_reports.id')->orderBy('users.name')->limit(1))),
            $name === 'order_id' => $column
                ->state(fn (AccidentReport $record): ?string => $record->order?->order_number)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('order', fn (Builder $q): Builder => $q->whereLike('order_number', "%{$search}%")))
                ->sortable(query: $order(fn (): Builder => Order::query()->select('orders.order_number')->whereColumn('orders.id', 'accident_reports.order_id'))),
            $name === 'customer' => $column
                ->state(fn (AccidentReport $record): ?string => $record->order?->customer?->name)
                ->sortable(query: $order(fn (): Builder => Partner::query()->select('partners.name')->join('orders', 'orders.customer_id', '=', 'partners.id')
                    ->whereColumn('orders.id', 'accident_reports.order_id'))),
            $name === 'product_name' => $column
                ->state(fn (AccidentReport $record): ?string => $record->order?->product_name)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('order', fn (Builder $q): Builder => $q->whereLike('product_name', "%{$search}%")))
                ->sortable(query: $order(fn (): Builder => Order::query()->select('orders.product_name')->whereColumn('orders.id', 'accident_reports.order_id'))),
            $name === 'machine_id' => $column
                ->state(fn (AccidentReport $record): ?string => $record->machine?->name)
                ->sortable(query: $order(fn (): Builder => Machine::query()->select('machines.name')->whereColumn('machines.id', 'accident_reports.machine_id'))),
            $name === 'machine_record' => $column->state(fn (AccidentReport $record): ?string => $record->machine_id === null ? null : (string) $record->machine_id)
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('accident_reports.machine_id', $direction)),
            $name === 'reporter_code' => $column->state(fn (AccidentReport $record): ?string => $record->reporter->getAttribute('employee_code'))
                ->sortable(query: $order(fn (): Builder => User::query()->select('users.employee_code')->whereColumn('users.id', 'accident_reports.reporter_id'))),
            $name === 'customer_code' => $column->state(fn (AccidentReport $record): ?string => $record->order?->customer?->code)
                ->sortable(query: $order(fn (): Builder => Partner::query()->select('partners.code')->join('orders', 'orders.customer_id', '=', 'partners.id')
                    ->whereColumn('orders.id', 'accident_reports.order_id'))),
            default => $column,
        };
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
