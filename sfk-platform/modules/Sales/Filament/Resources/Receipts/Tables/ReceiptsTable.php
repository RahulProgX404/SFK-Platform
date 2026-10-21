<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receipts\Tables;

use App\Models\User;
use Closure;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Billing\CollectionMonth;
use Modules\Core\Domain\Filters\FilterCondition;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Filament\Resources\Receipts\ReceiptLayout;
use Modules\Sales\Filament\Resources\Receipts\ReceiptResource;
use Modules\Sales\Filament\SalesFormat;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\Receipt;

/**
 * Record list of 21. 売入金管理（売上） with the legacy views (SYSTEM_BRAIN APP_21 views.md): 入金管理一覧　未入金 (入金不足 ≥
 * ¥1, oldest 請求日 first) and 入金管理一覧 (newest first); （すべて） shows columns() in order. The legacy 未入金 view
 * sorted by 締日(マスタ) and 得意先名 first — customer master columns, sorted by a click on their header here. The search box
 * replaces 入金管理一覧(検索特化). インライン編集 changes 備考.
 */
final class ReceiptsTable
{
    public const VIEW_UNPAID = 'unpaid';

    public const VIEW_LIST = 'list';

    public const UNPAID_COLUMNS = ['billed_on', 'invoice_id', 'customer_id', 'billed_amount', 'settled_amount', 'shortfall', 'assignees', 'master_closing_day',
        'collection_month', 'collection_day', 'notes'];

    public const LIST_COLUMNS = ['billed_on', 'invoice_id', 'customer_id', 'billed_amount', 'assignees', 'master_closing_day', 'closing_day', 'collection_month',
        'collection_day', 'notes', 'settled_amount', 'overpayment', 'shortfall'];

    /** 入金不足 / 過入金 in SQL (positive when there is one). */
    private const SHORTFALL_SQL = '(receipts.billed_amount - receipts.settled_amount)';

    private const OVERPAYMENT_SQL = '(receipts.settled_amount - receipts.billed_amount)';

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', 'billed_on', 'customer_id', 'assignees', 'collection_month', 'collection_day', 'master_closing_day', 'notes', 'customer_code',
            'billed_amount', 'invoice_id', 'settled_amount', 'shortfall', 'overpayment', 'closing_day', 'created_at'] as $name) {
            $columns[$name] = ReceiptLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        return [
            ListView::table(self::VIEW_UNPAID, '入金管理一覧　未入金', self::UNPAID_COLUMNS,
                [['field' => 'billed_on', 'direction' => 'asc'], ['field' => 'id', 'direction' => 'desc']],
                [['field' => 'shortfall', 'operator' => FilterOperator::GreaterOrEqual->value, 'values' => ['1']]]),
            ListView::table(self::VIEW_LIST, '入金管理一覧', self::LIST_COLUMNS, [['field' => 'id', 'direction' => 'desc']]),
        ];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::UNPAID_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'assignees', 'invoice']))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(ReceiptResource::class))
            ->emptyStateIcon(Heroicon::OutlinedBanknotes)
            ->emptyStateHeading('入金がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_21 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => ReceiptLayout::label($name);
        $days = array_map(fn (ClosingDay $day): string => $day->value, ClosingDay::cases());
        $months = array_map(fn (CollectionMonth $month): string => $month->value, CollectionMonth::cases());

        return [
            FilterField::text('invoice_id', $label('invoice_id'))->query(self::filterInvoiceNumber(...)),
            FilterField::choice('customer_id', $label('customer_id'), fn (): array => Partner::query()->where('is_customer', true)->orderByDesc('id')
                ->pluck('name', 'id')->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all()),
            FilterField::user('assignees', $label('assignees'))->relationship('assignees', 'id'),
            FilterField::choice('collection_month', $label('collection_month'), array_combine($months, $months))->relationship('customer', 'collection_month'),
            FilterField::choice('master_closing_day', $label('master_closing_day'), array_combine($days, $days))->relationship('customer', 'closing_day'),
            FilterField::choice('closing_day', $label('closing_day'), array_combine($days, $days)),
            FilterField::date('billed_on', $label('billed_on')),
            FilterField::number('billed_amount', $label('billed_amount')),
            FilterField::number('settled_amount', $label('settled_amount')),
            FilterField::number('shortfall', $label('shortfall'))->query(fn (Builder $query, FilterCondition $condition) => self::filterDifference($query, $condition, self::SHORTFALL_SQL)),
            FilterField::number('overpayment', $label('overpayment'))->query(fn (Builder $query, FilterCondition $condition) => self::filterDifference($query, $condition, self::OVERPAYMENT_SQL)),
            FilterField::textarea('notes', $label('notes')),
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
        return [InlineField::textarea('notes')];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(ReceiptLayout::label($name))->placeholder('');
        $partner = fn (string $attribute): Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(
            Partner::query()->select("partners.{$attribute}")->whereColumn('partners.id', 'receipts.customer_id'),
            $direction,
        );
        $difference = fn (string $sql): Closure => fn (Builder $query, string $direction): Builder => $query->orderByRaw($sql.' '.($direction === 'desc' ? 'desc' : 'asc'));

        return match ($name) {
            'notes' => $column->searchable(),
            'billed_on' => $column->date('Y-m-d'),
            'created_at' => $column->dateTime('Y-m-d H:i'),
            'billed_amount', 'settled_amount' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => SalesFormat::yen($state)),
            'shortfall' => $column->alignEnd()->state(fn (Receipt $record): ?string => $record->shortfall() === null ? null : SalesFormat::yen($record->shortfall()))
                ->sortable(query: $difference(self::SHORTFALL_SQL)),
            'overpayment' => $column->alignEnd()->state(fn (Receipt $record): ?string => $record->overpayment() === null ? null : SalesFormat::yen($record->overpayment()))
                ->sortable(query: $difference(self::OVERPAYMENT_SQL)),
            'invoice_id' => $column
                ->state(fn (Receipt $record): ?string => $record->invoice?->number)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('invoice', fn (Builder $q): Builder => $q->whereLike('number', "%{$search}%")))
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Invoice::query()->select('invoices.number')->whereColumn('invoices.id', 'receipts.invoice_id'),
                    $direction,
                )),
            'customer_id' => $column
                ->state(fn (Receipt $record): string => $record->customer->name)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('customer', fn (Builder $q): Builder => $q->whereLike('name', "%{$search}%")))
                ->sortable(query: $partner('name')),
            'customer_code' => $column->state(fn (Receipt $record): ?string => $record->customer->code)->sortable(query: $partner('code')),
            'collection_month', 'collection_day', 'master_closing_day' => $column
                ->state(fn (Receipt $record): ?string => SalesFormat::text($record->customer->getAttribute($name === 'master_closing_day' ? 'closing_day' : $name)))
                ->sortable(query: $partner($name === 'master_closing_day' ? 'closing_day' : $name)),
            // user names open the ユーザー情報 popup, so the cell is not a link to the record
            'assignees' => $column
                ->state(fn (Receipt $record): HtmlString => UserPopup::list($record->assignees))
                ->html()
                ->disabledClick()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    User::query()->select('users.name')->join('receipt_user', 'receipt_user.user_id', '=', 'users.id')
                        ->whereColumn('receipt_user.receipt_id', 'receipts.id')->orderBy('users.name')->limit(1),
                    $direction,
                )),
            default => $column,
        };
    }

    /**
     * 請求書番号 is the number of the linked invoice.
     *
     * @param  Builder<*>  $query
     */
    private static function filterInvoiceNumber(Builder $query, FilterCondition $condition): void
    {
        $value = (string) ($condition->values[0] ?? '');

        match ($condition->operator) {
            FilterOperator::Contains => $query->whereHas('invoice', fn (Builder $q): Builder => $q->whereLike('number', '%'.$value.'%')),
            FilterOperator::NotContains => $query->whereDoesntHave('invoice', fn (Builder $q): Builder => $q->whereLike('number', '%'.$value.'%')),
            FilterOperator::Equals => $value === '' ? $query->whereNull('receipts.invoice_id') : $query->whereHas('invoice', fn (Builder $q): Builder => $q->where('number', $value)),
            FilterOperator::NotEquals => $value === '' ? $query->whereNotNull('receipts.invoice_id') : $query->whereDoesntHave('invoice', fn (Builder $q): Builder => $q->where('number', $value)),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * 入金不足 / 過入金 are blank unless positive (legacy IF(…, …, "")).
     *
     * @param  Builder<*>  $query
     */
    private static function filterDifference(Builder $query, FilterCondition $condition, string $sql): void
    {
        $raw = (string) ($condition->values[0] ?? '');
        // a number, not text: SQLite ranks any text above every number
        $value = [$raw];

        match (true) {
            $raw === '' && $condition->operator === FilterOperator::Equals => $query->whereRaw("{$sql} <= 0"),
            $raw === '' && $condition->operator === FilterOperator::NotEquals => $query->whereRaw("{$sql} > 0"),
            $condition->operator === FilterOperator::Equals => $query->whereRaw("{$sql} > 0 and {$sql} = cast(? as numeric)", $value),
            $condition->operator === FilterOperator::NotEquals => $query->whereRaw("({$sql} <= 0 or {$sql} <> cast(? as numeric))", $value),
            $condition->operator === FilterOperator::LessOrEqual => $query->whereRaw("{$sql} > 0 and {$sql} <= cast(? as numeric)", $value),
            $condition->operator === FilterOperator::GreaterOrEqual => $query->whereRaw("{$sql} > 0 and {$sql} >= cast(? as numeric)", $value),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
