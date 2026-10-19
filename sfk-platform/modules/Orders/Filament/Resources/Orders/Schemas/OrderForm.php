<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Orders\Schemas;

use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Filament\Records\RecordTable;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Domain\Orders\JobCosting;
use Modules\Orders\Domain\Orders\OrderChoices;
use Modules\Orders\Domain\Orders\OrderFields;
use Modules\Orders\Filament\Resources\Orders\OrderFormat;
use Modules\Orders\Filament\Resources\Orders\OrderLayout;
use Modules\Orders\Models\Order;

/**
 * Create / edit form of 04. 受注入力, laid out like the legacy form (OrderLayout). Like the legacy save check
 * (required_field_validation.js), 得意先(受注先) is required and 数量 must be more than 0. 注番 is numbered on save and
 * cannot be typed (BR-OR-01). The calculated fields (小計, 金額(刷版), 見積原価合計, 利益 …, the amounts of the table
 * rows) follow the inputs as they are typed, with the customer's 端数区分 (JobCosting); the model recalculates them on
 * save. Partner fields offer the legacy lookup choices: 得意先(受注先) = 得意先 / その他, every 仕入先 = 仕入先 (BR-MD-02),
 * newest first; デザイナー = employees 在籍中 (BR-MD-03). 入力方法 「手動」 shows the manual amount (BR-OR-04).
 */
final class OrderForm
{
    /** Number inputs the calculations read (recalculated when they lose focus). */
    private const CALCULATION_INPUTS = [
        'quantity', 'manufactured_quantity', 'sold_quantity', 'design_fee', 'plate_count', 'plate_unit_price', 'bag_unit_price',
        'bag_manual_amount', 'case_cost', 'shipping_cost', 'sales_amount', 'internal_cost', 'purchase_amount',
        'purchase_cutting_amount', 'purchase_plate_amount', 'purchase_stored_plate_amount', 'purchase_spot_color_amount', 'sold_amount',
    ];

    private const QUANTITIES = ['quantity', 'sample', 'spare', 'manufactured_quantity', 'sold_quantity', 'expected_hours', 'per_case', 'band_sheets', 'previous_order_quantity'];

    private const AMOUNTS = [
        'design_fee', 'bag_manual_amount', 'case_cost', 'shipping_cost', 'sales_amount', 'internal_cost', 'purchase_amount', 'purchase_cutting_amount',
        'purchase_plate_amount', 'purchase_stored_plate_amount', 'purchase_spot_color_amount', 'sold_amount', 'manufacturing_sales',
    ];

    private const SIZES = ['width', 'height', 'flap', 'bottom', 'unfolded_width', 'unfolded_height', 'window_width', 'window_height', 'window_from_left', 'window_from_bottom', 'legacy_size_no'];

    private const DATES = ['due_date', 'order_date', 'plate_last_used_on', 'printed_on', 'delivered_on', 'completed_on', 'previous_order_date'];

    private const FLAGS = [
        'is_fsc' => OrderChoices::FSC_CODE, 'as_per_artwork' => '原稿通り', 'per_case_specified' => '有', 'partition_case' => '有', 'band' => '有',
        'has_label' => '有', 'bag_manual' => '手動',
    ];

    private const TEXTS = ['product_name' => 255, 'envelope_other' => 255, 'size_no' => 64, 'die' => 255, 'carton_number' => 64, 'print_location' => 255,
        'delivery_location' => 255, 'delivery_service' => 255, 'remarks' => 255, 'previous_order_number' => 64];

    private const SELECTS = [
        'status' => OrderChoices::STATUSES, 'category1' => OrderChoices::CATEGORIES1, 'category2' => OrderChoices::CATEGORIES2,
        'unit' => OrderChoices::UNITS, 'plate_version' => OrderChoices::PLATE_VERSIONS, 'plate_size' => OrderChoices::PLATE_SIZES,
        'bag_schedule' => OrderChoices::BAG_SCHEDULES, 'envelope_shape' => OrderChoices::ENVELOPE_SHAPES, 'pasting' => OrderChoices::PASTINGS,
        'flap_type' => OrderChoices::FLAP_TYPES, 'die_cut' => OrderChoices::DIE_CUTS, 'flap_glue' => OrderChoices::FLAP_GLUES,
        'side_glue' => OrderChoices::SIDE_GLUES, 'window' => OrderChoices::WINDOWS,
    ];

    /** Calculated fields => key of JobCosting::totals() and how they are shown. */
    private const TOTALS = [
        'manufacturing_remaining' => 'number', 'sales_remaining' => 'number', 'paper_subtotal' => 'yen', 'plate_amount' => 'yen', 'bag_amount' => 'yen',
        'bag_subtotal' => 'yen', 'estimated_cost_total' => 'yen', 'estimated_profit' => 'yen', 'profit' => 'yen', 'profit_rate' => 'percent',
        'purchase_cost_total' => 'yen', 'actual_cost_total' => 'yen', 'actual_sales_amount' => 'yen',
    ];

    /** @var array<int, RoundingMode> 端数区分 of the customers looked up in this request */
    private static array $roundings = [];

    public static function configure(Schema $schema): Schema
    {
        $superAdmin = auth()->user() instanceof User && auth()->user()->isSuperAdmin();

        return $schema
            ->columns(1)
            ->components(OrderLayout::components(self::input(...), withFooter: false, withDeveloperFields: $superAdmin));
    }

    /** Rounding of the amounts: the order's 端数区分 while its customer is kept, else the chosen customer's (BR-TX-01). */
    public static function rounding(mixed $customerId, mixed $livewire): RoundingMode
    {
        $record = $livewire instanceof EditRecord ? $livewire->getRecord() : null;
        if ($record instanceof Order && filled($customerId) && (int) $record->customer_id === (int) $customerId) {
            return $record->roundingMode();
        }
        if (! is_numeric($customerId)) {
            return RoundingMode::Truncate;
        }

        return self::$roundings[(int) $customerId] ??= Partner::query()->find((int) $customerId)->rounding ?? RoundingMode::Truncate;
    }

    /**
     * JobCosting::totals() of the form as filled in.
     *
     * @return array<string, int|string>
     */
    public static function totals(Get $get, mixed $livewire): array
    {
        $rows = fn (string $table): array => array_values(array_filter((array) $get($table), 'is_array'));

        return JobCosting::totals(
            [...self::orderState($get), 'bag_manual_amount' => self::isFlagged($get('bag_manual'), '手動') ? $get('bag_manual_amount') : null],
            array_map(fn (array $row): array => [
                ...$row,
                'manual_amount' => self::isFlagged($row['manual'] ?? null, '手動') ? ($row['manual_amount'] ?? null) : null,
                'cutting_manual_amount' => self::isFlagged($row['cutting_manual'] ?? null, '手動') ? ($row['cutting_manual_amount'] ?? null) : null,
            ], $rows('papers')),
            array_map(fn (array $row): array => [...$row, 'manual_amount' => self::isFlagged($row['manual'] ?? null, '手動') ? ($row['manual_amount'] ?? null) : null], $rows('printings')),
            array_map(fn (array $row): array => [...$row, 'manual_amount' => self::isFlagged($row['manual'] ?? null, '手動') ? ($row['manual_amount'] ?? null) : null], $rows('finishings')),
            self::rounding($get('customer_id'), $livewire),
        );
    }

    private static function input(string $name): ?Component
    {
        // record page only
        if (in_array($name, ['customer_code', 'past_orders', 'daily_reports', 'purchase_history', 'id', 'creator', 'created_at', 'updater', 'updated_at'], true)) {
            return null;
        }
        if ($name === 'papers') {
            return self::papers();
        }
        if ($name === 'printings' || $name === 'finishings') {
            return self::processes($name, printing: $name === 'printings');
        }

        $label = OrderLayout::label($name);

        return match (true) {
            $name === 'order_number' => TextInput::make($name)->label($label)->disabled()->dehydrated(false),
            $name === 'customer_id' => self::partner($name, 'customer', customers: true)
                ->required()
                ->validationMessages(['required' => '得意先（受注先）は必須項目です。']),
            $name === 'closing_day' => TextEntry::make($name)->label($label)->placeholder('')
                ->state(fn (Get $get, mixed $livewire): ?string => self::closingDay($get('customer_id'), $livewire)),
            $name === 'assignees' => Select::make($name)->label($label)->placeholder('-----')->relationship('assignees', 'name')->multiple()->searchable()->preload(),
            $name === 'quantity' => self::quantity($name, $label)
                ->required()
                ->rule('gt:0')
                ->validationMessages([
                    'required' => '数量は必須項目です。0より大きい数値を入力してください。',
                    'numeric' => '数量には数値を入力してください。',
                    'gt' => '数量は0より大きい値を入力してください。',
                ]),
            $name === 'order_date' => DatePicker::make($name)->label($label)->default(fn (): string => now()->toDateString()),
            in_array($name, self::DATES, true) => DatePicker::make($name)->label($label),
            $name === 'due_time' => TimePicker::make($name)->label($label)->seconds(false),
            $name === 'unit' => self::select($name, $label, OrderChoices::UNITS)->default(OrderChoices::DEFAULT_UNIT),
            isset(self::SELECTS[$name]) => self::select($name, $label, self::SELECTS[$name]),
            isset(self::FLAGS[$name]) => self::flag($name, $label, self::FLAGS[$name]),
            isset(self::TEXTS[$name]) => TextInput::make($name)->label($label)->maxLength(self::TEXTS[$name]),
            in_array($name, self::QUANTITIES, true) => self::quantity($name, $label),
            in_array($name, self::AMOUNTS, true) => self::amount($name, $label),
            in_array($name, self::SIZES, true) => TextInput::make($name)->label($label)->numeric()->step('any')->rule('decimal:0,2')
                ->minValue(0)->maxValue(99999999)->suffix(OrderFormat::UNITS[$name] ?? null),
            isset(self::TOTALS[$name]) => self::total($name, $label),
            $name === 'purchase_total' => self::total('purchase_cost_total', $label, $name),
            $name === 'plate_count' => self::quantity($name, $label),
            in_array($name, ['plate_unit_price', 'bag_unit_price'], true) => self::price($name, $label),
            $name === 'plate_storage' => Radio::make($name)->label($label)->options(OrderChoices::options(OrderChoices::PLATE_STORAGES))
                ->default(OrderChoices::DEFAULT_PLATE_STORAGE)->required()->inline(),
            in_array($name, ['data_supply', 'film_supply'], true) => Radio::make($name)->label($label)->options(OrderChoices::options(OrderChoices::SUPPLIES))->inline(),
            $name === 'plate_supplier_id' => self::partner($name, 'plateSupplier'),
            $name === 'manufacturer_id' => self::partner($name, 'manufacturer'),
            $name === 'data_supplier_id' => self::partner($name, 'dataSupplier'),
            $name === 'film_supplier_id' => self::partner($name, 'filmSupplier'),
            $name === 'designer_id' => Select::make($name)->label($label)->placeholder('-----')->searchable()->preload()
                ->relationship('designer', 'name', fn (Builder $query, mixed $state): Builder => $query
                    ->where(fn (Builder $q) => $q->where('employment_status', EmploymentStatus::Active->value)->orWhere('id', is_numeric($state) ? (int) $state : 0))
                    ->orderByDesc('id')),
            $name === 'machine_id' => Select::make($name)->label($label)->placeholder('-----')->relationship('machine', 'name')->searchable()->preload(),
            $name === 'customer_rounding' => TextEntry::make($name)->label($label)->placeholder('')
                ->state(fn (Get $get, mixed $livewire): string => self::rounding($get('customer_id'), $livewire)->value),
            in_array($name, ['plate_notes', 'memo', 'worker_message', 'notes'], true) => Textarea::make($name)->label($label)->rows(4)->maxLength(4000),
            str_starts_with($name, 'work_sheet_') => FileUpload::make($name)
                ->label($label)
                ->multiple()
                ->disk('local')
                ->directory('orders/work-sheets')
                ->visibility('private')
                ->downloadable()
                ->maxSize(20480),
            $name === 'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => throw new LogicException("No form input for [{$name}]."),
        };
    }

    /** 用紙情報: per sheet or per kg (BR-OR-02), cutting (BR-OR-03), manual amounts (BR-OR-04). */
    private static function papers(): Repeater
    {
        $labels = OrderFields::PAPER_LABELS;
        $amount = fn (string $key): TextEntry => TextEntry::make($key)->hiddenLabel()->alignEnd()
            ->state(fn (Get $get, mixed $livewire): string => OrderFormat::yen(JobCosting::paperRow(self::rowState($get, ['manual' => 'manual_amount', 'cutting_manual' => 'cutting_manual_amount']), self::rounding($get('../../customer_id'), $livewire))[$key]));

        $columns = [
            'supplier_id' => 300, 'supplier_partner_id' => 80, 'supplier_short_name' => 140, 'paper_name' => 260, 'size' => 90, 'weight_kg' => 100, 'grain' => 90,
            'sheets' => 110, 'pricing' => 90, 'unit_price' => 120, 'manual' => 90, 'paper_subtotal' => 130, 'manual_amount' => 140, 'paper_amount' => 130,
            'cut' => 110, 'bundles' => 90, 'cutting_unit_price' => 120, 'cutting_manual' => 90, 'cutting_subtotal' => 130, 'cutting_manual_amount' => 140,
            'cutting_amount' => 130,
        ];

        return RecordTable::form(
            Repeater::make('papers')->label(OrderFields::label('papers'))->relationship('papers')->orderColumn('sort'),
            array_map(fn (string $key, int $width): array => [$labels[$key], $width], array_keys($columns), $columns),
        )->schema([
            self::rowSupplier(),
            ...self::supplierInfo(),
            TextInput::make('paper_name')->hiddenLabel()->maxLength(255),
            TextInput::make('size')->hiddenLabel()->maxLength(64),
            self::rowNumber('weight_kg'),
            self::rowSelect('grain', OrderChoices::GRAINS),
            self::rowNumber('sheets'),
            self::rowSelect('pricing', OrderChoices::PRICINGS)->live(),
            self::rowNumber('unit_price'),
            self::flag('manual', $labels['manual'], '手動')->hiddenLabel(),
            $amount('paper_subtotal'),
            self::rowManualAmount('manual_amount', 'manual'),
            $amount('paper_amount'),
            TextInput::make('cut')->hiddenLabel()->maxLength(64),
            self::rowNumber('bundles'),
            self::rowNumber('cutting_unit_price'),
            self::flag('cutting_manual', $labels['cutting_manual'], '手動')->hiddenLabel(),
            $amount('cutting_subtotal'),
            self::rowManualAmount('cutting_manual_amount', 'cutting_manual'),
            $amount('cutting_amount'),
        ]);
    }

    /**
     * 印刷 (通し数 × 色数 × 単価) or トムソン・NO・シルク・箔 (単価 × 通し数), BR-OR-03. Both tables fit the record width
     * (the legacy 得意先ID copy of the supplier is left out), so ⊕ / ⊖ stay in view.
     */
    private static function processes(string $table, bool $printing): Repeater
    {
        $labels = OrderFields::PROCESS_LABELS;
        $amount = fn (string $key): TextEntry => TextEntry::make($key)->hiddenLabel()->alignEnd()
            ->state(function (Get $get, mixed $livewire) use ($key, $printing): string {
                $row = self::rowState($get, ['manual' => 'manual_amount']);
                $mode = self::rounding($get('../../customer_id'), $livewire);

                return OrderFormat::yen(($printing ? JobCosting::printingRow($row, $mode) : JobCosting::finishingRow($row, $mode))[$key]);
            });

        $columns = [
            'supplier_id' => 240, 'supplier_short_name' => 100, 'notes' => 160, 'imposition' => 70, 'passes' => 90, 'spare_passes' => 80, 'colors' => 70,
            'ink_colors' => 100, 'unit_price' => 100, 'manual' => 80, 'subtotal' => 110, 'manual_amount' => 120, 'amount' => 110,
        ];

        return RecordTable::form(
            Repeater::make($table)->label(OrderFields::label($table))->relationship($table)->orderColumn('sort'),
            array_map(fn (string $key, int $width): array => [$labels[$key], $width], array_keys($columns), $columns),
        )->schema([
            self::rowSupplier(),
            self::supplierInfo()[1],
            TextInput::make('notes')->hiddenLabel()->maxLength(255),
            self::rowNumber('imposition'),
            self::rowNumber('passes'),
            self::rowNumber('spare_passes'),
            self::rowNumber('colors'),
            TextInput::make('ink_colors')->hiddenLabel()->maxLength(64),
            self::rowNumber('unit_price'),
            self::flag('manual', $labels['manual'], '手動')->hiddenLabel(),
            $amount('subtotal'),
            self::rowManualAmount('manual_amount', 'manual'),
            $amount('amount'),
        ]);
    }

    /** 仕入先 of a table row: a native list (not cut off by the table), suppliers newest first (BR-MD-02). */
    private static function rowSupplier(): Select
    {
        return Select::make('supplier_id')
            ->hiddenLabel()
            ->native()
            ->placeholder('-----')
            ->relationship('supplier', 'name', fn (Builder $query): Builder => $query->where('is_supplier', true)->orderByDesc('id'))
            ->preload()
            ->live();
    }

    /** @param  list<string>  $choices */
    private static function rowSelect(string $name, array $choices): Select
    {
        return Select::make($name)->hiddenLabel()->native()->placeholder('-----')->options(OrderChoices::options($choices));
    }

    /** @return list<TextEntry> 得意先ID and 略名 of the chosen supplier (legacy lookup copies) */
    private static function supplierInfo(): array
    {
        $supplier = fn (Get $get): ?Partner => is_numeric($get('supplier_id')) ? Partner::query()->find((int) $get('supplier_id')) : null;

        return [
            TextEntry::make('supplier_partner_id')->hiddenLabel()->placeholder('')->state(fn (Get $get): ?string => ($partner = $supplier($get)) !== null ? (string) $partner->id : null),
            TextEntry::make('supplier_short_name')->hiddenLabel()->placeholder('')->state(fn (Get $get): ?string => $supplier($get)?->short_name),
        ];
    }

    private static function rowNumber(string $name): TextInput
    {
        return TextInput::make($name)->hiddenLabel()->numeric()->step('any')->rule('decimal:0,4')->minValue(0)->maxValue(999999999999)
            ->live(onBlur: true);
    }

    private static function rowManualAmount(string $name, string $flag): TextInput
    {
        return TextInput::make($name)->hiddenLabel()->numeric()->integer()->minValue(0)->maxValue(99999999999)->prefix('¥')
            ->visible(fn (Get $get): bool => self::isFlagged($get($flag), '手動'))
            ->live(onBlur: true);
    }

    /**
     * A row's values for JobCosting, manual amounts only while 「手動」 is checked.
     *
     * @param  array<string, string>  $manual  flag => manual amount field
     * @return array<string, mixed>
     */
    private static function rowState(Get $get, array $manual): array
    {
        $row = [];
        foreach (['pricing', 'sheets', 'weight_kg', 'unit_price', 'bundles', 'cutting_unit_price', 'passes', 'colors'] as $field) {
            $row[$field] = $get($field);
        }
        foreach ($manual as $flag => $amount) {
            $row[$amount] = self::isFlagged($get($flag), '手動') ? $get($amount) : null;
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private static function orderState(Get $get): array
    {
        $state = [];
        foreach ([...self::CALCULATION_INPUTS, 'bag_manual'] as $field) {
            $state[$field] = $get($field);
        }

        return $state;
    }

    private static function total(string $key, string $label, ?string $name = null): TextEntry
    {
        $format = self::TOTALS[$key];

        return TextEntry::make($name ?? $key)
            ->label($label)
            ->placeholder('')
            ->state(function (Get $get, mixed $livewire) use ($key, $format): string {
                $value = self::totals($get, $livewire)[$key];

                return match ($format) {
                    'yen' => OrderFormat::yen($value),
                    'percent' => OrderFormat::percent($value),
                    default => OrderFormat::number($value),
                };
            });
    }

    private static function closingDay(mixed $customerId, mixed $livewire): ?string
    {
        $record = $livewire instanceof EditRecord ? $livewire->getRecord() : null;
        if ($record instanceof Order && filled($customerId) && (int) $record->customer_id === (int) $customerId) {
            return $record->closing_day;
        }

        return is_numeric($customerId) ? Partner::query()->find((int) $customerId)?->closing_day?->value : null;
    }

    private static function partner(string $name, string $relationship, bool $customers = false): Select
    {
        return Select::make($name)
            ->label(OrderLayout::label($name))
            ->relationship($relationship, 'name', fn (Builder $query): Builder => ($customers
                ? $query->where(fn (Builder $q) => $q->where('is_customer', true)->orWhere('is_other', true))
                : $query->where('is_supplier', true))->orderByDesc('id'))
            ->searchable(['name', 'short_name', 'name_kana'])
            ->preload()
            ->placeholder('-----')
            ->live();
    }

    private static function quantity(string $name, string $label): TextInput
    {
        $input = TextInput::make($name)->label($label)->numeric()->step('any')->rule('decimal:0,4')->minValue(0)->maxValue(999999999999)
            ->suffix(OrderFormat::UNITS[$name] ?? null);

        return in_array($name, self::CALCULATION_INPUTS, true) ? $input->live(onBlur: true) : $input;
    }

    private static function amount(string $name, string $label): TextInput
    {
        $input = TextInput::make($name)->label($label)->numeric()->integer()->minValue(0)->maxValue(99999999999)->prefix('¥');

        if ($name === 'bag_manual_amount') {
            $input->visible(fn (Get $get): bool => self::isFlagged($get('bag_manual'), '手動'));
        }

        return in_array($name, self::CALCULATION_INPUTS, true) ? $input->live(onBlur: true) : $input;
    }

    private static function price(string $name, string $label): TextInput
    {
        return TextInput::make($name)->label($label)->numeric()->step('any')->rule('decimal:0,4')->minValue(0)->maxValue(9999999999)->prefix('¥')
            ->live(onBlur: true);
    }

    /** @param  list<string>  $choices */
    private static function select(string $name, string $label, array $choices): Select
    {
        return Select::make($name)->label($label)->placeholder('-----')->options(OrderChoices::options($choices))->native(false);
    }

    /** A legacy check box with one option (e.g. FSC 「115」, 入力方法 「手動」), stored as a boolean. */
    private static function flag(string $name, string $label, string $option): CheckboxList
    {
        $list = CheckboxList::make($name)
            ->label($label)
            ->options([$option => $option])
            ->formatStateUsing(fn (mixed $state): array => $state === true || $state === 1 || $state === '1' ? [$option] : (is_array($state) ? $state : []))
            ->dehydrateStateUsing(fn (mixed $state): bool => self::isFlagged($state, $option));

        return in_array($name, ['bag_manual', 'manual', 'cutting_manual'], true) ? $list->live() : $list;
    }

    private static function isFlagged(mixed $state, string $option): bool
    {
        return $state === true || (is_array($state) && in_array($option, $state, true));
    }
}
