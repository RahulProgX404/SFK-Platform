<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payables;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\FieldTip;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Purchasing\Domain\Payables\PayableCategories;
use Modules\Purchasing\Domain\PurchasingFields;
use Modules\Purchasing\Filament\Resources\Payments\PaymentResource;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseResource;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseRows;
use Modules\Purchasing\Models\PayableMonth;
use Modules\Purchasing\Models\Payment;
use Modules\Purchasing\Models\PaymentLine;
use Modules\Purchasing\Models\Purchase;

/**
 * Form and record page of 23. 買掛管理 (SYSTEM_BRAIN APP_23B fields.md "Form layout", widths from the template): 年月 /
 * 仕入先 with its 仕入カテゴリ, 消費税区分, 端数区分 and 計算区分 from the master, the roll-forward (前月買掛残 − 当月支払額 =
 * 差引残高; 仕入額 + 消費税額 = 当月合計金額 for 外; 差引残高 + 当月合計金額 = 当月買掛残, BR-PU-06, BR-TX-07), ▼ 仕入カテゴリ別内訳
 * (incl. 雑包装費, PayableCategories), 過去買掛レコード (the supplier's latest five months), 仕入情報詳細 (the purchases of
 * the month, oldest first) and 支払明細 (the payment rows of the month — legacy Customine 23買掛 page 3). Every figure is
 * recalculated (PayableLedger); the form edits 消費税端数調整 only.
 */
final class PayableLayout
{
    /** Figures shown as amounts. */
    public const AMOUNTS = ['previous_balance', 'paid', 'net_balance', 'cutting_amount', 'plate_amount', 'stored_plate_amount', 'spot_color_amount',
        'purchase_amount', 'tax_amount', 'tax_adjustment', 'month_total', 'closing_balance'];

    /** The supplier's master values: attribute => partner column. */
    public const MASTER = ['supplier_code' => 'code', 'purchase_category' => 'purchase_category', 'tax_mode' => 'supplier_tax_mode',
        'rounding' => 'supplier_rounding', 'calculation_unit' => 'supplier_tax_calculation_unit'];

    private const MAIN = [
        [['year_month', 193], ['supplier_code', 193], ['supplier_id', 386], ['purchase_category', 193], ['tax_mode', 193], ['rounding', 193], ['calculation_unit', 193]],
        [['previous_balance', 193], ['paid', 193], ['net_balance', 193]],
        [['cutting_amount', 193], ['plate_amount', 193], ['stored_plate_amount', 193], ['spot_color_amount', 193]],
        [['purchase_amount', 193], ['tax_amount', 193], ['tax_adjustment', 193], ['month_total', 193], ['closing_balance', 193]],
        '▼ 仕入カテゴリ別内訳',
        [['cat_materials', 193], ['cat_goods', 193], ['cat_materials_goods', 193]],
        [['cat_outsourcing', 193], ['cat_cost_supplies', 193], ['cat_cost_repairs', 193], ['cat_cost_delivery', 193], ['cat_cost_misc', 193]],
        [['cat_cost_packaging', 193], ['cat_supplies', 193], ['cat_repairs', 193], ['cat_delivery', 193], ['cat_misc', 193], ['cat_packaging', 193]],
        [['cat_office_supplies', 193], ['cat_expenses', 193], ['cat_misc_misc', 193], ['cat_misc_cost_repairs', 193], ['cat_misc_repairs', 193],
            ['cat_misc_cost_supplies', 193]],
        [['cat_misc_supplies', 193], ['cat_misc_materials', 193], ['cat_misc_goods', 193], ['cat_misc_freight', 193], ['cat_misc_outsourcing', 193],
            ['cat_misc_expenses', 193], ['cat_misc_packaging', 193]],
        [['other_months', 1219]],
        [['month_purchases', 1500]],
        [['month_payments', 1219]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 193], ['created_at', 196], ['updater', 193], ['updated_at', 196]]];

    private const DEVELOPER = [[['month_start', 117]]];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::components(
            fn (string $name): ?Component => match ($name) {
                // 吹き出し of the legacy form (Customine 23買掛 page 2)
                'tax_adjustment' => FieldTip::add(TextInput::make($name)->label(self::label($name))->numeric()->integer()->minValue(-99999999999)
                    ->maxValue(99999999999)->prefix('¥')->required(), '誤差の調整に利用するフィールド。「1」や「-1」の入力を行うことで消費税額の調整が可能です。'),
                'tax_amount' => FieldTip::add(self::entry($name), '仕入額に対して、「消費税区分」と「端数区分」によって計算されます。※「計算区分」は消費税額の計算式に含まれていません。'),
                'other_months', 'month_purchases', 'month_payments' => null,
                default => self::entry($name),
            },
            withFooter: false,
        ));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::components(self::entry(...), withFooter: true));
    }

    public static function label(string $attribute): string
    {
        return PurchasingFields::PAYABLE[$attribute];
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');
        $yen = fn (mixed $state): string => AppFormat::yen($state);

        return match (true) {
            in_array($name, self::AMOUNTS, true), isset(PayableCategories::LABELS[$name]) => $entry->formatStateUsing($yen),
            $name === 'cat_materials_goods' => $entry->state(fn (PayableMonth $record): string => AppFormat::yen($record->materialsAndGoods())),
            $name === 'year_month' => $entry->state(fn (PayableMonth $record): string => $record->monthLabel()),
            $name === 'supplier_id' => $entry->state(fn (PayableMonth $record): string => $record->supplier->name),
            isset(self::MASTER[$name]) => $entry->state(fn (PayableMonth $record): ?string => AppFormat::text($record->supplier->getAttribute(self::MASTER[$name]))),
            $name === 'other_months' => View::make('core::filament.related-table')->viewData(fn (PayableMonth $record): array => self::otherMonths($record)),
            $name === 'month_purchases' => View::make('core::filament.related-table')->viewData(fn (PayableMonth $record): array => self::monthPurchases($record)),
            $name === 'month_payments' => View::make('core::filament.related-table')->viewData(fn (PayableMonth $record): array => self::monthPayments($record)),
            $name === 'month_start' => $entry->date('Y-m-d'),
            $name === 'creator', $name === 'updater' => $entry->state(fn (PayableMonth $record): HtmlString => UserPopup::html(
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

    /**
     * 過去買掛レコード: the supplier's latest five months.
     *
     * @return array{title: string, columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>}
     */
    private static function otherMonths(PayableMonth $month): array
    {
        $months = PayableResource::onlyVisible(PayableMonth::query())->where('supplier_id', $month->supplier_id)->orderByDesc('year_month')->limit(5)->get();
        $figures = ['previous_balance', 'paid', 'net_balance', 'purchase_amount', 'month_total', 'closing_balance'];

        return [
            'title' => self::label('other_months'),
            'columns' => ['year_month' => self::label('year_month'), ...array_combine($figures, array_map(fn (string $name): string => self::label($name), $figures))],
            'numeric' => $figures,
            'rows' => $months->map(fn (PayableMonth $row): array => [
                'year_month' => $row->monthLabel(),
                ...array_combine($figures, array_map(fn (string $name): string => AppFormat::yen($row->getAttribute($name)), $figures)),
            ])->values()->all(),
            'urls' => $months->map(fn (PayableMonth $row): ?string => PayableResource::canView($row) ? PayableResource::getUrl('view', ['record' => $row]) : null)->values()->all(),
        ];
    }

    /**
     * 仕入情報詳細: the supplier's purchases of the month (納品(仕入)日), oldest first.
     *
     * @return array{title: string, columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>}
     */
    private static function monthPurchases(PayableMonth $month): array
    {
        $yearMonth = YearMonth::parse($month->year_month);
        $purchases = PurchaseResource::onlyVisible(Purchase::query())->with('supplier')->where('supplier_id', $month->supplier_id)
            ->whereBetween('purchased_on', [$yearMonth->firstDay()->toDateString(), $yearMonth->lastDay()->toDateString()])
            ->orderBy('purchased_on')->orderBy('id')->get();

        return [
            'title' => self::label('month_purchases'),
            ...PurchaseRows::of($purchases, ['purchased_on', 'purchase_number', 'product_name', 'unit_price', 'amount', 'cutting_amount', 'plate_amount',
                'stored_plate_amount', 'spot_color_amount', 'total', 'master_category', 'effective_category'], PurchaseResource::class),
        ];
    }

    /**
     * 支払明細: the supplier's payment rows of the month (出金日 = 支払日, BR-PU-08).
     *
     * @return array{title: string, columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>}
     */
    private static function monthPayments(PayableMonth $month): array
    {
        $yearMonth = YearMonth::parse($month->year_month);
        $lines = PaymentLine::query()->with('payment')->where('supplier_id', $month->supplier_id)
            ->whereIn('payment_id', PaymentResource::onlyVisible(Payment::query())->select('payments.id'))
            ->whereBetween('withdrawn_on', [$yearMonth->firstDay()->toDateString(), $yearMonth->lastDay()->toDateString()])
            ->orderBy('withdrawn_on')->orderBy('id')->get();
        $labels = PurchasingFields::PAYMENT;

        return [
            'title' => self::label('month_payments'),
            'columns' => ['withdrawn_on' => $labels['withdrawn_on'], 'method' => $labels['method'], 'amount' => $labels['amount'], 'notes' => $labels['notes']],
            'numeric' => ['amount'],
            'rows' => $lines->map(fn (PaymentLine $line): array => [
                'withdrawn_on' => AppFormat::date($line->withdrawn_on),
                'method' => $line->method,
                'amount' => AppFormat::yen($line->amount),
                'notes' => (string) $line->notes,
            ])->values()->all(),
            'urls' => $lines->map(fn (PaymentLine $line): ?string => PaymentResource::canView($line->payment) ? PaymentResource::getUrl('view', ['record' => $line->payment]) : null)
                ->values()->all(),
        ];
    }
}
