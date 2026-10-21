<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Orders\Schemas;

use App\Models\User;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Filament\Records\RecordTable;
use Modules\Core\Filament\Records\RelatedLists;
use Modules\Core\Filament\Support\UserPopup;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Domain\Orders\JobCosting;
use Modules\Orders\Domain\Orders\OrderFields;
use Modules\Orders\Filament\Resources\Orders\OrderFormat;
use Modules\Orders\Filament\Resources\Orders\OrderLayout;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderPaper;

/**
 * Record page of 04. 受注入力, laid out like the legacy record (OrderLayout), incl. 作成者 / 更新者 and the tables with
 * their calculated amounts. 日報一覧 (08N) and 仕入履歴 (05) list the job's daily reports and purchases once those apps
 * exist and stay empty (no message) while there are none (D-04-4: the new daily-report app is the one to list).
 * 過去の受注履歴検索 sits at the end of 製 袋・製 本・他 加 工, where the legacy widget was meant to be (D-04-3).
 */
final class OrderInfolist
{
    private const YEN = [
        'paper_subtotal', 'plate_amount', 'design_fee', 'bag_manual_amount', 'bag_amount', 'bag_subtotal', 'case_cost', 'shipping_cost', 'sales_amount',
        'estimated_cost_total', 'estimated_profit', 'profit', 'internal_cost', 'purchase_amount', 'purchase_cutting_amount', 'purchase_plate_amount',
        'purchase_stored_plate_amount', 'purchase_spot_color_amount', 'purchase_cost_total', 'actual_cost_total', 'actual_sales_amount', 'sold_amount',
        'manufacturing_sales',
    ];

    private const NUMBERS = [
        'quantity', 'sample', 'spare', 'expected_hours', 'manufactured_quantity', 'manufacturing_remaining', 'sold_quantity', 'sales_remaining', 'plate_count',
        'width', 'height', 'flap', 'bottom', 'unfolded_width', 'unfolded_height', 'window_width', 'window_height', 'window_from_left', 'window_from_bottom',
        'per_case', 'band_sheets', 'previous_order_quantity', 'legacy_size_no',
    ];

    private const FLAGS = [
        'is_fsc' => '115', 'as_per_artwork' => '原稿通り', 'per_case_specified' => '有', 'partition_case' => '有', 'band' => '有', 'has_label' => '有',
        'bag_manual' => '手動',
    ];

    private const PARTNERS = [
        'customer_id' => 'customer', 'plate_supplier_id' => 'plateSupplier', 'manufacturer_id' => 'manufacturer', 'data_supplier_id' => 'dataSupplier',
        'film_supplier_id' => 'filmSupplier',
    ];

    private const DATES = ['due_date', 'order_date', 'plate_last_used_on', 'printed_on', 'delivered_on', 'completed_on', 'previous_order_date'];

    public static function configure(Schema $schema): Schema
    {
        $superAdmin = auth()->user() instanceof User && auth()->user()->isSuperAdmin();

        return $schema
            ->columns(1)
            ->components(OrderLayout::components(self::entry(...), withFooter: true, withDeveloperFields: $superAdmin));
    }

    private static function entry(string $name): Component
    {
        if ($name === 'past_orders') {
            return View::make('orders::filament.past-orders')->hidden(fn (mixed $livewire): bool => (bool) ($livewire->printMode ?? false));
        }
        if ($name === 'papers') {
            return self::papers();
        }
        if ($name === 'printings' || $name === 'finishings') {
            return self::processes($name, $name === 'printings');
        }

        $entry = TextEntry::make($name)->label(OrderLayout::label($name))->placeholder('—');

        return match (true) {
            in_array($name, self::YEN, true) => $entry->formatStateUsing(fn (mixed $state): string => OrderFormat::yen($state)),
            in_array($name, self::NUMBERS, true) => $entry->formatStateUsing(fn (mixed $state): string => OrderFormat::field($name, $state)),
            in_array($name, ['plate_unit_price', 'bag_unit_price'], true) => $entry->formatStateUsing(fn (mixed $state): string => OrderFormat::price($state)),
            $name === 'profit_rate' => $entry->formatStateUsing(fn (mixed $state): string => OrderFormat::percent($state)),
            $name === 'purchase_total' => $entry->state(fn (Order $record): string => OrderFormat::yen($record->purchase_cost_total)),
            isset(self::FLAGS[$name]) => $entry->state(fn (Order $record): ?string => $record->getAttribute($name) ? self::FLAGS[$name] : null),
            isset(self::PARTNERS[$name]) => $entry->state(fn (Order $record): ?string => self::partnerName($record->{self::PARTNERS[$name]})),
            in_array($name, self::DATES, true) => $entry->date('Y-m-d'),
            $name === 'due_time' => $entry->formatStateUsing(fn (mixed $state): string => OrderFormat::time($state)),
            $name === 'customer_code' => $entry->state(fn (Order $record): ?string => $record->customer?->code),
            $name === 'machine_id' => $entry->state(fn (Order $record): ?string => $record->machine?->name),
            // user names open the ユーザー情報 popup
            $name === 'assignees' => $entry->state(fn (Order $record): HtmlString => UserPopup::list($record->assignees))->html(),
            $name === 'designer_id' => $entry->state(fn (Order $record): HtmlString => UserPopup::html($record->designer))->html(),
            $name === 'creator', $name === 'updater' => $entry->state(fn (Order $record): HtmlString => UserPopup::html($record->{$name}))->html(),
            $name === 'created_at', $name === 'updated_at' => $entry->dateTime('Y-m-d H:i'),
            str_starts_with($name, 'work_sheet_') => $entry
                ->state(fn (Order $record): array => array_map('basename', (array) $record->getAttribute($name)))
                ->listWithLineBreaks(),
            // related records of other apps: empty while there are none (no message)
            $name === 'daily_reports', $name === 'purchase_history' => RelatedLists::entry('orders.'.$name, OrderLayout::label($name)),
            default => $entry,
        };
    }

    private static function papers(): RepeatableEntry
    {
        $labels = OrderFields::PAPER_LABELS;
        $amount = fn (string $key): TextEntry => TextEntry::make($key)->alignEnd()
            ->state(fn (Model $record, mixed $livewire): string => OrderFormat::yen(JobCosting::paperRow($record->attributesToArray(), self::rounding($livewire))[$key]));
        $number = fn (string $key): TextEntry => TextEntry::make($key)->alignEnd()->formatStateUsing(fn (mixed $state): string => OrderFormat::field($key, $state));
        $flag = fn (string $key): TextEntry => TextEntry::make($key)->state(fn (Model $record): ?string => $record->getAttribute($key) ? '手動' : null);

        $columns = [
            'supplier_id' => self::supplierEntry(), 'supplier_partner_id' => self::supplierEntry('id'), 'supplier_short_name' => self::supplierEntry('short_name'),
            'paper_name' => TextEntry::make('paper_name'), 'size' => TextEntry::make('size'), 'weight_kg' => $number('weight_kg'), 'grain' => TextEntry::make('grain'),
            'sheets' => $number('sheets'), 'pricing' => TextEntry::make('pricing'),
            'unit_price' => TextEntry::make('unit_price')->alignEnd()->formatStateUsing(fn (mixed $state): string => OrderFormat::price($state)),
            'manual' => $flag('manual'), 'paper_subtotal' => $amount('paper_subtotal'),
            'manual_amount' => TextEntry::make('manual_amount')->alignEnd()->formatStateUsing(fn (mixed $state): string => OrderFormat::yen($state)),
            'paper_amount' => $amount('paper_amount'), 'cut' => TextEntry::make('cut'), 'bundles' => $number('bundles'),
            'cutting_unit_price' => TextEntry::make('cutting_unit_price')->alignEnd()->formatStateUsing(fn (mixed $state): string => OrderFormat::price($state)),
            'cutting_manual' => $flag('cutting_manual'), 'cutting_subtotal' => $amount('cutting_subtotal'),
            'cutting_manual_amount' => TextEntry::make('cutting_manual_amount')->alignEnd()->formatStateUsing(fn (mixed $state): string => OrderFormat::yen($state)),
            'cutting_amount' => $amount('cutting_amount'),
            'cut_for_print' => TextEntry::make('cut_for_print')->state(fn (Model $record): ?string => $record instanceof OrderPaper ? $record->cutForPrint() : null),
        ];

        $widths = [
            'supplier_id' => 300, 'supplier_partner_id' => 80, 'supplier_short_name' => 140, 'paper_name' => 260, 'size' => 90, 'weight_kg' => 100, 'grain' => 90,
            'sheets' => 110, 'pricing' => 90, 'unit_price' => 120, 'manual' => 90, 'paper_subtotal' => 130, 'manual_amount' => 140, 'paper_amount' => 130,
            'cut' => 110, 'bundles' => 90, 'cutting_unit_price' => 120, 'cutting_manual' => 90, 'cutting_subtotal' => 130, 'cutting_manual_amount' => 140,
            'cutting_amount' => 130, 'cut_for_print' => 110,
        ];

        return RecordTable::entry(
            RepeatableEntry::make('papers')->label(OrderFields::label('papers'))->placeholder(''),
            array_map(fn (string $key): array => [$labels[$key], $widths[$key]], array_keys($columns)),
        )->schema(array_values(array_map(fn (TextEntry $entry): TextEntry => $entry->placeholder(''), $columns)));
    }

    private static function processes(string $table, bool $printing): RepeatableEntry
    {
        $labels = OrderFields::PROCESS_LABELS;
        $amount = fn (string $key): TextEntry => TextEntry::make($key)->alignEnd()
            ->state(function (Model $record, mixed $livewire) use ($key, $printing): string {
                $row = $record->attributesToArray();
                $mode = self::rounding($livewire);

                return OrderFormat::yen(($printing ? JobCosting::printingRow($row, $mode) : JobCosting::finishingRow($row, $mode))[$key]);
            });
        $number = fn (string $key): TextEntry => TextEntry::make($key)->alignEnd()->formatStateUsing(fn (mixed $state): string => OrderFormat::field($key, $state));

        $columns = [
            'supplier_id' => self::supplierEntry(), 'supplier_short_name' => self::supplierEntry('short_name'),
            'notes' => TextEntry::make('notes'), 'imposition' => $number('imposition'), 'passes' => $number('passes'), 'spare_passes' => $number('spare_passes'),
            'colors' => $number('colors'), 'ink_colors' => TextEntry::make('ink_colors'),
            'unit_price' => TextEntry::make('unit_price')->alignEnd()->formatStateUsing(fn (mixed $state): string => OrderFormat::price($state)),
            'manual' => TextEntry::make('manual')->state(fn (Model $record): ?string => $record->getAttribute('manual') ? '手動' : null),
            'subtotal' => $amount('subtotal'),
            'manual_amount' => TextEntry::make('manual_amount')->alignEnd()->formatStateUsing(fn (mixed $state): string => OrderFormat::yen($state)),
            'amount' => $amount('amount'),
        ];

        $widths = [
            'supplier_id' => 240, 'supplier_short_name' => 100, 'notes' => 160, 'imposition' => 70, 'passes' => 90, 'spare_passes' => 80, 'colors' => 70,
            'ink_colors' => 100, 'unit_price' => 100, 'manual' => 80, 'subtotal' => 110, 'manual_amount' => 120, 'amount' => 110,
        ];

        return RecordTable::entry(
            RepeatableEntry::make($table)->label(OrderFields::label($table))->placeholder(''),
            array_map(fn (string $key): array => [$labels[$key], $widths[$key]], array_keys($columns)),
        )->schema(array_values(array_map(fn (TextEntry $entry): TextEntry => $entry->placeholder(''), $columns)));
    }

    /** 仕入先 of a table row, or one of its fields (得意先ID / 略名, the legacy lookup copies). */
    private static function supplierEntry(?string $field = null): TextEntry
    {
        $name = match ($field) {
            null => 'supplier_id',
            'id' => 'supplier_partner_id',
            default => 'supplier_short_name',
        };

        return TextEntry::make($name)->state(function (Model $record) use ($field): ?string {
            $supplier = $record->getAttribute('supplier_id') !== null ? $record->getRelationValue('supplier') : null;
            if (! $supplier instanceof Partner) {
                return null;
            }

            return match ($field) {
                null => $supplier->name,
                'id' => (string) $supplier->id,
                default => $supplier->short_name,
            };
        });
    }

    /** 端数区分 of the order on the page (also for an older version shown by 変更履歴). */
    private static function rounding(mixed $livewire): RoundingMode
    {
        $order = $livewire instanceof ViewRecord ? $livewire->getRecord() : null;

        return $order instanceof Order ? $order->roundingMode() : RoundingMode::Truncate;
    }

    private static function partnerName(mixed $partner): ?string
    {
        return $partner instanceof Partner ? $partner->name : null;
    }
}
