<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payments;

use App\Models\User;
use DateTimeInterface;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Records\RecordTable;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\UserPopup;
use Modules\MasterData\Models\Partner;
use Modules\Purchasing\Domain\PurchaseChoices;
use Modules\Purchasing\Domain\PurchasingFields;
use Modules\Purchasing\Models\Payment;
use Modules\Purchasing\Models\PaymentLine;

/**
 * Form and record page of 07. 支払入力 (fields of SYSTEM_BRAIN APP_07 fields.md, on a 12-column grid of the 1,500 px
 * canvas): 基本情報 — 支払日,
 * 支払金額合計 (= Σ 支払額) and the table 支払内容詳細 — 仕入先 (仕入先 only, newest first; 仕入先ID from the master), 金種名
 * (default 7.振込), 支払額, 出金日, 備考 and 支払年月. Like auto-fill-payment-date.js, every row's 出金日 is the 支払日 and
 * cannot be typed (BR-PU-08). The legacy key columns 買掛更新キー / コピー用日付 are not carried over.
 */
final class PaymentLayout
{
    private const MAIN = [
        '基本情報',
        [['paid_on', 250], ['total', 375]],
        '支払内容詳細',
        [['lines', 1500]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 193], ['created_at', 196], ['updater', 193], ['updated_at', 196]]];

    private const DEVELOPER = [[['legacy_id', 193]]];

    /** 支払内容詳細 columns => width (legacy template). */
    private const LINE_COLUMNS = ['supplier_id' => 452, 'supplier_code' => 98, 'method' => 152, 'amount' => 193, 'withdrawn_on' => 117, 'notes' => 280,
        'payment_month' => 193];

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
        return PurchasingFields::PAYMENT[$attribute];
    }

    private static function input(string $name): ?Component
    {
        $label = self::label($name);

        return match ($name) {
            'paid_on' => DatePicker::make($name)->label($label)->required()->default(fn (): string => now()->toDateString())->live(),
            'total' => TextEntry::make($name)->label($label)->placeholder('')->state(fn (Get $get): string => AppFormat::yen(array_sum(array_map(
                fn (mixed $row): int => is_array($row) && is_numeric($row['amount'] ?? null) ? (int) $row['amount'] : 0,
                array_values((array) $get('lines')),
            )))),
            'lines' => self::linesForm(),
            'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');

        return match ($name) {
            'paid_on' => $entry->date('Y-m-d'),
            'total' => $entry->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            'lines' => self::linesEntry(),
            'creator', 'updater' => $entry->state(fn (Payment $record): HtmlString => UserPopup::html(
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

    private static function linesForm(): Repeater
    {
        $paidOn = fn (Get $get): mixed => $get('../../paid_on');

        return RecordTable::form(
            Repeater::make('lines')->label(self::label('lines'))->relationship('lines')->orderColumn('sort'),
            self::lineHeaders(),
            ['method' => PurchaseChoices::DEFAULT_PAYMENT_METHOD],
        )->schema([
            Select::make('supplier_id')->hiddenLabel()->native()->placeholder('-----')
                ->relationship('supplier', 'name', fn (Builder $query): Builder => $query->where('is_supplier', true)->orderByDesc('id'))
                ->preload()
                ->live(),
            TextEntry::make('supplier_code')->hiddenLabel()->placeholder('')
                ->state(fn (Get $get): ?string => is_numeric($get('supplier_id')) ? Partner::query()->whereKey((int) $get('supplier_id'))->value('code') : null),
            Select::make('method')->hiddenLabel()->native()->selectablePlaceholder(false)
                ->options(PurchaseChoices::options(PurchaseChoices::PAYMENT_METHODS))->default(PurchaseChoices::DEFAULT_PAYMENT_METHOD),
            TextInput::make('amount')->hiddenLabel()->numeric()->integer()->minValue(-99999999999)->maxValue(99999999999)->live(onBlur: true),
            TextEntry::make('withdrawn_on')->hiddenLabel()->placeholder('')->state(fn (Get $get): string => AppFormat::date(self::date($paidOn($get)))),
            TextInput::make('notes')->hiddenLabel()->maxLength(255),
            TextEntry::make('payment_month')->hiddenLabel()->placeholder('')->state(fn (Get $get): string => self::month($paidOn($get))),
        ]);
    }

    private static function linesEntry(): RepeatableEntry
    {
        return RecordTable::entry(
            RepeatableEntry::make('lines')->label(self::label('lines'))->placeholder(''),
            self::lineHeaders(),
        )->schema([
            TextEntry::make('supplier_id')->placeholder('')->state(fn (PaymentLine $record): ?string => $record->supplier?->name),
            TextEntry::make('supplier_code')->placeholder('')->state(fn (PaymentLine $record): ?string => $record->supplier?->code),
            TextEntry::make('method')->placeholder(''),
            TextEntry::make('amount')->alignEnd()->placeholder('')->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            TextEntry::make('withdrawn_on')->placeholder('')->date('Y-m-d'),
            TextEntry::make('notes')->placeholder(''),
            TextEntry::make('payment_month')->placeholder('')->state(fn (PaymentLine $record): string => $record->month()?->label() ?? ''),
        ]);
    }

    /** @return list<array{0: string, 1: int}> */
    private static function lineHeaders(): array
    {
        return array_map(fn (string $key, int $width): array => [self::label($key), $width], array_keys(self::LINE_COLUMNS), self::LINE_COLUMNS);
    }

    private static function date(mixed $value): ?DateTimeInterface
    {
        return $value instanceof DateTimeInterface ? $value : (is_string($value) && $value !== '' ? Carbon::parse($value) : null);
    }

    /** 支払年月 「2026年10月」 of 支払日. */
    private static function month(mixed $value): string
    {
        $date = self::date($value);

        return $date === null ? '' : YearMonth::fromDate($date)->label();
    }
}
