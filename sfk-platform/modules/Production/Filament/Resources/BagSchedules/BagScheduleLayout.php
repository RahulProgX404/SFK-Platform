<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\BagSchedules;

use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\UserPopup;
use Modules\MasterData\Models\Machine;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Models\Order;
use Modules\Production\Domain\ProductionChoices;
use Modules\Production\Domain\ProductionFields;
use Modules\Production\Models\BagSchedule;

/**
 * Form and record page of 12. 製袋予定管理 (fields of SYSTEM_BRAIN APP_12 fields.md), on a 12-column grid of the 1,500 px
 * canvas: 予定 (開始 / 終了日時, 機械名, 使用状況) and 内容 (入力方法, 部数, either 注番 — a job, its 品名 shown — or 注番(フリー) /
 * 品名(フリー) — the inputs of the other 入力方法 cannot be typed — and 備考). 開発者用フィールド shows the job's values the
 * schedule print uses.
 */
final class BagScheduleLayout
{
    /** The job's values of 開発者用フィールド: attribute => order attribute. */
    public const ORDER_VALUES = ['brought_on' => 'delivered_on', 'delivered_on' => 'completed_on', 'order_notes' => 'remarks', 'quantity' => 'quantity',
        'brought_by' => 'delivery_service', 'die_cut' => 'die_cut', 'window' => 'window', 'flap_glue' => 'flap_glue'];

    private const MAIN = [
        '予定',
        [['starts_at', 375], ['ends_at', 375], ['machine_id', 500], ['usage_status', 250]],
        '内容',
        [['entry_mode', 500], ['copies', 250]],
        [['order_id', 375], ['product_name', 1125]],
        [['free_order_number', 375], ['free_product_name', 1125]],
        [['notes', 1500]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [
        [['machine_code', 193], ['order_number_p', 193], ['product_name_p', 193], ['customer', 193], ['quantity', 193]],
        [['brought_on', 117], ['brought_by', 193], ['delivered_on', 117], ['order_notes', 193], ['die_cut', 193], ['window', 193], ['flap_glue', 193], ['legacy_id', 193]],
    ];

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
        return ProductionFields::SCHEDULE[$attribute];
    }

    /** The job's value of 開発者用フィールド, as shown. */
    public static function orderValue(?Order $order, string $attribute): ?string
    {
        if ($order === null) {
            return null;
        }

        $value = $order->getAttribute(self::ORDER_VALUES[$attribute]);

        return match ($attribute) {
            'brought_on', 'delivered_on' => AppFormat::date($value) ?: null,
            'quantity' => AppFormat::number($value) ?: null,
            default => AppFormat::text($value),
        };
    }

    private static function input(string $name): ?Component
    {
        $label = self::label($name);
        $order = fn (Get $get): ?Order => is_numeric($get('order_id')) ? Order::query()->with('customer')->find((int) $get('order_id')) : null;
        $free = fn (Get $get): bool => $get('entry_mode') === ProductionChoices::ENTRY_FREE;
        $text = fn (callable $state): TextEntry => TextEntry::make($name)->label($label)->placeholder('')->state($state);

        return match (true) {
            $name === 'starts_at' => DateTimePicker::make($name)->label($label)->seconds(false),
            $name === 'ends_at' => DateTimePicker::make($name)->label($label)->seconds(false)->afterOrEqual('starts_at'),
            $name === 'machine_id' => Select::make($name)
                ->label($label)
                ->relationship('machine', 'name', fn (Builder $query): Builder => $query->orderByDesc('id'))
                ->searchable()
                ->preload()
                ->placeholder('-----')
                ->live(),
            $name === 'usage_status' => Select::make($name)->label($label)->placeholder('-----')
                ->options(ProductionChoices::options(array_keys(ProductionChoices::USAGE_STATUSES)))->native(false),
            $name === 'entry_mode' => Radio::make($name)->label($label)->inline()->required()->live()
                ->options(ProductionChoices::options([ProductionChoices::ENTRY_FROM_ORDER, ProductionChoices::ENTRY_FREE]))
                ->default(ProductionChoices::ENTRY_FROM_ORDER),
            $name === 'order_id' => Select::make($name)
                ->label($label)
                ->relationship('order', 'order_number', fn (Builder $query): Builder => $query->orderByDesc('id'))
                ->searchable(['order_number', 'product_name'])
                ->preload()
                ->optionsLimit(100)
                ->placeholder('-----')
                ->live()
                ->disabled($free),
            $name === 'product_name' => $text(fn (Get $get): ?string => $order($get)?->product_name),
            $name === 'free_order_number' => TextInput::make($name)->label($label)->maxLength(64)->disabled(fn (Get $get): bool => ! $free($get)),
            $name === 'free_product_name' => TextInput::make($name)->label($label)->maxLength(255)->disabled(fn (Get $get): bool => ! $free($get)),
            $name === 'copies' => TextInput::make($name)->label($label)->numeric()->step('any')->rule('decimal:0,4')->minValue(0)->maxValue(999999999999),
            $name === 'notes' => TextInput::make($name)->label($label)->maxLength(255),
            $name === 'machine_code' => $text(fn (Get $get): ?string => is_numeric($get('machine_id')) ? Machine::query()->whereKey((int) $get('machine_id'))->value('code') : null),
            $name === 'order_number_p' => $text(fn (Get $get): ?string => $free($get) ? $get('free_order_number') : $order($get)?->order_number),
            $name === 'product_name_p' => $text(fn (Get $get): ?string => $free($get) ? $get('free_product_name') : $order($get)?->product_name),
            $name === 'customer' => $text(fn (Get $get): ?string => $order($get)?->customer?->name),
            isset(self::ORDER_VALUES[$name]) => $text(fn (Get $get): ?string => self::orderValue($order($get), $name)),
            $name === 'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');

        return match (true) {
            $name === 'starts_at', $name === 'ends_at' => $entry->dateTime('Y-m-d H:i'),
            $name === 'machine_id' => $entry->state(fn (BagSchedule $record): ?string => $record->machine?->name),
            $name === 'order_id' => $entry
                ->state(fn (BagSchedule $record): ?string => $record->order?->order_number)
                ->url(fn (BagSchedule $record): ?string => $record->order !== null && OrderResource::canView($record->order)
                    ? OrderResource::getUrl('view', ['record' => $record->order]) : null),
            $name === 'product_name' => $entry->state(fn (BagSchedule $record): ?string => $record->order?->product_name),
            $name === 'copies' => $entry->formatStateUsing(fn (mixed $state): string => AppFormat::number($state)),
            $name === 'machine_code' => $entry->state(fn (BagSchedule $record): ?string => $record->machine?->code),
            $name === 'order_number_p' => $entry->state(fn (BagSchedule $record): ?string => $record->orderNumber()),
            $name === 'product_name_p' => $entry->state(fn (BagSchedule $record): ?string => $record->productName()),
            $name === 'customer' => $entry->state(fn (BagSchedule $record): ?string => $record->order?->customer?->name),
            isset(self::ORDER_VALUES[$name]) => $entry->state(fn (BagSchedule $record): ?string => self::orderValue($record->order, $name)),
            $name === 'creator', $name === 'updater' => $entry->state(fn (BagSchedule $record): HtmlString => UserPopup::html(
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
}
