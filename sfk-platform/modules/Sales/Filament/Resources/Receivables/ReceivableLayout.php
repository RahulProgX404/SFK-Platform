<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Modules\Core\Domain\Billing\BillingCycle;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Sales\Domain\Documents\DocumentFields;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Filament\Resources\Receipts\ReceiptResource;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Filament\SalesFormat;
use Modules\Sales\Models\ReceiptLine;
use Modules\Sales\Models\ReceivableMonth;
use Modules\Sales\Models\Sale;

/**
 * Form and record page of 23. 売掛管理 and 23-2. 売掛管理(締日別) (SYSTEM_BRAIN APP_23 / APP_23-2 fields.md "Form layout",
 * widths from the templates): 年月 / 得意先, the roll-forward (前月売掛残 − 当月入金額 = 差引残高; + 売上額 + 消費税額 +
 * 消費税端数調整 = 当月売掛残, BR-AR-03), the customer's billing terms from the master, the sales and taxes per rate (the
 * legacy 「改修中」 group, now the tax source), the customer's latest months (関連レコード一覧 / 過去売掛), 売上情報詳細 and
 * 入金履歴 (the sales and 入金明細 rows the month is made of). Every figure is recalculated from the sales and receipts
 * (ReceivableLedger); the form edits 消費税端数調整 only.
 */
final class ReceivableLayout
{
    private const HEAD = [['year_month', 193], ['customer_code', 193], ['customer_id', 386]];

    private const BALANCE = [['previous_balance', 193], ['received', 193], ['net_balance', 193]];

    private const SALES = [['sales_amount', 193], ['tax_amount', 193], ['tax_adjustment', 193], ['month_total', 193], ['closing_balance', 193]];

    private const TERMS = [['billing_closing_day', 193], ['invoice_issuance', 193], ['delivery_note_issuance', 193], ['collection_month', 193], ['collection_day', 193]];

    private const PER_RATE = [
        [['sales_standard', 193], ['sales_reduced', 193], ['sales_exempt', 193], ['sales_out_of_scope', 193]],
        [['tax_standard', 193], ['tax_reduced', 193], ['tax_exempt', 193], ['tax_out_of_scope', 193]],
    ];

    private const RELATED = [[['other_months', 1219]], [['month_sales', 1219]], [['month_receipts', 1219]]];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [[['carries_forward', 193]], [['month_start', 117]]];

    /** Figures shown as amounts. */
    private const AMOUNTS = ['previous_balance', 'received', 'net_balance', 'sales_amount', 'tax_amount', 'tax_adjustment', 'month_total', 'closing_balance',
        'sales_standard', 'sales_reduced', 'sales_exempt', 'sales_out_of_scope', 'tax_standard', 'tax_reduced'];

    public static function form(Schema $schema, ReceivableBasis $basis): Schema
    {
        return $schema->columns(1)->components(self::components(
            fn (string $name): ?Component => match ($name) {
                'tax_adjustment' => TextInput::make($name)->label(self::label($name, $basis))->numeric()->integer()->minValue(-99999999999)->maxValue(99999999999)
                    ->prefix('¥')->required(),
                'other_months', 'month_sales', 'month_receipts' => null,
                default => self::entry($name, $basis),
            },
            $basis,
            withFooter: false,
        ));
    }

    public static function infolist(Schema $schema, ReceivableBasis $basis): Schema
    {
        return $schema->columns(1)->components(self::components(fn (string $name): Component => self::entry($name, $basis), $basis, withFooter: true));
    }

    public static function label(string $attribute, ReceivableBasis $basis = ReceivableBasis::Calendar): string
    {
        return $basis === ReceivableBasis::Cycle && $attribute === 'other_months' ? '過去売掛' : DocumentFields::RECEIVABLE[$attribute];
    }

    private static function entry(string $name, ReceivableBasis $basis): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name, $basis))->placeholder('—');

        return match (true) {
            in_array($name, self::AMOUNTS, true) => $entry->formatStateUsing(fn (mixed $state): string => SalesFormat::yen($state)),
            $name === 'tax_exempt', $name === 'tax_out_of_scope' => $entry->state(SalesFormat::yen(0)),
            $name === 'year_month' => $entry->state(fn (ReceivableMonth $record): string => $record->monthLabel()),
            $name === 'customer_code' => $entry->state(fn (ReceivableMonth $record): ?string => $record->customer->code),
            $name === 'customer_id' => $entry->state(fn (ReceivableMonth $record): string => $record->customer->name),
            $name === 'billing_closing_day' => $entry->state(fn (ReceivableMonth $record): ?string => SalesFormat::text($record->customer->closing_day)),
            in_array($name, ['invoice_issuance', 'delivery_note_issuance', 'collection_month', 'collection_day'], true) => $entry
                ->state(fn (ReceivableMonth $record): ?string => SalesFormat::text($record->customer->getAttribute($name))),
            $name === 'other_months' => View::make('core::filament.related-table')->viewData(fn (ReceivableMonth $record): array => self::otherMonths($record, $basis)),
            $name === 'month_sales' => View::make('core::filament.related-table')->viewData(fn (ReceivableMonth $record): array => self::monthSales($record)),
            $name === 'month_receipts' => View::make('core::filament.related-table')->viewData(fn (ReceivableMonth $record): array => self::monthReceipts($record)),
            $name === 'carries_forward' => $entry->state(fn (ReceivableMonth $record): string => $record->carriesForward()),
            $name === 'month_start' => $entry->date('Y-m-d'),
            $name === 'creator', $name === 'updater' => $entry->state(fn (ReceivableMonth $record): HtmlString => UserPopup::html(
                $record->getRelationValue($name) instanceof User ? $record->getRelationValue($name) : null,
            ))->html(),
            $name === 'created_at', $name === 'updated_at' => $entry->dateTime('Y-m-d H:i'),
            default => $entry,
        };
    }

    /**
     * @param  callable(string): ?Component  $make
     * @return list<Component>
     */
    private static function components(callable $make, ReceivableBasis $basis, bool $withFooter): array
    {
        // 23-2 shows 締日 in the head row and the billing terms before the sales
        $rows = $basis === ReceivableBasis::Cycle
            ? [[...self::HEAD, ['closing_day', 193]], self::BALANCE, self::TERMS, self::SALES, ...self::PER_RATE, ...self::RELATED]
            : [self::HEAD, self::BALANCE, self::SALES, self::TERMS, ...self::PER_RATE, ...self::RELATED];

        $components = [RecordSections::section($rows, $make)];
        if ($withFooter) {
            $components[] = RecordSections::section(self::FOOTER, $make);
        }
        if (auth()->user() instanceof User && auth()->user()->isSuperAdmin()) {
            $components[] = RecordSections::section(self::DEVELOPER, $make, '開発者用フィールド', collapsed: true);
        }

        return $components;
    }

    /**
     * 関連レコード一覧 / 過去売掛: the customer's latest five months of the same ledger.
     *
     * @return array{title: string, columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>}
     */
    private static function otherMonths(ReceivableMonth $month, ReceivableBasis $basis): array
    {
        $months = ReceivableMonth::query()->ofBasis($basis)->where('customer_id', $month->customer_id)->orderByDesc('year_month')->limit(5)->get();
        $figures = ['previous_balance', 'received', 'net_balance', 'sales_amount', 'tax_amount', 'month_total', 'closing_balance'];
        $resource = $basis === ReceivableBasis::Cycle ? CycleReceivableResource::class : ReceivableResource::class;

        return [
            'title' => self::label('other_months', $basis),
            'columns' => ['year_month' => self::label('year_month'), ...array_combine($figures, array_map(fn (string $name): string => self::label($name), $figures))],
            'numeric' => $figures,
            'rows' => $months->map(fn (ReceivableMonth $row): array => [
                'year_month' => $row->monthLabel(),
                ...array_combine($figures, array_map(fn (string $name): string => SalesFormat::yen($row->getAttribute($name)), $figures)),
            ])->values()->all(),
            'urls' => $months->map(fn (ReceivableMonth $row): ?string => $resource::canView($row) ? $resource::getUrl('view', ['record' => $row]) : null)->values()->all(),
        ];
    }

    /**
     * 売上情報詳細: the customer's sales of the month (売上日 for 23, 月度 for 23-2), newest first.
     *
     * @return array{title: string, columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>}
     */
    private static function monthSales(ReceivableMonth $month): array
    {
        $yearMonth = YearMonth::parse($month->year_month);
        $sales = Sale::query()->with('order')->where('customer_id', $month->customer_id)
            ->when(
                $month->basis === ReceivableBasis::Cycle,
                fn ($query) => $query->where('billing_month', $month->year_month),
                fn ($query) => $query->whereBetween('sold_on', [$yearMonth->firstDay()->toDateString(), $yearMonth->lastDay()->toDateString()]),
            )
            ->orderByDesc('id')->limit(20)->get();

        return [
            'title' => self::label('month_sales'),
            'columns' => ['sold_on' => '売上日', 'sales_number' => '売上No', 'delivery_due_on' => '納品予定日', 'order_number' => '注番', 'product_name' => '品名',
                'quantity' => '売上数量', 'unit_price' => '売上単価', 'tax_rate' => '税率', 'tax' => '消費税', 'amount' => '売上金額', 'fsc' => 'FSC'],
            'numeric' => ['quantity', 'unit_price', 'tax', 'amount'],
            'rows' => $sales->map(fn (Sale $sale): array => [
                'sold_on' => SalesFormat::date($sale->sold_on),
                'sales_number' => (string) $sale->sales_number,
                'delivery_due_on' => SalesFormat::date($sale->delivery_due_on),
                'order_number' => (string) $sale->order?->order_number,
                'product_name' => (string) $sale->product_name,
                'quantity' => SalesFormat::number($sale->quantity),
                'unit_price' => SalesFormat::price($sale->unit_price),
                'tax_rate' => $sale->tax_rate,
                'tax' => SalesFormat::yen($sale->tax),
                'amount' => SalesFormat::yen($sale->amount),
                'fsc' => $sale->order?->is_fsc ? 'FSC' : '',
            ])->values()->all(),
            'urls' => $sales->map(fn (Sale $sale): ?string => SaleResource::canView($sale) ? SaleResource::getUrl('view', ['record' => $sale]) : null)->values()->all(),
        ];
    }

    /**
     * 入金履歴: the customer's 入金明細 rows counting in the month (入金日 for 23, its 月度 by the invoice's 今回締日 for
     * 23-2 — BR-AR-06), by 入金日.
     *
     * @return array{title: string, columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>}
     */
    private static function monthReceipts(ReceivableMonth $month): array
    {
        $yearMonth = YearMonth::parse($month->year_month);
        // a 月度 takes rows from the end of the previous month at most
        $lines = ReceiptLine::query()->with('receipt.invoice')
            ->whereHas('receipt', fn ($query) => $query->where('customer_id', $month->customer_id))
            ->whereBetween('received_on', [$yearMonth->previous()->firstDay()->toDateString(), $yearMonth->lastDay()->toDateString()])
            ->orderBy('received_on')->orderBy('id')->get()
            ->filter(fn (ReceiptLine $line): bool => $line->received_on !== null && ($month->basis === ReceivableBasis::Cycle
                ? BillingCycle::monthFor($line->received_on, ClosingDay::tryFrom((string) $line->receipt->closing_day))
                : YearMonth::fromDate($line->received_on))->equals($yearMonth))
            ->values();

        return [
            'title' => self::label('month_receipts'),
            'columns' => ['received_on' => '入金日', 'invoice' => '請求書番号', 'amount' => '入金金額', 'fee' => '手数料', 'discount' => '歩引き', 'method' => '入金方法',
                'settled' => '入金額小計'],
            'numeric' => ['amount', 'fee', 'discount', 'settled'],
            'rows' => $lines->map(fn (ReceiptLine $line): array => [
                'received_on' => SalesFormat::date($line->received_on),
                'invoice' => (string) $line->receipt->invoice?->number,
                'amount' => SalesFormat::yen($line->amount),
                'fee' => SalesFormat::yen($line->fee),
                'discount' => SalesFormat::yen($line->discount),
                'method' => (string) $line->method,
                'settled' => SalesFormat::yen($line->settled()),
            ])->all(),
            'urls' => $lines->map(fn (ReceiptLine $line): ?string => ReceiptResource::canView($line->receipt) ? ReceiptResource::getUrl('view', ['record' => $line->receipt]) : null)->all(),
        ];
    }
}
