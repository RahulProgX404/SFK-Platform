<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Sales\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Filters\FilterCondition;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Domain\Tax\TaxRate;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Order;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Filament\Resources\Sales\SaleLayout;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Filament\Resources\Sales\Schemas\SaleForm;
use Modules\Sales\Filament\SalesFormat;
use Modules\Sales\Models\DeliveryNote;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\Sale;

/**
 * Record list of 06. 売上入力 with the legacy views (SYSTEM_BRAIN APP_06 views.md): 自身が作成した売上一覧 (作成者 =
 * ログインユーザー), 売上一覧 and one list per 請求締日 (sorted by customer); （すべて） shows columns() in order (the helper
 * fields of the legacy app are left out). The search box replaces 売上一覧(検索特化); 得意先別売上 (krewDashboard) is
 * not rebuilt.
 *
 * Administrators check rows for the legacy list buttons (ListSales::menuActions()) and 選択した売上を削除 (削除 of
 * 06_SalesEntry.bundle.js; their rows leave the delivery notes and invoices).
 * インライン編集 changes only fields without checks or copies: 納品予定日, 単位, 摘要.
 */
final class SalesTable
{
    public const VIEW_MINE = 'mine';

    public const VIEW_LIST = 'list';

    private const BASE_COLUMNS = ['sold_on', 'sales_number', 'customer_id', 'order_id', 'product_name', 'quantity', 'unit_price', 'amount',
        'estimated_cost', 'estimated_expense', 'delivery_due_on', 'delivery_note_id', 'invoice_id'];

    /** Columns of the legacy view 売上一覧, in order. */
    public const LIST_COLUMNS = ['needs', 'handover', 'sold_on', 'sales_number', 'closing_day', 'closing_day_override', 'customer_id', 'order_id', 'product_name',
        'quantity', 'unit_price', 'amount', 'estimated_cost', 'estimated_expense', 'tax', 'amount_with_tax', 'delivery_due_on', 'delivery_note_id', 'invoice_id'];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', 'kind', 'sold_on', 'sales_number', 'customer_id', 'delivery_due_on', 'order_id', 'order_customer', 'closing_day', 'product_name',
            'quantity', 'unit_price', 'amount', 'estimated_cost', 'is_fsc', 'delivery_note_recipient', 'unit', 'invoice_recipient', 'manual_postal_code',
            'manual_address', 'other_address', 'delivered', 'assignees', 'needs', 'handover', 'tax', 'tax_rate', 'amount_with_tax', 'summary',
            'delivery_note_id', 'invoiced', 'invoice_id', 'locked', 'closing_day_override', 'billing_date', 'estimated_expense', 'billing_month'] as $name) {
            $columns[$name] = SaleLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        $byId = [['field' => 'id', 'direction' => 'desc']];
        $views = [
            ListView::table(self::VIEW_MINE, '自身が作成した売上一覧', [...array_slice(self::BASE_COLUMNS, 0, 2), 'closing_day', ...array_slice(self::BASE_COLUMNS, 2)], $byId,
                [['field' => 'created_by', 'operator' => FilterOperator::In->value, 'values' => [FilterField::LOGIN_USER]]]),
            ListView::table(self::VIEW_LIST, '売上一覧', self::LIST_COLUMNS, $byId),
        ];

        foreach (ClosingDay::cases() as $day) {
            $columns = match ($day) {
                ClosingDay::Day20 => ['delivered', 'closing_day', ...self::BASE_COLUMNS],
                ClosingDay::Day25 => array_values(array_diff(self::BASE_COLUMNS, ['estimated_cost', 'estimated_expense'])),
                ClosingDay::AdHoc => [...array_slice(self::BASE_COLUMNS, 0, 2), 'closing_day', ...array_slice(self::BASE_COLUMNS, 2)],
                default => self::BASE_COLUMNS,
            };
            $views[] = ListView::table(
                'closing-'.$day->name,
                $day === ClosingDay::AdHoc ? '随時請求一覧' : $day->value.'締日一覧',
                $columns,
                [['field' => 'customer_id', 'direction' => 'asc'], ['field' => 'id', 'direction' => 'desc']],
                [['field' => 'closing_day', 'operator' => FilterOperator::In->value, 'values' => [$day->value]]],
            );
        }

        return $views;
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'order.customer', 'deliveryNote', 'invoice', 'assignees']))
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
            ->recordActions(AppRecordActions::make(SaleResource::class))
            // check boxes for the legacy buttons working on the checked rows (ListSales::menuActions())
            ->selectable(fn (): bool => SaleForm::isAdministrator())
            ->toolbarActions([self::bulkActions()])
            ->emptyStateIcon(Heroicon::OutlinedBanknotes)
            ->emptyStateHeading('売上がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_06 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => SaleLayout::label($name);
        $rates = array_map(fn (TaxRate $rate): string => $rate->value, TaxRate::cases());
        $days = array_map(fn (ClosingDay $day): string => $day->value, ClosingDay::cases());

        return [
            FilterField::choice('kind', $label('kind'), SaleChoices::options(SaleChoices::KINDS)),
            FilterField::date('sold_on', $label('sold_on')),
            FilterField::text('sales_number', $label('sales_number')),
            FilterField::choice('customer_id', $label('customer_id'), fn (): array => Partner::query()->where('is_customer', true)->orderByDesc('id')
                ->pluck('name', 'id')->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all()),
            FilterField::choice('closing_day', $label('closing_day'), array_combine($days, $days)),
            FilterField::choice('closing_day_override', $label('closing_day_override'), SaleChoices::options(SaleChoices::CLOSING_DAY_OVERRIDES)),
            FilterField::date('billing_date', $label('billing_date')),
            FilterField::date('delivery_due_on', $label('delivery_due_on')),
            FilterField::text('manual_postal_code', $label('manual_postal_code')),
            FilterField::text('manual_address', $label('manual_address')),
            FilterField::number('quantity', $label('quantity')),
            FilterField::choice('unit', $label('unit'), SaleChoices::options(SaleChoices::UNITS)),
            FilterField::number('unit_price', $label('unit_price')),
            FilterField::number('amount', $label('amount')),
            FilterField::number('estimated_cost', $label('estimated_cost')),
            FilterField::number('estimated_expense', $label('estimated_expense')),
            FilterField::choice('tax_rate', $label('tax_rate'), array_combine($rates, $rates)),
            FilterField::number('tax', $label('tax')),
            FilterField::text('summary', $label('summary')),
            FilterField::text('order_id', $label('order_id'))->query(self::filterOrderNumber(...)),
            FilterField::text('product_name', $label('product_name')),
            FilterField::user('assignees', $label('assignees'))->relationship('assignees', 'id'),
            FilterField::choice('needs', $label('needs'), SaleChoices::options(SaleChoices::NEEDS))->query(fn (Builder $query, FilterCondition $condition) => self::filterJson($query, 'needs', $condition)),
            FilterField::choice('handover', $label('handover'), SaleChoices::options(SaleChoices::HANDOVERS))->query(fn (Builder $query, FilterCondition $condition) => self::filterJson($query, 'handover', $condition)),
            FilterField::choice('delivered', $label('delivered'), ['済' => '済'])->query(fn (Builder $query, FilterCondition $condition) => self::filterLinked($query, 'delivery_note_id', $condition)),
            FilterField::text('delivery_note_id', $label('delivery_note_id'))->query(fn (Builder $query, FilterCondition $condition) => self::filterNumber($query, 'deliveryNote', $condition)),
            FilterField::choice('invoiced', $label('invoiced'), ['済' => '済'])->query(fn (Builder $query, FilterCondition $condition) => self::filterLinked($query, 'invoice_id', $condition)),
            FilterField::text('invoice_id', $label('invoice_id'))->query(fn (Builder $query, FilterCondition $condition) => self::filterNumber($query, 'invoice', $condition)),
            FilterField::choice('locked', $label('locked'), ['可' => '可', '不可' => '不可'])->query(self::filterLocked(...)),
            FilterField::number('id', $label('id')),
            FilterField::user('created_by', $label('creator')),
            FilterField::dateTime('created_at', $label('created_at')),
            FilterField::user('updated_by', $label('updater')),
            FilterField::dateTime('updated_at', $label('updated_at')),
        ];
    }

    /**
     * インライン編集: the fields without save checks or copies (the rest changes on the edit screen).
     *
     * @return list<InlineField>
     */
    public static function inlineFields(): array
    {
        return [
            InlineField::date('delivery_due_on')->required(),
            InlineField::select('unit', SaleChoices::options(SaleChoices::UNITS)),
            InlineField::text('summary')->maxLength(255),
        ];
    }

    private static function bulkActions(): BulkActionGroup
    {
        return BulkActionGroup::make([
            DeleteBulkAction::make()
                ->label('選択した売上を削除')
                ->modalHeading('選択した売上を削除しますか？')
                ->modalSubmitActionLabel('削除する')
                ->visible(fn (): bool => SaleForm::isAdministrator() && SaleResource::canDeleteAny()),
        ]);
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)
            ->label(SaleLayout::label($name))
            ->placeholder('');
        $subquery = fn (string $table, string $column, string $key): \Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(
            $table === 'partners' ? Partner::query()->select("partners.{$column}")->whereColumn('partners.id', "sales.{$key}")
                : ($table === 'orders' ? Order::query()->select("orders.{$column}")->whereColumn('orders.id', "sales.{$key}")
                    : ($table === 'delivery_notes' ? DeliveryNote::query()->select('delivery_notes.number')->whereColumn('delivery_notes.id', "sales.{$key}")
                        : Invoice::query()->select('invoices.number')->whereColumn('invoices.id', "sales.{$key}"))),
            $direction,
        );

        return match ($name) {
            'sales_number', 'product_name', 'summary' => $column->searchable(),
            'sold_on', 'delivery_due_on', 'billing_date' => $column->date('Y-m-d'),
            'quantity' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => SalesFormat::number($state)),
            'unit_price' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => SalesFormat::price($state)),
            'amount', 'estimated_cost', 'estimated_expense', 'tax' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => SalesFormat::yen($state)),
            'amount_with_tax' => $column->alignEnd()
                ->state(fn (Sale $record): string => SalesFormat::yen($record->amountWithTax()))
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw('(sales.amount + sales.tax) '.($direction === 'asc' ? 'asc' : 'desc'))),
            'customer_id' => $column
                ->state(fn (Sale $record): string => $record->customer->name)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('customer', fn (Builder $q): Builder => $q->whereLike('name', "%{$search}%")))
                ->sortable(query: $subquery('partners', 'name', 'customer_id')),
            'order_id' => $column
                ->state(fn (Sale $record): ?string => $record->order?->order_number)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('order', fn (Builder $q): Builder => $q->whereLike('order_number', "%{$search}%")))
                ->sortable(query: $subquery('orders', 'order_number', 'order_id')),
            'order_customer' => $column
                ->state(fn (Sale $record): ?string => $record->order?->customer?->name)
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Partner::query()->select('partners.name')->join('orders', 'orders.customer_id', '=', 'partners.id')->whereColumn('orders.id', 'sales.order_id'),
                    $direction,
                )),
            'is_fsc' => $column
                ->state(fn (Sale $record): ?string => $record->order?->is_fsc === true ? SaleChoices::FSC_CODE : null)
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(Order::query()->select('orders.is_fsc')->whereColumn('orders.id', 'sales.order_id'), $direction)),
            'delivery_note_id' => $column
                ->state(fn (Sale $record): ?string => $record->deliveryNote?->number)
                ->sortable(query: $subquery('delivery_notes', 'number', 'delivery_note_id')),
            'invoice_id' => $column
                ->state(fn (Sale $record): ?string => $record->invoice?->number)
                ->sortable(query: $subquery('invoices', 'number', 'invoice_id')),
            'delivered', 'invoiced' => $column
                ->state(fn (Sale $record): ?string => $record->getAttribute($name === 'delivered' ? 'delivery_note_id' : 'invoice_id') !== null ? '済' : null)
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                    '(case when sales.'.($name === 'delivered' ? 'delivery_note_id' : 'invoice_id').' is null then 0 else 1 end) '.($direction === 'asc' ? 'asc' : 'desc'),
                )),
            'needs', 'handover' => $column->state(fn (Sale $record): ?string => implode('、', (array) $record->getAttribute($name)) ?: null),
            'other_address' => $column->state(fn (Sale $record): ?string => $record->other_address ? 'する' : null),
            'locked' => $column->state(fn (Sale $record): string => $record->locked ? '不可' : '可'),
            'billing_month' => $column->formatStateUsing(fn (mixed $state): string => SalesFormat::month(is_string($state) ? $state : null)),
            'delivery_note_recipient', 'invoice_recipient' => $column,
            // user names open the ユーザー情報 popup, so the cell is not a link to the record
            'assignees' => $column
                ->state(fn (Sale $record): HtmlString => UserPopup::list($record->assignees))
                ->html()
                ->disabledClick()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    User::query()->select('users.name')->join('sale_user', 'sale_user.user_id', '=', 'users.id')->whereColumn('sale_user.sale_id', 'sales.id')
                        ->orderBy('users.name')->limit(1),
                    $direction,
                )),
            default => $column,
        };
    }

    /**
     * 注番 is the job's number.
     *
     * @param  Builder<*>  $query
     */
    private static function filterOrderNumber(Builder $query, FilterCondition $condition): void
    {
        self::filterNumber($query, 'order', $condition, 'order_number');
    }

    /**
     * A document / job number of a related record, compared like a text field.
     *
     * @param  Builder<*>  $query
     */
    private static function filterNumber(Builder $query, string $relation, FilterCondition $condition, string $column = 'number'): void
    {
        $value = $condition->values[0] ?? '';
        $has = fn (callable $where): Builder => $query->whereHas($relation, $where);

        match ($condition->operator) {
            FilterOperator::Contains => $has(fn (Builder $q): Builder => $q->whereLike($column, '%'.$value.'%')),
            FilterOperator::NotContains => $query->whereDoesntHave($relation, fn (Builder $q): Builder => $q->whereLike($column, '%'.$value.'%')),
            FilterOperator::Equals => $value === '' ? $query->whereDoesntHave($relation) : $has(fn (Builder $q): Builder => $q->where($column, $value)),
            FilterOperator::NotEquals => $value === '' ? $query->whereHas($relation) : $query->whereDoesntHave($relation, fn (Builder $q): Builder => $q->where($column, $value)),
            FilterOperator::OneOf => $has(fn (Builder $q): Builder => $q->whereIn($column, $condition->values)),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * 納品書作成 / 請求書作成 「済」: linked to a document; "--" = not yet.
     *
     * @param  Builder<*>  $query
     */
    private static function filterLinked(Builder $query, string $column, FilterCondition $condition): void
    {
        $done = in_array('済', $condition->values, true);
        $blank = in_array('', $condition->values, true);
        if ($condition->operator === FilterOperator::NotIn) {
            [$done, $blank] = [! $done, ! $blank];
        }

        match (true) {
            $done && $blank => null,
            $done => $query->whereNotNull("sales.{$column}"),
            $blank => $query->whereNull("sales.{$column}"),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * 必要なもの / 受け渡し: any of the chosen options; "--" = none chosen.
     *
     * @param  Builder<*>  $query
     */
    private static function filterJson(Builder $query, string $column, FilterCondition $condition): void
    {
        $chosen = array_values(array_filter($condition->values, fn (string $value): bool => $value !== ''));
        $blank = in_array('', $condition->values, true);
        $matches = function (Builder $q) use ($column, $chosen, $blank): void {
            foreach ($chosen as $value) {
                $q->orWhereJsonContains("sales.{$column}", $value);
            }
            if ($blank) {
                $q->orWhereNull("sales.{$column}")->orWhereJsonLength("sales.{$column}", 0);
            }
        };

        $condition->operator === FilterOperator::NotIn ? $query->whereNot($matches) : $query->where($matches);
    }

    /**
     * 編集 可 / 不可
     *
     * @param  Builder<*>  $query
     */
    private static function filterLocked(Builder $query, FilterCondition $condition): void
    {
        $locked = in_array('不可', $condition->values, true);
        $open = in_array('可', $condition->values, true);
        if ($condition->operator === FilterOperator::NotIn) {
            [$locked, $open] = [! $locked, ! $open];
        }

        match (true) {
            $locked && $open => null,
            $locked => $query->where('sales.locked', true),
            $open => $query->where('sales.locked', false),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
