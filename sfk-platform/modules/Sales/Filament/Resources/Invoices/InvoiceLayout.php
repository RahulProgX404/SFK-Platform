<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Invoices;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Filament\Records\RecordSections;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Domain\Documents\DocumentFields;
use Modules\Sales\Domain\Documents\TaxBreakdown;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Filament\Resources\Documents\DocumentParts;
use Modules\Sales\Filament\SalesFormat;
use Modules\Sales\Models\Invoice;

/**
 * Form and record page of 11. 請求書管理(売上) (SYSTEM_BRAIN APP_11 fields.md "Form layout", widths from the template):
 * 送付 / 請求区分 / 登録番号 / 請求書番号, 請求日付 / 得意先名 / 担当者 / 今回締日 / 発行区分, 郵便番号 / 請求先, the statement
 * (前月御請求額, ご入金額, 繰越金額 = 前月 − 入金, 今回御買上額(税抜), 今回消費税額, 合計御買上金額, 御請求額, BR-SA-11),
 * 直近請求レコード (record page), the table 請求内容詳細, the subtotals and taxes per rate, 備考, 請求書PDF and
 * 締日売掛連携キー (請求月度). Invoices are created from 06. 売上入力 with the balances of 23-2 (BR-SA-12).
 */
final class InvoiceLayout
{
    private const MAIN = [
        [['send_status', 265], ['kind', 250], [null, 549], ['registration_number', 193], ['number', 191]],
        [['invoiced_on', 117], ['customer_id', 449], ['assignees', 344], ['closing_day', 193], ['invoice_issuance', 193], ['delivery_note_issuance', 193]],
        [['postal_code', 193], ['address', 581]],
        [['previous_amount', 193], ['received_amount', 193], ['carried_amount', 193], ['subtotal', 193], ['tax_total', 193], ['purchase_total', 193], ['billed_total', 193]],
        [['recent_invoices', 1500]],
        [['lines', 1500]],
        [['standard_subtotal', 193], ['reduced_subtotal', 193], ['exempt_subtotal', 193], ['out_of_scope_subtotal', 193]],
        [['standard_tax', 193], ['reduced_tax', 193], ['exempt_tax', 193], ['out_of_scope_tax', 193], ['tax_sum', 193]],
        [['notes', 860]],
        [['pdf_files', 207]],
    ];

    private const FOOTER = [
        [['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]],
        [['billing_month', 193]],
    ];

    private const DEVELOPER = [[['customer_code', 193], ['master_closing_day', 193], ['collection', 193], ['collection_day', 193], ['customer_rounding', 193], ['legacy_id', 193]]];

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
        return DocumentFields::INVOICE[$attribute];
    }

    private static function input(string $name): ?Component
    {
        $label = self::label($name);
        $customer = fn (Get $get): ?Partner => is_numeric($get('customer_id')) ? Partner::query()->find((int) $get('customer_id')) : null;
        $amount = fn (): TextInput => TextInput::make($name)->label($label)->numeric()->integer()->minValue(-99999999999)->maxValue(99999999999)
            ->prefix('¥')->default(0)->live(onBlur: true);
        $days = array_map(fn (ClosingDay $day): string => $day->value, ClosingDay::cases());

        return match ($name) {
            'kind' => Radio::make($name)->label($label)->options(SaleChoices::options(SaleChoices::INVOICE_KINDS))->default('通常請求')->required()->inline(),
            'invoiced_on' => DatePicker::make($name)->label($label)->default(fn (): string => now()->toDateString()),
            'closing_day' => Select::make($name)->label($label)->placeholder('-----')->options(array_combine($days, $days))->native(false),
            'previous_amount', 'received_amount' => $amount(),
            'carried_amount' => TextEntry::make($name)->label($label)->placeholder('')
                ->state(fn (Get $get): string => SalesFormat::yen((int) $get('previous_amount') - (int) $get('received_amount'))),
            'purchase_total' => TextEntry::make($name)->label($label)->placeholder('')
                ->state(fn (Get $get, ?Invoice $record): string => SalesFormat::yen(self::liveBreakdown($get, $record)->subtotal() + self::liveBreakdown($get, $record)->tax())),
            'billed_total' => TextEntry::make($name)->label($label)->placeholder('')
                ->state(fn (Get $get, ?Invoice $record): string => SalesFormat::yen((int) $get('previous_amount') - (int) $get('received_amount')
                    + self::liveBreakdown($get, $record)->subtotal() + self::liveBreakdown($get, $record)->tax())),
            'recent_invoices' => null,
            'master_closing_day' => TextEntry::make($name)->label($label)->placeholder('')->state(fn (Get $get): ?string => $customer($get)?->closing_day?->value),
            'collection' => TextEntry::make($name)->label($label)->placeholder('')->state(fn (Get $get): ?string => SalesFormat::text($customer($get)?->getAttribute('collection_month'))),
            'collection_day' => TextEntry::make($name)->label($label)->placeholder('')->state(fn (Get $get): ?string => $customer($get)?->getAttribute('collection_day')),
            'billing_month' => null,
            default => DocumentParts::input($name, DocumentFields::INVOICE, invoice: true),
        };
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');

        return match ($name) {
            'previous_amount', 'received_amount', 'carried_amount', 'billed_total' => $entry->formatStateUsing(fn (mixed $state): string => SalesFormat::yen($state)),
            'purchase_total' => $entry->state(fn (Invoice $record): string => SalesFormat::yen($record->purchaseTotal())),
            'recent_invoices' => View::make('core::filament.related-table')
                ->viewData(fn (Invoice $record): array => self::recentInvoices($record))
                ->hidden(fn (mixed $livewire): bool => (bool) ($livewire->printMode ?? false)),
            'billing_month' => $entry->state(fn (Invoice $record): ?string => filled($record->billing_month)
                ? $record->customer_id.'_'.SalesFormat::month($record->billing_month) : null),
            'master_closing_day' => $entry->state(fn (Invoice $record): ?string => $record->customer?->closing_day?->value),
            'collection' => $entry->state(fn (Invoice $record): ?string => SalesFormat::text($record->customer?->getAttribute('collection_month'))),
            'collection_day' => $entry->state(fn (Invoice $record): ?string => $record->customer?->getAttribute('collection_day')),
            default => DocumentParts::entry($name, DocumentFields::INVOICE, invoice: true) ?? $entry,
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

    private static function liveBreakdown(Get $get, ?Invoice $record): TaxBreakdown
    {
        $lines = array_map(
            fn (mixed $row): array => ['tax_rate' => (string) (is_array($row) ? ($row['tax_rate'] ?? '') : ''), 'amount' => (int) (is_array($row) ? ($row['amount'] ?? 0) : 0)],
            array_values((array) $get('lines')),
        );
        $rounding = $record !== null && (int) $record->customer_id === (int) $get('customer_id')
            ? $record->roundingMode()
            : RoundingMode::fromLabel((is_numeric($get('customer_id')) ? Partner::query()->find((int) $get('customer_id')) : null)?->rounding?->value);

        return TaxBreakdown::of($lines, $rounding);
    }

    /**
     * 直近請求レコード: the customer's other invoices, newest first (legacy related list).
     *
     * @return array{title: string, columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>}
     */
    private static function recentInvoices(Invoice $invoice): array
    {
        $invoices = Invoice::query()->where('customer_id', $invoice->customer_id)->whereKeyNot($invoice->id)
            ->orderByDesc('invoiced_on')->orderByDesc('id')->limit(5)->get();

        return [
            'title' => self::label('recent_invoices'),
            'columns' => ['invoiced_on' => '請求日付', 'number' => '請求書番号', 'purchase_total' => '合計御買上金額', 'billed_total' => '御請求額'],
            'numeric' => ['purchase_total', 'billed_total'],
            'rows' => $invoices->map(fn (Invoice $row): array => [
                'invoiced_on' => SalesFormat::date($row->invoiced_on),
                'number' => $row->number,
                'purchase_total' => SalesFormat::yen($row->purchaseTotal()),
                'billed_total' => SalesFormat::yen($row->billed_total),
            ])->values()->all(),
            'urls' => $invoices->map(fn (Invoice $row): ?string => InvoiceResource::canView($row) ? InvoiceResource::getUrl('view', ['record' => $row]) : null)->values()->all(),
        ];
    }
}
