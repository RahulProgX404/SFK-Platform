<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Invoices\Tables;

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
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Filament\Resources\Invoices\InvoiceLayout;
use Modules\Sales\Filament\Resources\Invoices\InvoiceResource;
use Modules\Sales\Filament\SalesFormat;
use Modules\Sales\Models\Invoice;

/**
 * Record list of 11. 請求書管理(売上) with the legacy views (SYSTEM_BRAIN APP_11 views.md): 請求管理一覧 and one list per
 * 締日(マスタ) of the customer, newest first; （すべて） shows columns() in order. The legacy closing views also required
 * an empty 請求日付, which every issued invoice has — they list the customer's invoices by closing day. The search box
 * replaces 請求書一覧(検索特化). インライン編集 changes 送付 and 備考.
 */
final class InvoicesTable
{
    public const VIEW_LIST = 'list';

    public const LIST_COLUMNS = ['number', 'send_status', 'invoice_issuance', 'invoiced_on', 'master_closing_day', 'closing_day', 'customer_id', 'subtotal',
        'tax_sum', 'purchase_total', 'billed_total', 'pdf_files'];

    private const CLOSING_COLUMNS = ['number', 'invoiced_on', 'invoice_issuance', 'master_closing_day', 'closing_day', 'customer_id', 'subtotal', 'purchase_total',
        'billed_total', 'send_status', 'pdf_files'];

    /** Closing days with a legacy view (no 随時請求 list). */
    private const CLOSING_VIEWS = [ClosingDay::Day5, ClosingDay::Day10, ClosingDay::Day15, ClosingDay::Day20, ClosingDay::Day25, ClosingDay::EndOfMonth];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', 'send_status', 'invoiced_on', 'customer_id', 'assignees', 'number', 'registration_number', 'subtotal', 'purchase_total', 'notes',
            'pdf_files', 'customer_code', 'previous_amount', 'received_amount', 'carried_amount', 'kind', 'tax_total', 'master_closing_day', 'collection',
            'postal_code', 'address', 'billed_total', 'collection_day', 'closing_day', 'customer_rounding', 'tax_sum', 'billing_month', 'invoice_issuance',
            'delivery_note_issuance'] as $name) {
            $columns[$name] = InvoiceLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        $byId = [['field' => 'id', 'direction' => 'desc']];
        $views = [ListView::table(self::VIEW_LIST, '請求管理一覧', self::LIST_COLUMNS, $byId)];

        foreach (self::CLOSING_VIEWS as $day) {
            $views[] = ListView::table(
                'closing-'.$day->name,
                $day->value.'締日一覧',
                $day === ClosingDay::Day15 ? array_values(array_diff(self::CLOSING_COLUMNS, ['invoice_issuance'])) : self::CLOSING_COLUMNS,
                $byId,
                [['field' => 'master_closing_day', 'operator' => FilterOperator::In->value, 'values' => [$day->value]]],
            );
        }

        return $views;
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'assignees']))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(InvoiceResource::class))
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->emptyStateHeading('請求書がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_11 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => InvoiceLayout::label($name);
        $days = array_map(fn (ClosingDay $day): string => $day->value, ClosingDay::cases());

        return [
            FilterField::choice('send_status', $label('send_status'), SaleChoices::options(SaleChoices::SEND_STATUSES)),
            FilterField::choice('kind', $label('kind'), SaleChoices::options(SaleChoices::INVOICE_KINDS)),
            FilterField::text('registration_number', $label('registration_number')),
            FilterField::text('number', $label('number')),
            FilterField::date('invoiced_on', $label('invoiced_on')),
            FilterField::choice('customer_id', $label('customer_id'), fn (): array => Partner::query()->where('is_customer', true)->orderByDesc('id')
                ->pluck('name', 'id')->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all()),
            FilterField::user('assignees', $label('assignees'))->relationship('assignees', 'id'),
            FilterField::choice('closing_day', $label('closing_day'), array_combine($days, $days)),
            FilterField::choice('master_closing_day', $label('master_closing_day'), array_combine($days, $days))->relationship('customer', 'closing_day'),
            FilterField::text('postal_code', $label('postal_code')),
            FilterField::text('address', $label('address')),
            FilterField::number('previous_amount', $label('previous_amount')),
            FilterField::number('received_amount', $label('received_amount')),
            FilterField::number('carried_amount', $label('carried_amount')),
            FilterField::number('subtotal', $label('subtotal')),
            FilterField::number('tax_total', $label('tax_total')),
            FilterField::number('billed_total', $label('billed_total')),
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
        return [
            InlineField::select('send_status', SaleChoices::options(SaleChoices::SEND_STATUSES))->required(),
            InlineField::textarea('notes'),
        ];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(InvoiceLayout::label($name))->placeholder('');
        $partner = fn (string $attribute): Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(
            Partner::query()->select("partners.{$attribute}")->whereColumn('partners.id', 'invoices.customer_id'),
            $direction,
        );
        $yen = fn (mixed $state): string => SalesFormat::yen($state);

        return match ($name) {
            'number', 'address', 'notes' => $column->searchable(),
            'invoiced_on' => $column->date('Y-m-d'),
            'previous_amount', 'received_amount', 'carried_amount', 'subtotal', 'tax_total', 'billed_total' => $column->alignEnd()->formatStateUsing($yen),
            'tax_sum' => $column->alignEnd()->state(fn (Invoice $record): string => SalesFormat::yen($record->tax_total))
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('invoices.tax_total', $direction)),
            'purchase_total' => $column->alignEnd()->state(fn (Invoice $record): string => SalesFormat::yen($record->purchaseTotal()))
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw('(invoices.subtotal + invoices.tax_total) '.($direction === 'desc' ? 'desc' : 'asc'))),
            'customer_id' => $column
                ->state(fn (Invoice $record): ?string => $record->customer?->name)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('customer', fn (Builder $q): Builder => $q->whereLike('name', "%{$search}%")))
                ->sortable(query: $partner('name')),
            'customer_code' => $column->state(fn (Invoice $record): ?string => $record->customer?->code)->sortable(query: $partner('code')),
            'customer_rounding' => $column->state(fn (Invoice $record): string => $record->roundingMode()->value),
            'master_closing_day', 'collection', 'collection_day', 'invoice_issuance', 'delivery_note_issuance' => $column
                ->state(fn (Invoice $record): ?string => SalesFormat::text($record->customer?->getAttribute(self::partnerColumn($name))))
                ->sortable(query: $partner(self::partnerColumn($name))),
            'billing_month' => $column->state(fn (Invoice $record): ?string => filled($record->billing_month)
                ? $record->customer_id.'_'.SalesFormat::month($record->billing_month) : null),
            'pdf_files' => $column->state(fn (Invoice $record): ?string => implode('、', array_map('basename', (array) $record->pdf_files)) ?: null),
            // user names open the ユーザー情報 popup, so the cell is not a link to the record
            'assignees' => $column
                ->state(fn (Invoice $record): HtmlString => UserPopup::list($record->assignees))
                ->html()
                ->disabledClick()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    User::query()->select('users.name')->join('invoice_user', 'invoice_user.user_id', '=', 'users.id')
                        ->whereColumn('invoice_user.invoice_id', 'invoices.id')->orderBy('users.name')->limit(1),
                    $direction,
                )),
            default => $column,
        };
    }

    /** The partner column a customer-master column of the list shows. */
    private static function partnerColumn(string $name): string
    {
        return match ($name) {
            'master_closing_day' => 'closing_day',
            'collection' => 'collection_month',
            default => $name,
        };
    }
}
