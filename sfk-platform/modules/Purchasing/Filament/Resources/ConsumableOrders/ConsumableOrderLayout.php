<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\ConsumableOrders;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Records\RecordTable;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\UserPopup;
use Modules\MasterData\Models\Consumable;
use Modules\MasterData\Models\Partner;
use Modules\Purchasing\Domain\ConsumableOrders\ConsumableOrderStatus;
use Modules\Purchasing\Domain\PurchasingFields;
use Modules\Purchasing\Models\ConsumableOrder;
use Modules\Purchasing\Models\ConsumableOrderLine;

/**
 * Form and record page of 20. 消耗品発注 (fields of SYSTEM_BRAIN APP_20 fields.md, on a 12-column grid of the 1,500 px
 * canvas): 基本情報 (発注日,
 * 発注先 (仕入先 only, newest first), 発注者, the table 発注明細 (商品名 = lookup of 19. 消耗品マスタ, newest first, copying
 * 単価 / 単位 — shown, not typed) and その他 (備考). The record page shows ステータス and 作業者 of the プロセス管理 above the
 * fields; 開発者用フィールド shows the 発注先's master values (the legacy lookup copies).
 */
final class ConsumableOrderLayout
{
    private const STATUS = [[['status', 250], ['assignee', 375]]];

    private const MAIN = [
        '基本情報',
        [['ordered_on', 250], ['supplier_id', 875], ['orderers', 375]],
        '発注明細',
        [['lines', 1500]],
        'その他',
        [['notes', 1500]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [
        [['supplier_record', 193], ['supplier_code', 193], ['purchase_category', 193], ['tax_mode', 193], ['calculation_unit', 193], ['rounding', 193]],
        [['group', 193], ['tel', 193], ['fax', 193], ['legacy_id', 193]],
    ];

    /** 発注明細 columns => width (legacy template). */
    private const LINE_COLUMNS = ['consumable_id' => 472, 'unit_price' => 160, 'quantity' => 123, 'unit' => 111, 'requested_on' => 117, 'orderer' => 193,
        'delivered_on' => 117, 'line_notes' => 249];

    /** The 発注先's master values of 開発者用フィールド: attribute => partner column. */
    public const SUPPLIER_VALUES = ['supplier_record' => 'id', 'supplier_code' => 'code', 'purchase_category' => 'purchase_category',
        'tax_mode' => 'supplier_tax_mode', 'calculation_unit' => 'supplier_tax_calculation_unit', 'rounding' => 'supplier_rounding',
        'group' => 'customer_classification', 'tel' => 'supplier_phone', 'fax' => 'supplier_fax'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::components(self::input(...), withRecordRows: false));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::components(self::entry(...), withRecordRows: true));
    }

    public static function label(string $attribute): string
    {
        return PurchasingFields::CONSUMABLE_ORDER[$attribute];
    }

    /** 作業者: the 作成者 while 未処理, the administrators while 確認中, nobody once 承認済. */
    public static function assignee(ConsumableOrder $order): HtmlString
    {
        return match ($order->status) {
            ConsumableOrderStatus::Unprocessed => UserPopup::html($order->creator),
            ConsumableOrderStatus::Checking => new HtmlString(e('管理者')),
            ConsumableOrderStatus::Approved => new HtmlString(''),
        };
    }

    private static function input(string $name): ?Component
    {
        $label = self::label($name);
        $supplier = fn (Get $get): ?Partner => is_numeric($get('supplier_id')) ? Partner::query()->find((int) $get('supplier_id')) : null;

        return match (true) {
            $name === 'ordered_on' => DatePicker::make($name)->label($label)->required()->default(fn (): string => now()->toDateString()),
            $name === 'supplier_id' => Select::make($name)
                ->label($label)
                ->relationship('supplier', 'name', fn (Builder $query): Builder => $query->where('is_supplier', true)->orderByDesc('id'))
                ->searchable(['name', 'short_name', 'name_kana'])
                ->preload()
                ->placeholder('-----')
                ->live(),
            $name === 'orderers' => Select::make($name)->label($label)->placeholder('-----')->relationship('orderers', 'name')->multiple()->searchable()->preload(),
            $name === 'lines' => self::linesForm(),
            $name === 'notes' => Textarea::make($name)->label($label)->rows(3)->maxLength(4000),
            isset(self::SUPPLIER_VALUES[$name]) => TextEntry::make($name)->label($label)->placeholder('')
                ->state(fn (Get $get): ?string => AppFormat::text($supplier($get)?->getAttribute(self::SUPPLIER_VALUES[$name]))),
            $name === 'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');

        return match (true) {
            $name === 'status' => $entry->state(fn (ConsumableOrder $record): string => $record->status->value),
            $name === 'assignee' => $entry->state(fn (ConsumableOrder $record): HtmlString => self::assignee($record))->html(),
            $name === 'ordered_on' => $entry->date('Y-m-d'),
            $name === 'supplier_id' => $entry->state(fn (ConsumableOrder $record): ?string => $record->supplier?->name),
            // user names open the ユーザー情報 popup
            $name === 'orderers' => $entry->state(fn (ConsumableOrder $record): HtmlString => UserPopup::list($record->orderers))->html(),
            $name === 'lines' => self::linesEntry(),
            isset(self::SUPPLIER_VALUES[$name]) => $entry->state(fn (ConsumableOrder $record): ?string => AppFormat::text($record->supplier?->getAttribute(self::SUPPLIER_VALUES[$name]))),
            $name === 'creator', $name === 'updater' => $entry->state(fn (ConsumableOrder $record): HtmlString => UserPopup::html(
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
    private static function components(callable $make, bool $withRecordRows): array
    {
        $components = [];
        if ($withRecordRows) {
            $components[] = RecordSections::section(self::STATUS, $make);
        }
        $components[] = RecordSections::section(self::MAIN, $make);
        if ($withRecordRows) {
            $components[] = RecordSections::section(self::FOOTER, $make);
        }
        if (auth()->user() instanceof User && auth()->user()->isSuperAdmin()) {
            $components[] = RecordSections::section(self::DEVELOPER, $make, '開発者用フィールド', collapsed: true);
        }

        return $components;
    }

    private static function linesForm(): Repeater
    {
        $consumable = fn (Get $get): ?Consumable => is_numeric($get('consumable_id')) ? Consumable::query()->find((int) $get('consumable_id')) : null;
        // the row's copy while its item is unchanged, else the chosen item's master value
        $copied = fn (string $column): \Closure => fn (Get $get, ?Model $record): ?string => $record instanceof ConsumableOrderLine
            && $record->consumable_id !== null && (int) $record->consumable_id === (int) $get('consumable_id')
                ? $record->getAttribute($column)
                : $consumable($get)?->getAttribute($column);

        return RecordTable::form(
            Repeater::make('lines')->label(self::label('lines'))->relationship('lines')->orderColumn('sort'),
            self::lineHeaders(),
        )->schema([
            Select::make('consumable_id')->hiddenLabel()->native()->placeholder('-----')
                ->relationship('consumable', 'name', fn (Builder $query): Builder => $query->orderByDesc('id'))
                ->preload()
                ->live(),
            TextEntry::make('unit_price')->hiddenLabel()->placeholder('')->alignEnd()
                ->state(fn (Get $get, ?Model $record): string => AppFormat::price($copied('unit_price')($get, $record))),
            TextInput::make('quantity')->hiddenLabel()->numeric()->step('any')->rule('decimal:0,4')->minValue(-999999999999)->maxValue(999999999999),
            TextEntry::make('unit')->hiddenLabel()->placeholder('')->state(fn (Get $get, ?Model $record): ?string => $copied('unit')($get, $record)),
            DatePicker::make('requested_on')->hiddenLabel()->native(),
            TextInput::make('orderer')->hiddenLabel()->maxLength(255),
            DatePicker::make('delivered_on')->hiddenLabel()->native(),
            TextInput::make('notes')->hiddenLabel()->maxLength(255),
        ]);
    }

    private static function linesEntry(): RepeatableEntry
    {
        return RecordTable::entry(
            RepeatableEntry::make('lines')->label(self::label('lines'))->placeholder(''),
            self::lineHeaders(),
        )->schema([
            TextEntry::make('consumable_id')->placeholder('')->state(fn (ConsumableOrderLine $record): ?string => $record->consumable?->name),
            TextEntry::make('unit_price')->alignEnd()->placeholder('')->formatStateUsing(fn (mixed $state): string => AppFormat::price($state)),
            TextEntry::make('quantity')->alignEnd()->placeholder('')->formatStateUsing(fn (mixed $state): string => AppFormat::number($state)),
            TextEntry::make('unit')->placeholder(''),
            TextEntry::make('requested_on')->placeholder('')->date('Y-m-d'),
            TextEntry::make('orderer')->placeholder(''),
            TextEntry::make('delivered_on')->placeholder('')->date('Y-m-d'),
            TextEntry::make('notes')->placeholder(''),
        ]);
    }

    /** @return list<array{0: string, 1: int}> */
    private static function lineHeaders(): array
    {
        return array_map(fn (string $key, int $width): array => [self::label($key), $width], array_keys(self::LINE_COLUMNS), self::LINE_COLUMNS);
    }
}
