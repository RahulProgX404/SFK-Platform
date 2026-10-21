<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\PastSales;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Modules\Analytics\Domain\AnalyticsFields;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Analytics\Models\PastSale;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\UserPopup;

/**
 * Form and record page of 22. 過去の売上データ (SYSTEM_BRAIN APP_22 fields.md "Form layout", widths from the template):
 * 担当者, 異常値フラグ, 決算最終月初 with 決算年度, 小計 / 年間合計, the twelve months May–April and レコードキー.
 */
final class PastSaleLayout
{
    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [[['date_label', 193], ['year_number', 193], ['date_number', 193], ['era', 193], ['legacy_id', 193]]];

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
        return str_starts_with($attribute, 'm') && strlen($attribute) === 3 ? AnalyticsFields::month($attribute, padded: false) : AnalyticsFields::PAST_SALES[$attribute];
    }

    private static function input(string $name): ?Component
    {
        $label = self::label($name);
        $start = fn (Get $get): ?Carbon => filled($get('fiscal_month_start')) ? Carbon::parse((string) $get('fiscal_month_start')) : null;
        $total = fn (Get $get): string => AppFormat::yen(PastSale::sumOf(array_map(fn (string $column): mixed => $get($column), FiscalYear::columns())));
        $text = fn (callable $state): TextEntry => TextEntry::make($name)->label($label)->placeholder('')->state($state);

        return match (true) {
            in_array($name, FiscalYear::columns(), true) => TextInput::make($name)->label($label)->numeric()->integer()->minValue(-99999999999)->maxValue(99999999999)
                ->prefix('¥')->live(onBlur: true),
            $name === 'salesperson' => TextInput::make($name)->label($label)->maxLength(255),
            $name === 'flag' => Radio::make($name)->label($label)->inline()->options(array_combine(PastSale::FLAGS, PastSale::FLAGS))->default(PastSale::FLAGS[0])->required(),
            $name === 'fiscal_month_start' => DatePicker::make($name)->label($label)->live(),
            $name === 'fiscal_year' => $text(fn (Get $get): ?string => $start($get)?->format('Y年')),
            $name === 'subtotal', $name === 'total' => $text($total),
            $name === 'record_key' => TextInput::make($name)->label($label)->maxLength(255),
            $name === 'date_label' => $text(fn (Get $get): ?string => $start($get)?->format('Y年m月d日')),
            $name === 'year_number' => $text(fn (Get $get): ?string => $start($get)?->format('Y')),
            $name === 'date_number' => $text(fn (Get $get): ?string => $start($get)?->format('Ymd')),
            $name === 'era' => $text(fn (Get $get): ?string => ($day = $start($get)) === null ? null : FiscalYear::era($day)),
            $name === 'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');

        return match (true) {
            in_array($name, FiscalYear::columns(), true), $name === 'total' => $entry->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            $name === 'subtotal' => $entry->state(fn (PastSale $record): string => AppFormat::yen($record->total)),
            $name === 'fiscal_month_start' => $entry->date('Y-m-d'),
            $name === 'fiscal_year' => $entry->state(fn (PastSale $record): ?string => $record->fiscalYear()),
            $name === 'date_label' => $entry->state(fn (PastSale $record): ?string => $record->fiscal_month_start?->format('Y年m月d日')),
            $name === 'year_number' => $entry->state(fn (PastSale $record): ?string => $record->fiscal_month_start?->format('Y')),
            $name === 'date_number' => $entry->state(fn (PastSale $record): ?string => $record->fiscal_month_start?->format('Ymd')),
            $name === 'era' => $entry->state(fn (PastSale $record): ?string => $record->fiscal_month_start === null ? null : FiscalYear::era($record->fiscal_month_start)),
            $name === 'creator', $name === 'updater' => $entry->state(fn (PastSale $record): HtmlString => UserPopup::html(
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
        $months = array_map(fn (string $column): array => [$column, 193], FiscalYear::columns());
        $main = [
            [['salesperson', 193], ['flag', 253]],
            [['fiscal_month_start', 117], ['fiscal_year', 193], ['subtotal', 193], ['total', 193]],
            array_slice($months, 0, 6),
            array_slice($months, 6),
            [['record_key', 193]],
        ];

        $components = [RecordSections::section($main, $make)];
        if ($withFooter) {
            $components[] = RecordSections::section(self::FOOTER, $make);
        }
        if (auth()->user() instanceof User && auth()->user()->isSuperAdmin()) {
            $components[] = RecordSections::section(self::DEVELOPER, $make, '開発者用フィールド', collapsed: true);
        }

        return $components;
    }
}
