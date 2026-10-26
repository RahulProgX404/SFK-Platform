<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Purchases;

use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Money\Yen;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\FieldTip;
use Modules\Core\Filament\Support\UserPopup;
use Modules\MasterData\Domain\Partners\PurchaseCategory;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Models\Order;
use Modules\Purchasing\Domain\PurchaseChoices;
use Modules\Purchasing\Domain\PurchasingFields;
use Modules\Purchasing\Models\Purchase;

/**
 * Form and record page of 05. 納品(仕入)入力 (fields of SYSTEM_BRAIN APP_05 fields.md), grouped by task on a 12-column grid
 * of the 1,500 px canvas — 基本情報 (No, 納品(仕入)日, 仕入先, 帳端処理; the categories), 受注 (受注No, 品名, FSC, 注番基本情報),
 * 金額 (数量 × 単価 = 金額 and 仕入金額合計, then 断裁 / 版代 / 置き版代 / 特色), 原価チェック (仕入原価超過, 通知先) and
 * 同注番の仕入履歴 — with the legacy Customine behaviour:
 *
 * - 仕入先: 仕入先 only, newest first (lookup); its 消費税区分 and 仕入カテゴリ(マスタ) are shown from the master.
 * - 仕入カテゴリ: chosen only when it differs from the master (BR-PU-04); the four un-numbered legacy options are left out.
 * - 金額 = 数量 × 単価 when either changes, in whole yen by the supplier's 端数区分(仕入) (blank ⇒ 切捨), still editable
 *   (page 5, BR-PU-02); 仕入金額合計 = 金額 + 断裁 + 版代 + 置き版代 + 特色 (BR-PU-03).
 * - 納品(仕入)入力No is numbered on save (page 7, BR-PU-01) and not editable.
 * - 注番基本情報 (the job) and 同注番の仕入履歴 (its other purchases up to today, newest first) on the record page.
 *
 * The legacy 受注連携 / 買掛連携 flags and the 支払残 helpers are not carried over: the job costs and 23. 買掛管理 follow
 * every save (PurchasingObserver).
 */
final class PurchaseLayout
{
    private const MAIN = [
        '基本情報',
        [['purchase_number', 250], ['purchased_on', 250], ['supplier_id', 750], ['period_end', 250]],
        [['category', 500], ['master_category', 500], ['tax_mode', 500]],
        '受注',
        [['order_id', 375], ['product_name', 875], ['is_fsc', 250]],
        [['order_info', 1500]],
        '金額',
        [['quantity', 375], ['unit_price', 375], ['amount', 375], ['total', 375]],
        [['cutting_amount', 375], ['plate_amount', 375], ['stored_plate_amount', 375], ['spot_color_amount', 375]],
        '原価チェック',
        [['overruns', 750], ['notified_users', 750]],
        '同注番の仕入履歴',
        [['same_order', 1500]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [[['supplier_code', 193], ['supplier_rounding', 193], ['purchase_month', 193], ['effective_category', 193], ['legacy_id', 193]]];

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
        return PurchasingFields::PURCHASE[$attribute];
    }

    /** @return array<string, string> 仕入カテゴリ choices */
    public static function categoryOptions(): array
    {
        $options = [];
        foreach (PurchaseCategory::cases() as $category) {
            $options[$category->value] = $category->value;
        }

        return $options;
    }

    private static function input(string $name): ?Component
    {
        $label = self::label($name);
        $supplier = fn (Get $get): ?Partner => self::partner($get('supplier_id'));
        $text = fn (callable $state): TextEntry => TextEntry::make($name)->label($label)->placeholder('')->state($state);
        $money = fn (): TextInput => TextInput::make($name)->label($label)->numeric()->integer()->minValue(-99999999999)->maxValue(99999999999)
            ->prefix('¥')->live(onBlur: true);

        return match ($name) {
            'purchased_on' => DatePicker::make($name)->label($label)->required()->default(fn (): string => now()->toDateString()),
            'supplier_id' => Select::make($name)
                ->label($label)
                ->relationship('supplier', 'name', fn (Builder $query): Builder => $query->where('is_supplier', true)->orderByDesc('id'))
                ->searchable(['name', 'short_name', 'name_kana'])
                ->preload()
                ->placeholder('-----')
                ->required()
                ->live(),
            'tax_mode' => $text(fn (Get $get): ?string => AppFormat::text($supplier($get)?->getAttribute('supplier_tax_mode'))),
            'master_category' => $text(fn (Get $get): ?string => AppFormat::text($supplier($get)?->purchase_category)),
            // 吹き出し of the legacy form (Customine 05 page 3)
            'category' => FieldTip::add(Select::make($name)->label($label)->placeholder('-----')->options(self::categoryOptions())->native(false)->searchable(),
                'マスタ登録と異なる仕入カテゴリの場合は選択してください。'),
            'purchase_number' => TextInput::make($name)->label($label)->disabled()->dehydrated(false),
            'order_id' => Select::make($name)
                ->label($label)
                ->relationship('order', 'order_number', fn (Builder $query): Builder => $query->orderByDesc('id'))
                ->searchable(['order_number', 'product_name'])
                ->preload()
                ->optionsLimit(100)
                ->placeholder('-----'),
            'product_name' => TextInput::make($name)->label($label)->maxLength(255),
            'quantity', 'unit_price' => TextInput::make($name)->label($label)->numeric()->step('any')->rule('decimal:0,4')
                ->minValue(-999999999999)->maxValue(999999999999)
                ->prefix($name === 'unit_price' ? '¥' : null)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, Set $set) => self::fillAmount($get, $set)),
            'amount' => $money()->required(),
            'cutting_amount', 'plate_amount', 'stored_plate_amount', 'spot_color_amount' => $money()->default(0),
            'is_fsc' => self::flag($name, PurchaseChoices::FSC_CODE),
            'period_end' => self::flag($name, PurchaseChoices::PERIOD_END),
            'overruns' => CheckboxList::make($name)->label($label)->options(PurchaseChoices::options(PurchaseChoices::OVERRUNS))->columns(4),
            'notified_users' => Select::make($name)->label($label)->placeholder('-----')->relationship('notifiedUsers', 'name')->multiple()->searchable()->preload(),
            'total' => $text(fn (Get $get): string => AppFormat::yen(array_sum(array_map(fn (string $column): int => (int) $get($column), Purchase::AMOUNTS)))),
            'supplier_code' => $text(fn (Get $get): ?string => $supplier($get)?->code),
            'supplier_rounding' => $text(fn (Get $get): string => self::rounding($supplier($get))->value),
            'purchase_month' => $text(fn (Get $get): string => filled($get('purchased_on')) ? substr((string) $get('purchased_on'), 0, 4).'年'.substr((string) $get('purchased_on'), 5, 2).'月' : ''),
            'effective_category' => $text(fn (Get $get): ?string => filled($get('category')) ? (string) $get('category') : AppFormat::text($supplier($get)?->purchase_category)),
            'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');
        $yen = fn (mixed $state): string => AppFormat::yen($state);

        return match ($name) {
            'purchased_on' => $entry->date('Y-m-d'),
            'supplier_id' => $entry->state(fn (Purchase $record): string => $record->supplier->name),
            'tax_mode' => $entry->state(fn (Purchase $record): ?string => AppFormat::text($record->supplier->getAttribute('supplier_tax_mode'))),
            'master_category' => $entry->state(fn (Purchase $record): ?string => AppFormat::text($record->supplier->purchase_category)),
            'category' => $entry->state(fn (Purchase $record): ?string => AppFormat::text($record->category)),
            'order_id' => $entry
                ->state(fn (Purchase $record): ?string => $record->order?->order_number)
                ->url(fn (Purchase $record): ?string => $record->order !== null && OrderResource::canView($record->order)
                    ? OrderResource::getUrl('view', ['record' => $record->order]) : null),
            'order_info' => View::make('core::filament.related-table')
                ->viewData(fn (Purchase $record): array => self::orderInfo($record))
                ->hidden(fn (mixed $livewire): bool => (bool) ($livewire->printMode ?? false)),
            'same_order' => View::make('core::filament.related-table')
                ->viewData(fn (Purchase $record): array => self::sameOrder($record))
                ->hidden(fn (mixed $livewire): bool => (bool) ($livewire->printMode ?? false)),
            'quantity' => $entry->formatStateUsing(fn (mixed $state): string => AppFormat::number($state)),
            'unit_price' => $entry->formatStateUsing(fn (mixed $state): string => AppFormat::price($state)),
            'amount', 'cutting_amount', 'plate_amount', 'stored_plate_amount', 'spot_color_amount', 'total' => $entry->formatStateUsing($yen),
            'is_fsc' => $entry->state(fn (Purchase $record): ?string => $record->is_fsc ? PurchaseChoices::FSC_CODE : null),
            'period_end' => $entry->state(fn (Purchase $record): ?string => $record->period_end ? PurchaseChoices::PERIOD_END : null),
            'overruns' => $entry->state(fn (Purchase $record): ?string => implode('、', (array) $record->overruns) ?: null),
            // user names open the ユーザー情報 popup
            'notified_users' => $entry->state(fn (Purchase $record): HtmlString => UserPopup::list($record->notifiedUsers))->html(),
            'supplier_code' => $entry->state(fn (Purchase $record): ?string => $record->supplier->code),
            'supplier_rounding' => $entry->state(fn (Purchase $record): string => $record->roundingMode()->value),
            'purchase_month' => $entry->state(fn (Purchase $record): string => $record->month()->label()),
            'effective_category' => $entry->state(fn (Purchase $record): ?string => AppFormat::text($record->effectiveCategory())),
            'creator', 'updater' => $entry->state(fn (Purchase $record): HtmlString => UserPopup::html(
                $record->getRelationValue($name) instanceof User ? $record->getRelationValue($name) : null,
            ))->html(),
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

    /** A legacy single check box (FSC 「115」, 帳端処理 「〇」), stored as a boolean. */
    private static function flag(string $name, string $option): CheckboxList
    {
        return CheckboxList::make($name)
            ->label(self::label($name))
            ->options([$option => $option])
            ->formatStateUsing(fn (mixed $state): array => $state === true || $state === 1 || $state === '1' ? [$option] : (is_array($state) ? $state : []))
            ->dehydrateStateUsing(fn (mixed $state): bool => in_array($option, (array) $state, true));
    }

    /** 金額 = 数量 × 単価 (whole yen by the supplier's 端数区分(仕入)) once both are there (BR-PU-02). */
    private static function fillAmount(Get $get, Set $set): void
    {
        $quantity = $get('quantity');
        $price = $get('unit_price');
        if (! is_numeric($quantity) || ! is_numeric($price)) {
            return;
        }

        $set('amount', Yen::round(Yen::multiply((string) $quantity, (string) $price), self::rounding(self::partner($get('supplier_id')))));
    }

    /** 端数区分(仕入) of the supplier (blank ⇒ 切捨, BR-TX-02). */
    private static function rounding(?Partner $supplier): RoundingMode
    {
        return RoundingMode::fromLabel($supplier?->supplier_rounding?->value);
    }

    /**
     * 注番基本情報: the job of 受注No.
     *
     * @return array{title: string, columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>}
     */
    private static function orderInfo(Purchase $purchase): array
    {
        $orders = $purchase->order === null ? collect() : collect([$purchase->order->loadMissing('assignees')]);

        return [
            'title' => self::label('order_info'),
            'columns' => ['assignees' => '担当者', 'product_name' => '品名', 'quantity' => '数量', 'sold_quantity' => '売上済数量', 'sales_amount' => '売上金額',
                'estimated_cost_total' => '見積原価合計'],
            'numeric' => ['quantity', 'sold_quantity', 'sales_amount', 'estimated_cost_total'],
            'rows' => $orders->map(fn (Order $order): array => [
                'assignees' => $order->assignees->pluck('name')->implode('、'),
                'product_name' => (string) $order->product_name,
                'quantity' => AppFormat::number($order->quantity),
                'sold_quantity' => AppFormat::number($order->getAttribute('sold_quantity')),
                'sales_amount' => AppFormat::yen($order->sales_amount),
                'estimated_cost_total' => AppFormat::yen($order->estimated_cost_total),
            ])->values()->all(),
            'urls' => $orders->map(fn (Order $order): ?string => OrderResource::canView($order) ? OrderResource::getUrl('view', ['record' => $order]) : null)->values()->all(),
        ];
    }

    /**
     * 同注番の仕入履歴: the job's other purchases up to today, newest first (legacy related list).
     *
     * @return array{title: string, columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>}
     */
    private static function sameOrder(Purchase $purchase): array
    {
        $purchases = $purchase->order_id === null
            ? collect()
            : PurchaseResource::onlyVisible(Purchase::query())->with('supplier')->where('order_id', $purchase->order_id)->whereKeyNot($purchase->id)
                ->whereDate('purchased_on', '<=', now()->toDateString())->orderByDesc('id')->get();

        return [
            'title' => self::label('same_order'),
            ...PurchaseRows::of($purchases, ['purchased_on', 'supplier_id', 'product_name', 'unit_price', 'quantity', 'amount', 'cutting_amount', 'plate_amount',
                'stored_plate_amount', 'spot_color_amount'], PurchaseResource::class),
        ];
    }

    private static function partner(mixed $id): ?Partner
    {
        return is_numeric($id) ? Partner::query()->find((int) $id) : null;
    }
}
