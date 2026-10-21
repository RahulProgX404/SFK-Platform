<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Documents;

use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Tax\TaxRate;
use Modules\Core\Filament\Records\RecordTable;
use Modules\Core\Filament\Support\UserPopup;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Domain\Documents\TaxBreakdown;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Filament\SalesFormat;
use Modules\Sales\Models\DeliveryNoteLine;
use Modules\Sales\Models\InvoiceLine;

/**
 * The inputs and entries 10. 納品書管理(売上) and 11. 請求書管理(売上) share: 得意先名 (得意先 only, newest first), 担当者, the
 * customer's 請求書発行区分 / 納品書発行区分 always from the master (legacy JS copied them after save and locked them), the
 * table of rows (RecordTable, legacy column widths) and the subtotals / taxes per rate on the document, rounded by the
 * customer's 端数区分 (BR-TX-04) — live in the form, from the saved rows on the record page.
 */
final class DocumentParts
{
    /** Per-rate entries: name => [TaxRate, subtotal (true) or tax]. */
    private const PER_RATE = [
        'standard_subtotal' => [TaxRate::Standard, true], 'reduced_subtotal' => [TaxRate::Reduced, true],
        'exempt_subtotal' => [TaxRate::NonTaxable, true], 'out_of_scope_subtotal' => [TaxRate::OutOfScope, true],
        'standard_tax' => [TaxRate::Standard, false], 'reduced_tax' => [TaxRate::Reduced, false],
        'exempt_tax' => [TaxRate::NonTaxable, false], 'out_of_scope_tax' => [TaxRate::OutOfScope, false],
    ];

    /**
     * Form inputs shared by both documents, else null.
     *
     * @param  array<string, string>  $labels
     */
    public static function input(string $name, array $labels, bool $invoice): ?Component
    {
        $label = $labels[$name] ?? $name;
        $customer = fn (Get $get): ?Partner => is_numeric($get('customer_id')) ? Partner::query()->find((int) $get('customer_id')) : null;

        return match (true) {
            $name === 'send_status' => Radio::make($name)->label($label)->options(SaleChoices::options(SaleChoices::SEND_STATUSES))->default('未')->required()->inline(),
            $name === 'registration_number' => TextInput::make($name)->label($label)->maxLength(32)->default(SaleChoices::REGISTRATION_NUMBER),
            $name === 'number' => TextInput::make($name)->label($label)->disabled()->dehydrated(false),
            $name === 'customer_id' => Select::make($name)
                ->label($label)
                ->relationship('customer', 'name', fn (Builder $query): Builder => $query->where('is_customer', true)->orderByDesc('id'))
                ->searchable(['name', 'short_name', 'name_kana'])
                ->preload()
                ->placeholder('-----')
                ->required()
                ->live(),
            $name === 'assignees' => Select::make($name)->label($label)->placeholder('-----')->relationship('assignees', 'name')->multiple()->searchable()->preload(),
            $name === 'postal_code' => TextInput::make($name)->label($label)->maxLength(8)->regex('/^\d{3}-?\d{4}$/'),
            $name === 'address' => TextInput::make($name)->label($label)->maxLength(255),
            $name === 'invoice_issuance' => TextEntry::make($name)->label($label)->placeholder('')->state(fn (Get $get): ?string => SalesFormat::text($customer($get)?->getAttribute('invoice_issuance'))),
            $name === 'delivery_note_issuance' => TextEntry::make($name)->label($label)->placeholder('')->state(fn (Get $get): ?string => SalesFormat::text($customer($get)?->getAttribute('delivery_note_issuance'))),
            $name === 'subtotal' => TextEntry::make($name)->label($label)->placeholder('')
                ->state(fn (Get $get, ?Model $record): string => SalesFormat::yen(self::liveBreakdown($get, $record)->subtotal())),
            $name === 'tax_total', $name === 'tax_sum' => TextEntry::make($name)->label($label)->placeholder('')
                ->state(fn (Get $get, ?Model $record): string => SalesFormat::yen(self::liveBreakdown($get, $record)->tax())),
            isset(self::PER_RATE[$name]) => TextEntry::make($name)->label($label)->placeholder('')
                ->state(function (Get $get, ?Model $record) use ($name): string {
                    [$rate, $subtotal] = self::PER_RATE[$name];
                    $breakdown = self::liveBreakdown($get, $record);

                    return SalesFormat::yen($subtotal ? $breakdown->subtotalFor($rate) : $breakdown->taxFor($rate));
                }),
            $name === 'lines' => self::linesForm($labels, $invoice),
            $name === 'notes' => Textarea::make($name)->label($label)->rows(3)->maxLength(4000),
            $name === 'pdf_files' => FileUpload::make($name)->label($label)->multiple()->disk('local')->directory($invoice ? 'sales/invoices' : 'sales/delivery-notes')
                ->visibility('private')->acceptedFileTypes(['application/pdf'])->maxSize(20480),
            $name === 'customer_code' => TextEntry::make($name)->label($label)->placeholder('')->state(fn (Get $get): ?string => $customer($get)?->code),
            $name === 'customer_rounding' => TextEntry::make($name)->label($label)->placeholder('')->state(fn (Get $get, ?Model $record): string => self::rounding($get, $record)->value),
            $name === 'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    /**
     * Record-page entries shared by both documents, else null.
     *
     * @param  array<string, string>  $labels
     */
    public static function entry(string $name, array $labels, bool $invoice): ?Component
    {
        $entry = TextEntry::make($name)->label($labels[$name] ?? $name)->placeholder('—');

        return match (true) {
            $name === 'customer_id' => $entry->state(fn (Model $record): ?string => $record->getRelationValue('customer')?->name),
            $name === 'invoice_issuance', $name === 'delivery_note_issuance' => $entry->state(fn (Model $record): ?string => SalesFormat::text($record->getRelationValue('customer')?->getAttribute($name))),
            $name === 'subtotal', $name === 'tax_total' => $entry->formatStateUsing(fn (mixed $state): string => SalesFormat::yen($state)),
            $name === 'tax_sum' => $entry->state(fn (Model $record): string => SalesFormat::yen($record->getAttribute('tax_total'))),
            isset(self::PER_RATE[$name]) => $entry->state(function (Model $record) use ($name): string {
                [$rate, $subtotal] = self::PER_RATE[$name];
                $breakdown = method_exists($record, 'taxBreakdown') ? $record->taxBreakdown() : TaxBreakdown::of([], RoundingMode::Truncate);

                return SalesFormat::yen($subtotal ? $breakdown->subtotalFor($rate) : $breakdown->taxFor($rate));
            }),
            $name === 'lines' => self::linesEntry($labels, $invoice),
            $name === 'pdf_files' => $entry->state(fn (Model $record): array => array_map('basename', (array) $record->getAttribute('pdf_files')))->listWithLineBreaks(),
            $name === 'customer_code' => $entry->state(fn (Model $record): ?string => $record->getRelationValue('customer')?->code),
            // user names open the ユーザー情報 popup
            $name === 'assignees' => $entry->state(fn (Model $record): HtmlString => UserPopup::list($record->getRelationValue('assignees')))->html(),
            $name === 'creator', $name === 'updater' => $entry->state(fn (Model $record): HtmlString => UserPopup::html($record->getRelationValue($name) instanceof User ? $record->getRelationValue($name) : null))->html(),
            $name === 'created_at', $name === 'updated_at' => $entry->dateTime('Y-m-d H:i'),
            in_array($name, ['sold_on', 'billing_due_on', 'invoiced_on'], true) => $entry->date('Y-m-d'),
            default => null,
        };
    }

    /** @return array<string, int> row column => width (legacy template) */
    private static function lineColumns(bool $invoice): array
    {
        return $invoice
            ? ['line_date' => 140, 'slip_number' => 149, 'line_kind' => 98, 'is_fsc' => 107, 'product_name' => 368, 'quantity' => 119, 'unit' => 93,
                'unit_price' => 165, 'tax_rate' => 116, 'amount' => 188, 'sales_number' => 149, 'order_number' => 149, 'month_day' => 125]
            : ['line_date' => 140, 'line_kind' => 117, 'sales_number' => 150, 'is_fsc' => 107, 'product_name' => 368, 'summary' => 193, 'quantity' => 119,
                'unit' => 93, 'unit_price' => 165, 'tax_rate' => 116, 'amount' => 188, 'order_number' => 193, 'month_day' => 114];
    }

    /** @param  array<string, string>  $labels */
    private static function linesForm(array $labels, bool $invoice): Repeater
    {
        $columns = self::lineColumns($invoice);
        $rates = array_map(fn (TaxRate $rate): string => $rate->value, TaxRate::cases());
        $inputs = [
            'line_date' => DatePicker::make('line_date')->hiddenLabel()->native(),
            'slip_number' => TextInput::make('slip_number')->hiddenLabel()->maxLength(64),
            'line_kind' => TextEntry::make('line_kind')->hiddenLabel()->placeholder('')
                ->state(fn (Get $get): string => $invoice ? (filled($get('slip_number')) ? '売上消税' : '') : DeliveryNoteLine::KIND),
            'sales_number' => TextInput::make('sales_number')->hiddenLabel()->maxLength(64),
            'is_fsc' => CheckboxList::make('is_fsc')->hiddenLabel()->options([SaleChoices::FSC_CODE => SaleChoices::FSC_CODE])
                ->formatStateUsing(fn (mixed $state): array => $state === true || $state === 1 || $state === '1' ? [SaleChoices::FSC_CODE] : (is_array($state) ? $state : []))
                ->dehydrateStateUsing(fn (mixed $state): bool => in_array(SaleChoices::FSC_CODE, (array) $state, true)),
            'product_name' => TextInput::make('product_name')->hiddenLabel()->maxLength(255),
            'summary' => TextInput::make('summary')->hiddenLabel()->maxLength(255),
            'quantity' => TextInput::make('quantity')->hiddenLabel()->numeric()->step('any')->rule('decimal:0,4')->minValue(-999999999999)->maxValue(999999999999),
            'unit' => Select::make('unit')->hiddenLabel()->native()->placeholder('-----')->options(SaleChoices::options(SaleChoices::UNITS)),
            'unit_price' => TextInput::make('unit_price')->hiddenLabel()->numeric()->step('any')->rule('decimal:0,4')->minValue(-9999999999)->maxValue(9999999999),
            'tax_rate' => Select::make('tax_rate')->hiddenLabel()->native()->options(array_combine($rates, $rates))->default(TaxRate::Standard->value)->required()->live(),
            'amount' => TextInput::make('amount')->hiddenLabel()->numeric()->integer()->minValue(-99999999999)->maxValue(99999999999)->live(onBlur: true),
            'order_number' => TextInput::make('order_number')->hiddenLabel()->maxLength(64),
            'month_day' => TextEntry::make('month_day')->hiddenLabel()->placeholder('')
                ->state(fn (Get $get): string => filled($get('line_date')) ? date('n/j', (int) strtotime((string) $get('line_date'))) : ''),
        ];

        return RecordTable::form(
            Repeater::make('lines')->label($labels['lines'])->relationship('lines')->orderColumn('sort'),
            array_map(fn (string $key, int $width): array => [$labels[$key], $width], array_keys($columns), $columns),
            ['tax_rate' => TaxRate::Standard->value],
        )->schema(array_values(array_intersect_key($inputs, $columns)));
    }

    /** @param  array<string, string>  $labels */
    private static function linesEntry(array $labels, bool $invoice): RepeatableEntry
    {
        $columns = self::lineColumns($invoice);
        $entries = [
            'line_date' => TextEntry::make('line_date')->date('Y-m-d'),
            'slip_number' => TextEntry::make('slip_number'),
            'line_kind' => TextEntry::make('line_kind')->state(fn (Model $record): string => $record instanceof InvoiceLine ? $record->kindLabel() : DeliveryNoteLine::KIND),
            'sales_number' => TextEntry::make('sales_number'),
            'is_fsc' => TextEntry::make('is_fsc')->state(fn (Model $record): ?string => $record->getAttribute('is_fsc') ? SaleChoices::FSC_CODE : null),
            'product_name' => TextEntry::make('product_name'),
            'summary' => TextEntry::make('summary'),
            'quantity' => TextEntry::make('quantity')->alignEnd()->formatStateUsing(fn (mixed $state): string => SalesFormat::number($state)),
            'unit' => TextEntry::make('unit'),
            'unit_price' => TextEntry::make('unit_price')->alignEnd()->formatStateUsing(fn (mixed $state): string => SalesFormat::price($state)),
            'tax_rate' => TextEntry::make('tax_rate'),
            'amount' => TextEntry::make('amount')->alignEnd()->formatStateUsing(fn (mixed $state): string => SalesFormat::yen($state)),
            'order_number' => TextEntry::make('order_number'),
            'month_day' => TextEntry::make('month_day')->state(fn (Model $record): string => method_exists($record, 'monthDay') ? $record->monthDay() : ''),
        ];

        return RecordTable::entry(
            RepeatableEntry::make('lines')->label($labels['lines'])->placeholder(''),
            array_map(fn (string $key, int $width): array => [$labels[$key], $width], array_keys($columns), $columns),
        )->schema(array_values(array_map(fn (TextEntry $entry): TextEntry => $entry->placeholder(''), array_intersect_key($entries, $columns))));
    }

    /** Totals of the rows being edited. */
    private static function liveBreakdown(Get $get, ?Model $record): TaxBreakdown
    {
        $lines = array_map(
            fn (mixed $row): array => ['tax_rate' => (string) (is_array($row) ? ($row['tax_rate'] ?? '') : ''), 'amount' => (int) (is_array($row) ? ($row['amount'] ?? 0) : 0)],
            array_values((array) $get('lines')),
        );

        return TaxBreakdown::of($lines, self::rounding($get, $record));
    }

    /** The record's 端数区分 while its customer is unchanged, else the chosen customer's. */
    private static function rounding(Get $get, ?Model $record): RoundingMode
    {
        if ($record !== null && (int) $record->getAttribute('customer_id') === (int) $get('customer_id') && method_exists($record, 'roundingMode')) {
            return $record->roundingMode();
        }

        $customer = is_numeric($get('customer_id')) ? Partner::query()->find((int) $get('customer_id')) : null;

        return RoundingMode::fromLabel($customer?->rounding?->value);
    }
}
