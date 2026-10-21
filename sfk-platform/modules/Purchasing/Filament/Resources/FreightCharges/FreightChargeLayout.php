<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\FreightCharges;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Purchasing\Domain\PurchasingFields;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseResource;
use Modules\Purchasing\Models\FreightCharge;

/**
 * Form and record page of 15. 運送請求データ取込 (SYSTEM_BRAIN APP_15 fields.md "Form layout", widths from the template):
 * 日付 (a date here, text in the legacy app), 注番 (a job), 金額, 運送会社番号, the purchase-style figures and 備考.
 * 納品(仕入)入力No is the purchase the record was posted as (選択して納品(仕入)を作成), not typed.
 */
final class FreightChargeLayout
{
    private const MAIN = [
        [['charged_on', 193], ['order_id', 193], ['amount', 193], ['carrier_number', 193]],
        [['purchase_id', 193], ['unit_price', 193], ['quantity', 193], ['cutting_amount', 193], ['plate_amount', 193], ['stored_plate_amount', 193],
            ['spot_color_amount', 193]],
        [['notes', 772]],
    ];

    private const FOOTER = [[['id', 150], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [[['legacy_id', 193]]];

    public const AMOUNTS = ['amount', 'cutting_amount', 'plate_amount', 'stored_plate_amount', 'spot_color_amount'];

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
        return PurchasingFields::FREIGHT[$attribute];
    }

    private static function input(string $name): ?Component
    {
        $label = self::label($name);

        return match (true) {
            $name === 'charged_on' => DatePicker::make($name)->label($label),
            $name === 'order_id' => Select::make($name)
                ->label($label)
                ->relationship('order', 'order_number', fn (Builder $query): Builder => $query->orderByDesc('id'))
                ->searchable(['order_number', 'product_name'])
                ->preload()
                ->optionsLimit(100)
                ->placeholder('-----'),
            in_array($name, self::AMOUNTS, true) => TextInput::make($name)->label($label)->numeric()->integer()->minValue(-99999999999)->maxValue(99999999999)->prefix('¥'),
            $name === 'carrier_number' => TextInput::make($name)->label($label)->maxLength(64),
            $name === 'purchase_id' => TextEntry::make($name)->label($label)->placeholder('')->state(fn (?FreightCharge $record): ?string => $record?->purchase?->purchase_number),
            $name === 'unit_price', $name === 'quantity' => TextInput::make($name)->label($label)->numeric()->step('any')->rule('decimal:0,4')
                ->minValue(-999999999999)->maxValue(999999999999)->prefix($name === 'unit_price' ? '¥' : null),
            $name === 'notes' => TextInput::make($name)->label($label)->maxLength(255),
            $name === 'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');

        return match (true) {
            $name === 'charged_on' => $entry->date('Y-m-d'),
            $name === 'order_id' => $entry
                ->state(fn (FreightCharge $record): ?string => $record->order?->order_number)
                ->url(fn (FreightCharge $record): ?string => $record->order !== null && OrderResource::canView($record->order)
                    ? OrderResource::getUrl('view', ['record' => $record->order]) : null),
            $name === 'purchase_id' => $entry
                ->state(fn (FreightCharge $record): ?string => $record->purchase?->purchase_number)
                ->url(fn (FreightCharge $record): ?string => $record->purchase !== null && PurchaseResource::canView($record->purchase)
                    ? PurchaseResource::getUrl('view', ['record' => $record->purchase]) : null),
            in_array($name, self::AMOUNTS, true) => $entry->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            $name === 'unit_price' => $entry->formatStateUsing(fn (mixed $state): string => AppFormat::price($state)),
            $name === 'quantity' => $entry->formatStateUsing(fn (mixed $state): string => AppFormat::number($state)),
            $name === 'creator', $name === 'updater' => $entry->state(fn (FreightCharge $record): HtmlString => UserPopup::html(
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
