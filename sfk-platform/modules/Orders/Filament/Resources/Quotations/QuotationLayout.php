<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Quotations;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Records\RecordTable;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\UserPopup;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Domain\Quotations\QuotationFields;
use Modules\Orders\Models\Quotation;
use Modules\Orders\Models\QuotationLine;

/**
 * Form and record page of 09. 見積書管理 (SYSTEM_BRAIN APP_09 fields.md "Form layout", widths from the template): 見積日付,
 * 得意先名 (found by name or 略名 — the legacy 略名取得ver lookup is part of the search), 担当者 (copied from the customer's
 * 自社担当者 when it is chosen), 見積書番号, 品名, the specification, 見積金額(税抜), the quantity tiers 見積内容 (金額 =
 * 単価 × 枚数), 備考 and 見積書PDF. The quotation itself is printed with 印刷する.
 */
final class QuotationLayout
{
    private const MAIN = [
        [['quoted_on', 117], ['customer_id', 493], ['assignees', 344], ['quote_number', 193]],
        [['product_name', 410]],
        [['size', 249], ['paper', 249], ['printing', 249], ['window', 249], ['flap_glue', 249]],
        [['free_text_1', 249], ['free_text_2', 249], ['free_text_3', 249]],
        [['total', 193]],
        [['lines', 600]],
        [['notes', 965]],
        [['pdf_files', 400]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [[['customer_code', 193], ['file_name', 386], ['legacy_id', 193]]];

    private const TEXTS = ['product_name', 'size', 'paper', 'printing', 'window', 'flap_glue', 'free_text_1', 'free_text_2', 'free_text_3'];

    /** 見積内容 columns => width (legacy template). */
    private const LINE_COLUMNS = ['sheets' => 193, 'unit_price' => 193, 'amount' => 193];

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
        return QuotationFields::label($attribute);
    }

    private static function input(string $name): ?Component
    {
        $label = self::label($name);
        $customer = fn (Get $get): ?Partner => is_numeric($get('customer_id')) ? Partner::query()->find((int) $get('customer_id')) : null;
        $text = fn (callable $state): TextEntry => TextEntry::make($name)->label($label)->placeholder('')->state($state);

        return match (true) {
            $name === 'quoted_on' => DatePicker::make($name)->label($label)->required()->default(fn (): string => now()->toDateString()),
            $name === 'customer_id' => Select::make($name)
                ->label($label)
                ->relationship('customer', 'name', fn (Builder $query): Builder => $query->orderByDesc('id'))
                ->searchable(['name', 'short_name', 'name_kana'])
                ->preload()
                ->placeholder('-----')
                ->live()
                // legacy lookup: 自社担当者 → 担当者
                ->afterStateUpdated(fn (Get $get, Set $set) => $set('assignees', $customer($get)?->staff()->pluck('users.id')
                    ->map(fn (mixed $id): string => (string) $id)->all() ?? [])),
            $name === 'assignees' => Select::make($name)->label($label)->placeholder('-----')->relationship('assignees', 'name')->multiple()->searchable()->preload(),
            $name === 'quote_number' => TextInput::make($name)->label($label)->maxLength(64)->unique(ignoreRecord: true),
            $name === 'size' => TextInput::make($name)->label($label)->maxLength(255)->default(Quotation::DEFAULT_SIZE),
            in_array($name, self::TEXTS, true) => TextInput::make($name)->label($label)->maxLength(255),
            $name === 'total' => $text(fn (Get $get): string => AppFormat::yen(array_sum(array_map(
                fn (mixed $row): int => is_array($row) ? QuotationLine::amountOf($row['unit_price'] ?? null, $row['sheets'] ?? null) : 0,
                array_values((array) $get('lines')),
            )))),
            $name === 'lines' => self::linesForm(),
            $name === 'notes' => Textarea::make($name)->label($label)->rows(4)->maxLength(4000),
            $name === 'pdf_files' => FileUpload::make($name)->label($label)->multiple()->disk('local')->directory('orders/quotations')->visibility('private')
                ->acceptedFileTypes(['application/pdf'])->maxSize(20480)->downloadable(),
            $name === 'customer_code' => $text(fn (Get $get): ?string => $customer($get)?->code),
            $name === 'file_name' => $text(fn (Get $get): string => $get('quote_number').'_'.$customer($get)?->name),
            $name === 'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');

        return match ($name) {
            'quoted_on' => $entry->date('Y-m-d'),
            'customer_id' => $entry->state(fn (Quotation $record): ?string => $record->customer?->name),
            // user names open the ユーザー情報 popup
            'assignees' => $entry->state(fn (Quotation $record): HtmlString => UserPopup::list($record->assignees))->html(),
            'total' => $entry->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            'lines' => self::linesEntry(),
            'pdf_files' => $entry->state(fn (Quotation $record): array => array_map('basename', (array) $record->pdf_files))->listWithLineBreaks(),
            'customer_code' => $entry->state(fn (Quotation $record): ?string => $record->customer?->code),
            'file_name' => $entry->state(fn (Quotation $record): string => $record->fileName()),
            'creator', 'updater' => $entry->state(fn (Quotation $record): HtmlString => UserPopup::html(
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
        $number = fn (string $field): TextInput => TextInput::make($field)->hiddenLabel()->numeric()->step('any')->rule('decimal:0,4')
            ->minValue(-999999999999)->maxValue(999999999999)->live(onBlur: true);

        return RecordTable::form(
            Repeater::make('lines')->label(self::label('lines'))->relationship('lines')->orderColumn('sort'),
            self::lineHeaders(),
        )->schema([
            $number('sheets')->suffix('枚'),
            $number('unit_price')->prefix('¥'),
            TextEntry::make('amount')->hiddenLabel()->placeholder('')->alignEnd()
                ->state(fn (Get $get): string => AppFormat::yen(QuotationLine::amountOf($get('unit_price'), $get('sheets')))),
        ]);
    }

    private static function linesEntry(): RepeatableEntry
    {
        return RecordTable::entry(
            RepeatableEntry::make('lines')->label(self::label('lines'))->placeholder(''),
            self::lineHeaders(),
        )->schema([
            TextEntry::make('sheets')->placeholder('')->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::number($state)),
            TextEntry::make('unit_price')->placeholder('')->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::price($state)),
            TextEntry::make('amount')->placeholder('')->alignEnd()->state(fn (QuotationLine $record): string => AppFormat::yen($record->amount())),
        ]);
    }

    /** @return list<array{0: string, 1: int}> */
    private static function lineHeaders(): array
    {
        return array_map(fn (string $key, int $width): array => [self::label($key), $width], array_keys(self::LINE_COLUMNS), self::LINE_COLUMNS);
    }
}
