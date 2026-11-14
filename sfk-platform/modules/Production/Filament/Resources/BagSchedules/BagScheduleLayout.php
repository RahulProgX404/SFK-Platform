<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\BagSchedules;

use App\Models\User;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\UserPopup;
use Modules\MasterData\Models\Machine;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Models\Order;
use Modules\Production\Application\Schedules\ProductionSchedule;
use Modules\Production\Application\Schedules\SalesLookup;
use Modules\Production\Domain\ProductionFields;
use Modules\Production\Domain\Schedules\ScheduleBoard;
use Modules\Production\Models\BagSchedule;

/**
 * Form and record page of 12. 製袋予定管理 in the fields of the 製袋予定表 (production-schedule.js), on a 12-column grid of
 * the 1,500 px canvas: 予定 (日付, 機械名 — a line of the board —, ハイライト) and 内容 (注番, 担当者, 部数, 品名, 備考 — one per
 * line, up to 10). With a 注番, 担当者 / 品名 / 部数 come from 06. 売上入力 (shown when it is chosen, taken again when saved:
 * Pages\Concerns\TakesSalesValues); without one 品名 and 部数 are required. 開発者用フィールド (super admins) shows the
 * legacy 開始 / 終了日時, 使用状況, 入力方法 and free 注番 / 品名 and the job's values the schedule print uses.
 */
final class BagScheduleLayout
{
    /** The job's values of 開発者用フィールド: attribute => order attribute. */
    public const ORDER_VALUES = ['brought_on' => 'delivered_on', 'delivered_on' => 'completed_on', 'order_notes' => 'remarks', 'quantity' => 'quantity',
        'brought_by' => 'delivery_service', 'die_cut' => 'die_cut', 'window' => 'window', 'flap_glue' => 'flap_glue'];

    private const MAIN = [
        '予定',
        [['scheduled_on', 250], ['machine_id', 500], ['highlight_color', 250]],
        '内容',
        [['order_id', 375], ['person_initial', 375], ['copies', 250]],
        [['product_name', 1500]],
        [['notes', 1500]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [
        [['starts_at', 193], ['ends_at', 193], ['usage_status', 193], ['entry_mode', 193], ['free_order_number', 193], ['free_product_name', 386]],
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
        // a job the user may see, or one of their visible schedules (security review 2026-11-14)
        $order = fn (Get $get): ?Order => is_numeric($get('order_id')) ? OrderResource::visibleOr(Order::query(), BagScheduleResource::getEloquentQuery()->select('bag_schedules.order_id'))->with('customer')->find((int) $get('order_id')) : null;
        $ofOrder = fn (Get $get): bool => is_numeric($get('order_id'));
        $text = fn (callable $state): TextEntry => TextEntry::make($name)->label($label)->placeholder('')->state($state);

        return match (true) {
            $name === 'scheduled_on' => DatePicker::make($name)->label($label)->required()->default(fn (): string => now()->toDateString()),
            $name === 'machine_id' => Select::make($name)->label($label)->placeholder('-----')->required()->native(false)
                ->options(fn (): array => self::lineOptions()),
            $name === 'highlight_color' => Select::make($name)->label($label)->placeholder('なし')->native(false)->options(ScheduleBoard::HIGHLIGHTS),
            $name === 'order_id' => Select::make($name)
                ->label($label)
                ->relationship('order', 'order_number', fn (Builder $query): Builder => OrderResource::visibleOr($query, BagScheduleResource::getEloquentQuery()->select('bag_schedules.order_id'))->orderByDesc('id'))
                ->searchable(['order_number', 'product_name'])
                ->preload()
                ->optionsLimit(100)
                ->placeholder('-----')
                ->live()
                ->afterStateUpdated(function (mixed $state, Set $set): void {
                    $sale = is_numeric($state) ? self::saleOf((int) $state) : null;
                    if ($sale !== null) {
                        $set('person_initial', $sale['person']);
                        $set('product_name', $sale['product']);
                        $set('copies', $sale['quantity']);
                    }
                }),
            $name === 'person_initial' => TextInput::make($name)->label($label)->maxLength(40)->disabled($ofOrder),
            $name === 'product_name' => TextInput::make($name)->label($label)->maxLength(120)->disabled($ofOrder)
                ->required(fn (Get $get): bool => ! $ofOrder($get)),
            $name === 'copies' => TextInput::make($name)->label($label)->numeric()->step('any')->rule('decimal:0,4')->minValue(0)->maxValue(999999999999)
                ->disabled($ofOrder)->required(fn (Get $get): bool => ! $ofOrder($get)),
            $name === 'notes' => Textarea::make($name)->label($label)->rows(3)
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $notes = array_filter(array_map(trim(...), preg_split('/\r\n|\r|\n/', (string) $value) ?: []), fn (string $note): bool => $note !== '');
                    if (count($notes) > ScheduleBoard::MAX_NOTES) {
                        $fail('備考は'.ScheduleBoard::MAX_NOTES.'件までです');
                    } elseif (array_filter($notes, fn (string $note): bool => mb_strlen($note) > 40) !== []) {
                        $fail('備考は1件40文字以内で入力してください');
                    }
                }),
            $name === 'machine_code' => $text(fn (Get $get): ?string => is_numeric($get('machine_id')) ? Machine::query()->whereKey((int) $get('machine_id'))->value('code') : null),
            $name === 'order_number_p' => $text(fn (Get $get): ?string => $order($get)?->order_number),
            $name === 'product_name_p' => $text(fn (Get $get): ?string => $get('product_name')),
            $name === 'customer' => $text(fn (Get $get): ?string => $order($get)?->customer?->name),
            isset(self::ORDER_VALUES[$name]) => $text(fn (Get $get): ?string => self::orderValue($order($get), $name)),
            $name === 'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    /** @return array<int, string> 機械名: the lines of the board (machine id => label) */
    public static function lineOptions(): array
    {
        $options = [];
        foreach (app(ProductionSchedule::class)->lines() as $line) {
            $options[$line['machine']] = trim($line['label'].'　'.($line['subtitle'] ?? ''));
        }

        return $options;
    }

    /** @return array{order: Order, person: string, product: string, quantity: string|null}|null the 06. 売上入力 values of a job */
    public static function saleOf(int $orderId): ?array
    {
        $number = Order::query()->whereKey($orderId)->value('order_number');

        return is_string($number) ? app(SalesLookup::class)->find($number) : null;
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');

        return match (true) {
            $name === 'scheduled_on' => $entry->date('Y-m-d'),
            $name === 'starts_at', $name === 'ends_at' => $entry->dateTime('Y-m-d H:i'),
            $name === 'machine_id' => $entry->state(fn (BagSchedule $record): ?string => $record->machine?->name),
            $name === 'highlight_color' => $entry->formatStateUsing(fn (?string $state): string => ScheduleBoard::HIGHLIGHTS[(string) $state] ?? ''),
            $name === 'notes' => $entry->state(fn (BagSchedule $record): ?string => $record->noteList() !== [] ? implode('、', $record->noteList()) : null),
            $name === 'order_id' => $entry
                ->state(fn (BagSchedule $record): ?string => $record->orderNumber())
                ->url(fn (BagSchedule $record): ?string => $record->order !== null && OrderResource::canView($record->order)
                    ? OrderResource::getUrl('view', ['record' => $record->order]) : null),
            $name === 'product_name' => $entry->state(fn (BagSchedule $record): ?string => $record->productName()),
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
