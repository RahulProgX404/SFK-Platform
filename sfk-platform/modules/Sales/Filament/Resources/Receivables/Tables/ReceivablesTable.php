<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables\Tables;

use Closure;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Filters\DateMode;
use Modules\Core\Domain\Filters\FilterCondition;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Filament\Resources\Receivables\CycleReceivableResource;
use Modules\Sales\Filament\Resources\Receivables\ReceivableLayout;
use Modules\Sales\Filament\Resources\Receivables\ReceivableResource;
use Modules\Sales\Filament\SalesFormat;
use Modules\Sales\Models\ReceivableMonth;

/**
 * Record list of 23. 売掛管理 and 23-2. 売掛管理(締日別) with the legacy views (SYSTEM_BRAIN APP_23 / APP_23-2 views.md), by
 * 得意先名 (23-2: then newest 年月 first); （すべて） shows columns() in order. 23: 先月売掛一覧, 当月売掛一覧 and 売掛一覧 —
 * 先々月売掛一覧 was fixed to 2025年06月 and is not carried over. 23-2: 売掛一覧 and one list per 締日 (also 随時請求 and
 * 締日空欄). The search box replaces 売掛一覧(検索特化). インライン編集 changes 消費税端数調整 (the only figure entered by hand).
 */
final class ReceivablesTable
{
    public const VIEW_LIST = 'list';

    public const VIEW_LAST_MONTH = 'last-month';

    public const VIEW_THIS_MONTH = 'this-month';

    public const LIST_COLUMNS = ['year_month', 'customer_id', 'previous_balance', 'received', 'net_balance', 'sales_amount', 'tax_amount', 'month_total', 'closing_balance'];

    private const AMOUNTS = ['previous_balance', 'received', 'net_balance', 'sales_amount', 'tax_amount', 'tax_adjustment', 'month_total', 'closing_balance',
        'sales_standard', 'sales_reduced', 'sales_exempt', 'sales_out_of_scope', 'tax_standard', 'tax_reduced'];

    /** Columns from the customer master: column => partner column. */
    private const MASTER = ['billing_closing_day' => 'closing_day', 'invoice_issuance' => 'invoice_issuance', 'delivery_note_issuance' => 'delivery_note_issuance',
        'collection_month' => 'collection_month', 'collection_day' => 'collection_day'];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(ReceivableBasis $basis): array
    {
        $names = ['id', 'customer_code', 'customer_id', 'previous_balance', 'received', 'net_balance', 'sales_amount', 'month_total', 'year_month', 'month_start',
            'closing_balance', 'tax_adjustment', 'sales_standard', 'sales_reduced', 'sales_exempt', 'sales_out_of_scope', 'tax_standard', 'tax_reduced',
            ...($basis === ReceivableBasis::Cycle ? ['closing_day'] : []),
            'tax_amount', 'carries_forward', 'billing_closing_day', 'invoice_issuance', 'delivery_note_issuance', 'collection_month', 'collection_day', 'created_at'];

        $columns = [];
        foreach ($names as $name) {
            $columns[$name] = ReceivableLayout::label($name, $basis);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(ReceivableBasis $basis): array
    {
        if ($basis === ReceivableBasis::Calendar) {
            $month = fn (DateMode $mode): array => [['field' => 'month_start', 'operator' => FilterOperator::Equals->value, 'values' => [], 'mode' => $mode->value]];

            return [
                ListView::table(self::VIEW_LAST_MONTH, '先月売掛一覧', self::LIST_COLUMNS, [], $month(DateMode::LastMonth)),
                ListView::table(self::VIEW_THIS_MONTH, '当月売掛一覧', self::LIST_COLUMNS, [], $month(DateMode::ThisMonth)),
                ListView::table(self::VIEW_LIST, '売掛一覧', [...self::LIST_COLUMNS, 'carries_forward']),
            ];
        }

        $views = [ListView::table(self::VIEW_LIST, '売掛一覧', self::LIST_COLUMNS)];
        foreach (ClosingDay::cases() as $day) {
            $views[] = ListView::table('closing-'.$day->name, '売掛一覧 - '.($day === ClosingDay::AdHoc ? $day->value : $day->value.'締'), self::LIST_COLUMNS, [],
                [['field' => 'closing_day', 'operator' => FilterOperator::In->value, 'values' => [$day->value]]]);
        }
        $views[] = ListView::table('closing-blank', '売掛一覧 - 締日空欄', self::LIST_COLUMNS, [['field' => 'id', 'direction' => 'desc']],
            [['field' => 'closing_day', 'operator' => FilterOperator::In->value, 'values' => ['']]]);

        return $views;
    }

    public static function configure(Table $table, ReceivableBasis $basis): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('customer'))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name, $basis), $names),
            ])
            // legacy sort: 得意先名, then (23-2) the newest 年月
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy(self::customerName(), 'asc')
                ->when($basis === ReceivableBasis::Cycle, fn (Builder $q): Builder => $q->orderByDesc('receivable_months.year_month')))
            ->recordActions(AppRecordActions::make($basis === ReceivableBasis::Cycle ? CycleReceivableResource::class : ReceivableResource::class))
            ->emptyStateIcon(Heroicon::OutlinedScale)
            ->emptyStateHeading('売掛がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_23 / APP_23-2 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(ReceivableBasis $basis): array
    {
        $label = fn (string $name): string => ReceivableLayout::label($name, $basis);
        $days = array_map(fn (ClosingDay $day): string => $day->value, ClosingDay::cases());

        return [
            FilterField::text('year_month', $label('year_month')),
            FilterField::choice('customer_id', $label('customer_id'), fn (): array => Partner::query()->where('is_customer', true)->orderByDesc('id')
                ->pluck('name', 'id')->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all()),
            ...($basis === ReceivableBasis::Cycle ? [FilterField::choice('closing_day', $label('closing_day'), array_combine($days, $days))] : []),
            ...array_map(fn (string $name): FilterField => FilterField::number($name, $label($name)), self::AMOUNTS),
            FilterField::choice('carries_forward', $label('carries_forward'), ['要作成' => '要作成'])->query(self::filterCarriesForward(...)),
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
        return [InlineField::number('tax_adjustment')->required()];
    }

    private static function column(string $name, ReceivableBasis $basis): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(ReceivableLayout::label($name, $basis))->placeholder('');
        $partner = fn (string $attribute): Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(
            Partner::query()->select("partners.{$attribute}")->whereColumn('partners.id', 'receivable_months.customer_id'),
            $direction,
        );

        return match (true) {
            in_array($name, self::AMOUNTS, true) => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => SalesFormat::yen($state)),
            $name === 'year_month' => $column->state(fn (ReceivableMonth $record): string => $record->monthLabel()),
            $name === 'month_start' => $column->date('Y-m-d'),
            $name === 'created_at' => $column->dateTime('Y-m-d H:i'),
            $name === 'customer_id' => $column
                ->state(fn (ReceivableMonth $record): string => $record->customer->name)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('customer', fn (Builder $q): Builder => $q->whereLike('name', "%{$search}%")))
                ->sortable(query: $partner('name')),
            $name === 'customer_code' => $column->state(fn (ReceivableMonth $record): ?string => $record->customer->code)->sortable(query: $partner('code')),
            isset(self::MASTER[$name]) => $column
                ->state(fn (ReceivableMonth $record): ?string => SalesFormat::text($record->customer->getAttribute(self::MASTER[$name])))
                ->sortable(query: $partner(self::MASTER[$name])),
            $name === 'carries_forward' => $column->state(fn (ReceivableMonth $record): string => $record->carriesForward())
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw('(receivable_months.closing_balance > 0) '.($direction === 'desc' ? 'desc' : 'asc'))),
            default => $column,
        };
    }

    /**
     * 得意先名 of the row, for sorting.
     *
     * @return Builder<Partner>
     */
    private static function customerName(): Builder
    {
        return Partner::query()->select('partners.name')->whereColumn('partners.id', 'receivable_months.customer_id');
    }

    /**
     * 次月分判定 「要作成」 while 当月売掛残 is open (BR-AR-05).
     *
     * @param  Builder<*>  $query
     */
    private static function filterCarriesForward(Builder $query, FilterCondition $condition): void
    {
        $values = $condition->values;
        $wantsOpen = in_array('要作成', $values, true);
        $wantsBlank = in_array('', $values, true);
        if ($condition->operator === FilterOperator::NotIn) {
            [$wantsOpen, $wantsBlank] = [! $wantsOpen, ! $wantsBlank];
        }

        match (true) {
            $wantsOpen && $wantsBlank => $query,
            $wantsOpen => $query->where('receivable_months.closing_balance', '>', 0),
            $wantsBlank => $query->where('receivable_months.closing_balance', '<=', 0),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
