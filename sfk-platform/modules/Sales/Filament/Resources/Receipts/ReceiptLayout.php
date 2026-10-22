<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receipts;

use App\Models\User;
use DateTimeInterface;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Modules\Core\Domain\Billing\BillingCycle;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Records\RecordTable;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Sales\Domain\Documents\DocumentFields;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Filament\Resources\Invoices\InvoiceResource;
use Modules\Sales\Filament\SalesFormat;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoiceLine;
use Modules\Sales\Models\Receipt;
use Modules\Sales\Models\ReceiptLine;

/**
 * Form and record page of 21. 売入金管理（売上） (fields of SYSTEM_BRAIN APP_21 fields.md), grouped by task on a 12-column
 * grid of the 1,500 px canvas: 請求情報 — 請求書番号 first (lookup of the invoices without a receipt; copies 得意先名, 担当者,
 * 請求日, 請求金額 and 今回締日, with 回収予定月 / 回収予定日 / 締日(マスタ) from the customer) — 入金状況 (請求金額, 入金済,
 * 入金不足, 過入金), the table 入金明細 (入金年月 and 月度 shown per row
 * — the months the row counts in for 23 / 23-2, BR-AR-06), 備考 and 請求明細 (the invoice's rows, record page). The
 * legacy helper columns of the rows (keys, 日 / 年 / 月 …) are not carried over.
 */
final class ReceiptLayout
{
    private const MAIN = [
        '請求情報',
        [['invoice_id', 375], ['customer_id', 750], ['assignees', 375]],
        [['billed_on', 250], ['closing_day', 250], ['master_closing_day', 250], ['collection_month', 375], ['collection_day', 375]],
        '入金状況',
        [['billed_amount', 375], ['settled_amount', 375], ['shortfall', 375], ['overpayment', 375]],
        '入金明細',
        [['lines', 1500]],
        'その他',
        [['notes', 1500]],
        [['invoice_lines', 1500]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 193], ['created_at', 196], ['updater', 193], ['updated_at', 196]]];

    private const DEVELOPER = [[['customer_code', 193], ['legacy_id', 193]]];

    /** 入金明細 columns => width (legacy template). */
    private const LINE_COLUMNS = ['received_on' => 133, 'line_amount' => 193, 'fee' => 193, 'discount' => 193, 'method' => 196, 'bank' => 196,
        'received_month' => 193, 'cycle_month' => 120, 'line_settled' => 193];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::components(self::input(...), withFooter: false));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::components(self::entry(...), withFooter: true));
    }

    public static function label(string $attribute): string
    {
        return DocumentFields::RECEIPT[$attribute];
    }

    private static function input(string $name): ?Component
    {
        $label = self::label($name);
        $text = fn (callable $state): TextEntry => TextEntry::make($name)->label($label)->placeholder('')->state($state);

        return match ($name) {
            'invoice_id' => Select::make($name)
                ->label($label)
                ->relationship('invoice', 'number', fn (Builder $query, ?Receipt $record): Builder => $query
                    ->where(fn (Builder $q): Builder => $q->whereDoesntHave('receipt')->when($record?->invoice_id !== null, fn (Builder $open): Builder => $open->orWhereKey($record?->invoice_id)))
                    ->orderByDesc('id'))
                ->searchable(['number'])
                ->preload()
                ->placeholder('-----')
                ->required()
                ->live()
                // legacy lookup: 担当者 is copied from the invoice
                ->afterStateUpdated(fn (mixed $state, Set $set) => $set('assignees', self::invoice($state)?->assignees()->pluck('users.id')
                    ->map(fn (mixed $id): string => (string) $id)->all() ?? [])),
            'customer_id' => $text(fn (Get $get): ?string => self::invoice($get('invoice_id'))?->customer?->name),
            'assignees' => Select::make($name)->label($label)->placeholder('-----')->relationship('assignees', 'name')->multiple()->searchable()->preload(),
            'collection_month', 'collection_day', 'master_closing_day' => $text(fn (Get $get): ?string => SalesFormat::text(self::invoice($get('invoice_id'))?->customer?->getAttribute(self::partnerColumn($name)))),
            'closing_day' => $text(fn (Get $get): ?string => self::invoice($get('invoice_id'))?->closing_day),
            'billed_on' => $text(fn (Get $get): string => SalesFormat::date(self::invoice($get('invoice_id'))?->invoiced_on)),
            'billed_amount' => $text(fn (Get $get): string => SalesFormat::yen(self::invoice($get('invoice_id'))?->purchaseTotal())),
            'settled_amount' => $text(fn (Get $get): string => SalesFormat::yen(self::liveSettled($get))),
            'shortfall' => $text(fn (Get $get): string => SalesFormat::yen(self::difference($get, shortfall: true))),
            'overpayment' => $text(fn (Get $get): string => SalesFormat::yen(self::difference($get, shortfall: false))),
            'lines' => self::linesForm(),
            'notes' => Textarea::make($name)->label($label)->rows(3)->maxLength(4000),
            'invoice_lines' => null,
            'customer_code' => $text(fn (Get $get): ?string => self::invoice($get('invoice_id'))?->customer?->code),
            'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');
        $yen = fn (mixed $state): string => SalesFormat::yen($state);

        return match ($name) {
            'invoice_id' => $entry->state(fn (Receipt $record): ?string => $record->invoice?->number)
                ->url(fn (Receipt $record): ?string => $record->invoice !== null && InvoiceResource::canView($record->invoice)
                    ? InvoiceResource::getUrl('view', ['record' => $record->invoice]) : null),
            'customer_id' => $entry->state(fn (Receipt $record): string => $record->customer->name),
            'assignees' => $entry->state(fn (Receipt $record): HtmlString => UserPopup::list($record->assignees))->html(),
            'collection_month', 'collection_day', 'master_closing_day' => $entry->state(fn (Receipt $record): ?string => SalesFormat::text($record->customer->getAttribute(self::partnerColumn($name)))),
            'billed_on' => $entry->date('Y-m-d'),
            'billed_amount', 'settled_amount' => $entry->formatStateUsing($yen),
            'shortfall' => $entry->state(fn (Receipt $record): ?string => $record->shortfall() === null ? null : SalesFormat::yen($record->shortfall())),
            'overpayment' => $entry->state(fn (Receipt $record): ?string => $record->overpayment() === null ? null : SalesFormat::yen($record->overpayment())),
            'lines' => self::linesEntry(),
            'invoice_lines' => View::make('core::filament.related-table')
                ->viewData(fn (Receipt $record): array => self::invoiceLines($record)),
            'customer_code' => $entry->state(fn (Receipt $record): ?string => $record->customer->code),
            'creator', 'updater' => $entry->state(fn (Receipt $record): HtmlString => UserPopup::html($record->getRelationValue($name) instanceof User ? $record->getRelationValue($name) : null))->html(),
            'created_at', 'updated_at' => $entry->dateTime('Y-m-d H:i'),
            default => $entry,
        };
    }

    /**
     * @param  callable(string): ?Component  $make
     * @return list<Component>
     */
    private static function components(callable $make, bool $withFooter): array
    {
        $components = [RecordSections::section(self::MAIN, $make)];
        if ($withFooter) {
            $components[] = RecordSections::section(self::FOOTER, $make);
        }
        if (auth()->user() instanceof User && auth()->user()->isSuperAdmin()) {
            $components[] = RecordSections::section(self::DEVELOPER, $make, '開発者用フィールド', collapsed: true);
        }

        return $components;
    }

    private static function linesForm(): Repeater
    {
        $money = fn (string $column): TextInput => TextInput::make($column)->hiddenLabel()->numeric()->integer()->minValue(-99999999999)->maxValue(99999999999)
            ->live(onBlur: true);
        $inputs = [
            DatePicker::make('received_on')->hiddenLabel()->native()->live(),
            $money('amount'),
            $money('fee'),
            $money('discount'),
            Select::make('method')->hiddenLabel()->native()->placeholder('-----')->options(SaleChoices::options(SaleChoices::PAYMENT_METHODS)),
            Select::make('bank')->hiddenLabel()->native()->placeholder('-----')->options(SaleChoices::options(SaleChoices::BANKS)),
            TextEntry::make('received_month')->hiddenLabel()->placeholder('')
                ->state(fn (Get $get): string => self::month($get('received_on'), null, cycle: false)),
            TextEntry::make('cycle_month')->hiddenLabel()->placeholder('')
                ->state(fn (Get $get): string => self::month($get('received_on'), self::invoice($get('../../invoice_id'))?->closing_day, cycle: true)),
            TextEntry::make('line_settled')->hiddenLabel()->placeholder('')
                ->state(fn (Get $get): string => SalesFormat::yen((int) $get('amount') + (int) $get('fee') + (int) $get('discount'))),
        ];

        return RecordTable::form(
            Repeater::make('lines')->label(self::label('lines'))->relationship('lines')->orderColumn('sort'),
            self::lineHeaders(),
        )->schema($inputs);
    }

    private static function linesEntry(): RepeatableEntry
    {
        $yen = fn (mixed $state): string => SalesFormat::yen($state);
        $entries = [
            TextEntry::make('received_on')->date('Y-m-d'),
            TextEntry::make('amount')->alignEnd()->formatStateUsing($yen),
            TextEntry::make('fee')->alignEnd()->formatStateUsing($yen),
            TextEntry::make('discount')->alignEnd()->formatStateUsing($yen),
            TextEntry::make('method'),
            TextEntry::make('bank'),
            TextEntry::make('received_month')->state(fn (ReceiptLine $record): string => self::month($record->received_on, null, cycle: false)),
            TextEntry::make('cycle_month')->state(fn (ReceiptLine $record): string => self::month($record->received_on, $record->receipt->closing_day, cycle: true)),
            TextEntry::make('line_settled')->alignEnd()->state(fn (ReceiptLine $record): string => SalesFormat::yen($record->settled())),
        ];

        return RecordTable::entry(
            RepeatableEntry::make('lines')->label(self::label('lines'))->placeholder(''),
            self::lineHeaders(),
        )->schema(array_map(fn (TextEntry $entry): TextEntry => $entry->placeholder(''), $entries));
    }

    /** @return list<array{0: string, 1: int}> */
    private static function lineHeaders(): array
    {
        return array_map(fn (string $key, int $width): array => [self::label($key), $width], array_keys(self::LINE_COLUMNS), self::LINE_COLUMNS);
    }

    /** 入金年月 (calendar month of 入金日) or 月度 (by the invoice's 今回締日), 「2026年10月」. */
    private static function month(mixed $date, ?string $closingDay, bool $cycle): string
    {
        if (blank($date)) {
            return '';
        }

        $date = $date instanceof DateTimeInterface ? $date : Carbon::parse((string) $date);

        return ($cycle ? BillingCycle::monthFor($date, ClosingDay::tryFrom((string) $closingDay)) : YearMonth::fromDate($date))->label();
    }

    private static function invoice(mixed $id): ?Invoice
    {
        return is_numeric($id) ? Invoice::query()->with('customer')->find((int) $id) : null;
    }

    private static function liveSettled(Get $get): int
    {
        return array_sum(array_map(
            fn (mixed $row): int => is_array($row) ? (int) ($row['amount'] ?? 0) + (int) ($row['fee'] ?? 0) + (int) ($row['discount'] ?? 0) : 0,
            array_values((array) $get('lines')),
        ));
    }

    /** 入金不足 / 過入金 of the form (null when there is none). */
    private static function difference(Get $get, bool $shortfall): ?int
    {
        $billed = self::invoice($get('invoice_id'))?->purchaseTotal() ?? 0;
        $difference = $shortfall ? $billed - self::liveSettled($get) : self::liveSettled($get) - $billed;

        return $difference > 0 ? $difference : null;
    }

    /**
     * 請求明細: the rows of the invoice.
     *
     * @return array{title: string, columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>}
     */
    private static function invoiceLines(Receipt $receipt): array
    {
        $lines = $receipt->invoice === null ? collect() : $receipt->invoice->lines;

        return [
            'title' => self::label('invoice_lines'),
            'columns' => ['line_date' => '日付', 'slip_number' => '伝票No', 'product_name' => '品名', 'quantity' => '数量', 'unit' => '単位',
                'unit_price' => '単価', 'tax_rate' => '税率', 'amount' => '小計', 'sales_number' => '売上No', 'order_number' => '注番'],
            'numeric' => ['quantity', 'unit_price', 'amount'],
            'rows' => $lines->map(fn (InvoiceLine $line): array => [
                'line_date' => SalesFormat::date($line->line_date),
                'slip_number' => (string) $line->slip_number,
                'product_name' => (string) $line->product_name,
                'quantity' => SalesFormat::number($line->quantity),
                'unit' => (string) $line->unit,
                'unit_price' => SalesFormat::price($line->unit_price),
                'tax_rate' => $line->tax_rate,
                'amount' => SalesFormat::yen($line->amount),
                'sales_number' => (string) $line->sales_number,
                'order_number' => (string) $line->order_number,
            ])->values()->all(),
            'urls' => $lines->map(fn (): ?string => null)->values()->all(),
        ];
    }

    /** The partner column a customer-master field shows. */
    private static function partnerColumn(string $name): string
    {
        return match ($name) {
            'master_closing_day' => 'closing_day',
            default => $name,
        };
    }
}
