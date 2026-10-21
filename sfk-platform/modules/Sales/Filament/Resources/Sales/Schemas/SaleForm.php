<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Sales\Schemas;

use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Money\Yen;
use Modules\Core\Domain\Tax\TaxRate;
use Modules\Core\Filament\Records\RecordTable;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Order;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Filament\Resources\Sales\SaleLayout;
use Modules\Sales\Filament\SalesFormat;
use Modules\Sales\Models\Sale;
use Modules\Sales\Models\SaleAllocation;

/**
 * Create / edit form of 06. 売上入力, laid out like the legacy form (SaleLayout), with the legacy Customine behaviour:
 *
 * - 売上先: 得意先 only, newest first (lookup filter, BR-MD-02); its 請求締日, 納品書送付先 / 請求書送付先 and addresses are
 *   shown from the master (copied on save).
 * - 請求日付 is shown and required only for 随時請求 — the customer's closing day or 請求締日変更 (page 20, BR-SA-06).
 * - 郵便番号(手動) / 納品先住所(手動) can be typed only with 「その他の送付先を入力 する」 (page 23, BR-SA-07).
 * - 売上金額 = 売上単価 × 売上数量 when either changes, in whole yen by the customer's 端数区分, still editable (page 24,
 *   BR-SA-05); 消費税 / 売上金額(税込) follow (BR-TX-05).
 * - 注番: jobs with 売上残数量 ≥ 1, newest first (lookup filter, BR-SA-01); choosing one copies 品名, 見積原価合計,
 *   見積経費 (= 見積利益) and 担当者, and shows the job's figures. 品名 can be changed by administrators only (page 23).
 * - 編集 (可 / 不可, BR-SA-16) is set by administrators only.
 */
final class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components(SaleLayout::components(self::input(...), withFooter: false, withDeveloperFields: self::actor()?->isSuperAdmin() ?? false));
    }

    public static function isAdministrator(): bool
    {
        return self::actor()?->user_type->isAdministrator() ?? false;
    }

    private static function input(string $name): ?Component
    {
        $label = SaleLayout::label($name);
        $customer = fn (Get $get): ?Partner => self::partner($get('customer_id'));
        $order = fn (Get $get): ?Order => self::order($get('order_id'));
        $entry = fn (callable $state): TextEntry => TextEntry::make($name)->label($label)->placeholder('')->state($state);

        return match ($name) {
            'kind' => Radio::make($name)->label($label)->options(SaleChoices::options(SaleChoices::KINDS))->default(SaleChoices::KIND_NORMAL)
                ->required()->inline(),
            'sold_on' => DatePicker::make($name)->label($label)->required()->default(fn (): string => now()->toDateString()),
            'sales_number' => TextInput::make($name)->label($label)->disabled()->dehydrated(false),
            'customer_id' => Select::make($name)
                ->label($label)
                ->relationship('customer', 'name', fn (Builder $query): Builder => $query->where('is_customer', true)->orderByDesc('id'))
                ->searchable(['name', 'short_name', 'name_kana'])
                ->preload()
                ->placeholder('-----')
                ->required()
                ->live(),
            'closing_day' => $entry(fn (Get $get, ?Sale $record): ?string => self::closingDay($get, $record)),
            'closing_day_override' => Select::make($name)->label($label)->placeholder('-----')
                ->options(SaleChoices::options(SaleChoices::CLOSING_DAY_OVERRIDES))->native(false)->live(),
            'billing_date' => DatePicker::make($name)->label($label)
                ->visible(fn (Get $get, ?Sale $record): bool => self::isAdHoc($get, $record))
                ->required(fn (Get $get, ?Sale $record): bool => self::isAdHoc($get, $record)),
            'delivery_due_on' => DatePicker::make($name)->label($label)->required(),
            'delivery_note_recipient' => $entry(fn (Get $get): ?string => $customer($get)?->delivery_note_recipient->value),
            'invoice_recipient' => $entry(fn (Get $get): ?string => $customer($get)?->invoice_recipient->value),
            'other_address' => CheckboxList::make($name)->label($label)->options(['する' => 'する'])
                ->formatStateUsing(fn (mixed $state): array => $state === true || $state === 1 || $state === '1' ? ['する'] : (is_array($state) ? $state : []))
                ->dehydrateStateUsing(fn (mixed $state): bool => in_array('する', (array) $state, true))
                ->live(),
            'manual_postal_code' => TextInput::make($name)->label($label)->maxLength(8)->regex('/^\d{3}-?\d{4}$/')
                ->disabled(fn (Get $get): bool => ! self::manualAddress($get)),
            'manual_address' => TextInput::make($name)->label($label)->maxLength(255)->disabled(fn (Get $get): bool => ! self::manualAddress($get)),
            'parent_postal_code' => $entry(fn (Get $get): ?string => $customer($get)?->getAttribute('parent_postal_code')),
            'parent_address' => $entry(fn (Get $get): ?string => $customer($get)?->getAttribute('parent_address')),
            'customer_postal_code' => $entry(fn (Get $get): ?string => $customer($get)?->getAttribute('postal_code')),
            'customer_address' => $entry(fn (Get $get): ?string => $customer($get)?->getAttribute('address')),
            'quantity' => TextInput::make($name)->label($label)->numeric()->step('any')->rule('decimal:0,4')->minValue(-999999999999)->maxValue(999999999999)
                ->live(onBlur: true)->afterStateUpdated(fn (Get $get, Set $set) => self::fillAmount($get, $set)),
            'unit' => Select::make($name)->label($label)->placeholder('-----')->options(SaleChoices::options(SaleChoices::UNITS))
                ->default(SaleChoices::DEFAULT_UNIT)->native(false),
            'unit_price' => TextInput::make($name)->label($label)->numeric()->step('any')->rule('decimal:0,4')->minValue(-9999999999)->maxValue(9999999999)
                ->prefix('¥')->live(onBlur: true)->afterStateUpdated(fn (Get $get, Set $set) => self::fillAmount($get, $set)),
            'amount' => TextInput::make($name)->label($label)->numeric()->integer()->required()->minValue(-99999999999)->maxValue(99999999999)
                ->prefix('¥')->live(onBlur: true),
            'tax_rate' => Select::make($name)->label($label)->options(array_combine(
                array_map(fn (TaxRate $rate): string => $rate->value, TaxRate::cases()),
                array_map(fn (TaxRate $rate): string => $rate->value, TaxRate::cases()),
            ))->default(TaxRate::Standard->value)->required()->native(false)->live(),
            'tax' => $entry(fn (Get $get, ?Sale $record): string => SalesFormat::yen(self::tax($get, $record))),
            'amount_with_tax' => $entry(fn (Get $get, ?Sale $record): string => SalesFormat::yen((int) $get('amount') + self::tax($get, $record))),
            'estimated_cost', 'estimated_expense' => TextInput::make($name)->label($label)->numeric()->integer()
                ->minValue(-99999999999)->maxValue(99999999999)->prefix('¥'),
            'summary' => TextInput::make($name)->label($label)->maxLength(255),
            'order_id' => Select::make($name)
                ->label($label)
                ->relationship('order', 'order_number', fn (Builder $query, ?Sale $record): Builder => $query
                    ->where(fn (Builder $q): Builder => $q->where('sales_remaining', '>=', 1)->when($record?->order_id !== null, fn (Builder $q): Builder => $q->orWhereKey($record?->order_id)))
                    ->orderByDesc('id'))
                ->searchable(['order_number', 'product_name'])
                ->preload()
                ->optionsLimit(100)
                ->placeholder('-----')
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::copyOrder($get, $set)),
            'order_customer' => $entry(fn (Get $get): ?string => $order($get)?->customer?->name),
            'product_name' => TextInput::make($name)->label($label)->maxLength(255)
                ->disabled(fn (Get $get): bool => ! self::isAdministrator() && filled($get('order_id')))
                ->dehydrated(),
            'assignees' => Select::make($name)->label($label)->placeholder('-----')->relationship('assignees', 'name')->multiple()->searchable()->preload(),
            'order_quantity' => $entry(fn (Get $get): string => SalesFormat::number($order($get)?->quantity)),
            'order_sold_quantity' => $entry(fn (Get $get): string => SalesFormat::number($order($get)?->getAttribute('sold_quantity'))),
            'order_remaining' => $entry(fn (Get $get): string => SalesFormat::number($order($get)?->getAttribute('sales_remaining'))),
            'order_sales_amount' => $entry(fn (Get $get): string => SalesFormat::yen($order($get)?->sales_amount)),
            'is_fsc' => $entry(fn (Get $get): string => $order($get)?->is_fsc === true ? SaleChoices::FSC_CODE : ''),
            'order_sold_amount' => $entry(fn (Get $get): string => SalesFormat::yen($order($get)?->getAttribute('sold_amount'))),
            'allocations' => self::allocations(),
            'needs' => CheckboxList::make($name)->label($label)->options(SaleChoices::options(SaleChoices::NEEDS))->columns(4),
            'handover' => CheckboxList::make($name)->label($label)->options(SaleChoices::options(SaleChoices::HANDOVERS))->columns(3),
            'delivered' => $entry(fn (?Sale $record): string => $record?->delivery_note_id !== null ? '済' : ''),
            'delivery_note_id' => $entry(fn (?Sale $record): ?string => $record?->deliveryNote?->number),
            'invoiced' => $entry(fn (?Sale $record): string => $record?->invoice_id !== null ? '済' : ''),
            'invoice_id' => $entry(fn (?Sale $record): ?string => $record?->invoice?->number),
            'locked' => Radio::make($name)->label($label)->options(['可' => '可', '不可' => '不可'])->inline()
                ->formatStateUsing(fn (mixed $state): string => $state === true || $state === 1 || $state === '1' ? '不可' : '可')
                ->dehydrateStateUsing(fn (mixed $state): bool => $state === '不可')
                ->default('可')
                ->disabled(fn (): bool => ! self::isAdministrator()),
            'postal_code_p' => $entry(fn (?Sale $record): ?string => $record?->deliveryAddress()['postal_code']),
            'address_p' => $entry(fn (?Sale $record): ?string => $record?->deliveryAddress()['address']),
            'customer_code' => $entry(fn (Get $get): ?string => $customer($get)?->code),
            'customer_rounding' => $entry(fn (Get $get, ?Sale $record): string => self::rounding($get, $record)->value),
            'effective_closing_day' => $entry(fn (Get $get, ?Sale $record): ?string => self::effectiveClosingDay($get, $record)?->value),
            'billing_month' => $entry(fn (?Sale $record): string => SalesFormat::month($record?->billing_month)),
            'sales_month' => $entry(fn (Get $get): string => filled($get('sold_on')) ? substr((string) $get('sold_on'), 0, 4).'年'.substr((string) $get('sold_on'), 5, 2).'月' : ''),
            'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    /** 按分: 担当者(按分), 割合 (%), 売上金額(按分) = 売上金額 × 割合 / 100. */
    private static function allocations(): Component
    {
        return RecordTable::form(
            Repeater::make('allocations')->label(SaleLayout::label('allocations'))->relationship('allocations')->orderColumn('sort'),
            [[SaleLayout::label('allocation_user'), 344], [SaleLayout::label('allocation_ratio'), 89], [SaleLayout::label('allocation_amount'), 193]],
        )->schema([
            Select::make('user_id')->hiddenLabel()->native()->placeholder('-----')->relationship('user', 'name')->preload(),
            TextInput::make('ratio')->hiddenLabel()->numeric()->step('any')->rule('decimal:0,4')->minValue(0)->maxValue(100)->suffix('%')->live(onBlur: true),
            TextEntry::make('allocation_amount')->hiddenLabel()->alignEnd()->placeholder('')
                ->state(fn (Get $get): string => SalesFormat::yen(SaleAllocation::share((int) $get('../../amount'), is_scalar($get('ratio')) ? (string) $get('ratio') : null))),
        ]);
    }

    /** 売上金額 = 売上単価 × 売上数量 (whole yen by the customer's 端数区分) once both are there. */
    private static function fillAmount(Get $get, Set $set): void
    {
        $quantity = $get('quantity');
        $price = $get('unit_price');
        if (! is_numeric($quantity) || ! is_numeric($price)) {
            return;
        }

        $set('amount', Yen::round(Yen::multiply((string) $quantity, (string) $price), self::rounding($get, null)));
    }

    /** Choosing a job copies 品名, 見積原価合計, 見積経費 and 担当者 (and the customer while none is chosen). */
    private static function copyOrder(Get $get, Set $set): void
    {
        $order = self::order($get('order_id'));
        if ($order === null) {
            return;
        }

        $set('product_name', $order->product_name);
        $set('estimated_cost', $order->estimated_cost_total);
        $set('estimated_expense', $order->estimated_profit);
        $set('assignees', $order->assignees()->pluck('users.id')->map(fn (mixed $id): string => (string) $id)->all());
        if (blank($get('customer_id')) && $order->customer_id !== null) {
            $set('customer_id', (string) $order->customer_id);
        }
    }

    private static function tax(Get $get, ?Sale $record): int
    {
        return Sale::taxRateOf(is_scalar($get('tax_rate')) ? (string) $get('tax_rate') : null)->taxOn((int) $get('amount'), self::rounding($get, $record));
    }

    /** The record's copy while its customer is unchanged, else the chosen customer's master value. */
    private static function closingDay(Get $get, ?Sale $record): ?string
    {
        if ($record !== null && (int) $record->customer_id === (int) $get('customer_id')) {
            return $record->closing_day;
        }

        return self::partner($get('customer_id'))?->closing_day?->value;
    }

    private static function rounding(Get $get, ?Sale $record): RoundingMode
    {
        if ($record !== null && (int) $record->customer_id === (int) $get('customer_id')) {
            return $record->roundingMode();
        }

        return RoundingMode::fromLabel(self::partner($get('customer_id'))?->rounding?->value);
    }

    private static function effectiveClosingDay(Get $get, ?Sale $record): ?ClosingDay
    {
        return ClosingDay::tryFrom((string) $get('closing_day_override')) ?? ClosingDay::tryFrom((string) self::closingDay($get, $record));
    }

    private static function isAdHoc(Get $get, ?Sale $record): bool
    {
        return self::effectiveClosingDay($get, $record) === ClosingDay::AdHoc;
    }

    private static function manualAddress(Get $get): bool
    {
        $state = $get('other_address');

        return $state === true || in_array('する', (array) $state, true);
    }

    private static function partner(mixed $id): ?Partner
    {
        if (! is_numeric($id)) {
            return null;
        }

        return Partner::query()->find((int) $id);
    }

    private static function order(mixed $id): ?Order
    {
        if (! is_numeric($id)) {
            return null;
        }

        return Order::query()->with('customer')->find((int) $id);
    }

    private static function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
