<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\AccidentReports;

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
use Illuminate\Support\HtmlString;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Records\RecordTable;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\UserPopup;
use Modules\MasterData\Models\Machine;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Models\Order;
use Modules\Production\Domain\ProductionChoices;
use Modules\Production\Domain\ProductionFields;
use Modules\Production\Models\AccidentDamage;
use Modules\Production\Models\AccidentReport;

/**
 * Form and record page of 14. 事故報告管理 (SYSTEM_BRAIN APP_14 fields.md "Form layout", widths from the template): 事故発生日,
 * 報告者氏名 (employees 在籍中), 注番 with the job's 得意先名 / 品名 shown, 数量, 担当機械, 製造者1 / 2, 営業関係者, the four
 * texts, 備考, the table 損害 (小計 = 原価 × 数量) and 合計損害額.
 */
final class AccidentReportLayout
{
    private const MAIN = [
        [['occurred_on', 117], ['reporter_id', 288], ['order_id', 246]],
        [['customer', 405], ['product_name', 405], ['quantity', 146]],
        [['machine_id', 405], ['maker1_id', 288], ['maker2_id', 288], ['sales_users', 344]],
        [['description', 651], ['cause', 651]],
        [['response', 651], ['prevention', 651]],
        [['notes', 651]],
        [['damages', 1000]],
        [['total_damage', 193]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [[['machine_record', 193], ['reporter_code', 193], ['customer_code', 193], ['legacy_id', 193]]];

    /** 損害 columns => width (legacy template). */
    private const DAMAGE_COLUMNS = ['item' => 196, 'sheets' => 193, 'cost' => 193, 'damage_quantity' => 193, 'subtotal' => 193];

    private const TEXTS = ['description', 'cause', 'response', 'prevention', 'notes'];

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
        return ProductionFields::ACCIDENT[$attribute];
    }

    private static function input(string $name): ?Component
    {
        $label = self::label($name);
        $order = fn (Get $get): ?Order => is_numeric($get('order_id')) ? Order::query()->with('customer')->find((int) $get('order_id')) : null;
        $text = fn (callable $state): TextEntry => TextEntry::make($name)->label($label)->placeholder('')->state($state);
        $employees = fn (string $relation, ?string $column): \Closure => fn (Builder $query, ?AccidentReport $record): Builder => $query
            ->where(fn (Builder $q): Builder => $q->where('employment_status', EmploymentStatus::Active->value)
                ->when($column !== null && $record?->getAttribute($column) !== null, fn (Builder $own): Builder => $own->orWhereKey($record?->getAttribute((string) $column))))
            ->orderByDesc('id');

        return match (true) {
            $name === 'occurred_on' => DatePicker::make($name)->label($label)->required()->default(fn (): string => now()->toDateString()),
            in_array($name, ['reporter_id', 'maker1_id', 'maker2_id'], true) => Select::make($name)
                ->label($label)
                ->relationship(substr($name, 0, -3), 'name', $employees($name, $name))
                ->searchable()
                ->preload()
                ->placeholder('-----')
                ->required($name === 'reporter_id'),
            $name === 'order_id' => Select::make($name)
                ->label($label)
                ->relationship('order', 'order_number', fn (Builder $query): Builder => $query->orderByDesc('id'))
                ->searchable(['order_number', 'product_name'])
                ->preload()
                ->optionsLimit(100)
                ->placeholder('-----')
                ->live(),
            $name === 'customer' => $text(fn (Get $get): ?string => $order($get)?->customer?->name),
            $name === 'product_name' => $text(fn (Get $get): ?string => $order($get)?->product_name),
            $name === 'quantity' => TextInput::make($name)->label($label)->numeric()->step('any')->rule('decimal:0,4')->minValue(-999999999999)->maxValue(999999999999),
            $name === 'machine_id' => Select::make($name)
                ->label($label)
                ->relationship('machine', 'name', fn (Builder $query): Builder => $query->orderByDesc('id'))
                ->searchable()
                ->preload()
                ->placeholder('-----')
                ->required(),
            $name === 'sales_users' => Select::make($name)->label($label)->placeholder('-----')->relationship('salesUsers', 'name')->multiple()->searchable()->preload(),
            in_array($name, self::TEXTS, true) => Textarea::make($name)->label($label)->rows(4)->maxLength(4000),
            $name === 'damages' => self::damagesForm(),
            $name === 'total_damage' => $text(fn (Get $get): string => AppFormat::yen(array_sum(array_map(
                fn (mixed $row): int => is_array($row) ? AccidentDamage::subtotalOf($row['cost'] ?? null, $row['quantity'] ?? null) : 0,
                array_values((array) $get('damages')),
            )))),
            $name === 'machine_record' => $text(fn (Get $get): ?string => is_numeric($get('machine_id')) ? (string) $get('machine_id') : null),
            $name === 'reporter_code' => $text(fn (Get $get): ?string => is_numeric($get('reporter_id')) ? User::query()->whereKey((int) $get('reporter_id'))->value('employee_code') : null),
            $name === 'customer_code' => $text(fn (Get $get): ?string => $order($get)?->customer?->code),
            $name === 'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');
        $user = fn (string $relation): \Closure => fn (AccidentReport $record): HtmlString => UserPopup::html(
            $record->getRelationValue($relation) instanceof User ? $record->getRelationValue($relation) : null,
        );

        return match (true) {
            $name === 'occurred_on' => $entry->date('Y-m-d'),
            // user names open the ユーザー情報 popup
            $name === 'reporter_id' => $entry->state($user('reporter'))->html(),
            $name === 'maker1_id' => $entry->state($user('maker1'))->html(),
            $name === 'maker2_id' => $entry->state($user('maker2'))->html(),
            $name === 'sales_users' => $entry->state(fn (AccidentReport $record): HtmlString => UserPopup::list($record->salesUsers))->html(),
            $name === 'order_id' => $entry
                ->state(fn (AccidentReport $record): ?string => $record->order?->order_number)
                ->url(fn (AccidentReport $record): ?string => $record->order !== null && OrderResource::canView($record->order)
                    ? OrderResource::getUrl('view', ['record' => $record->order]) : null),
            $name === 'customer' => $entry->state(fn (AccidentReport $record): ?string => $record->order?->customer?->name),
            $name === 'product_name' => $entry->state(fn (AccidentReport $record): ?string => $record->order?->product_name),
            $name === 'quantity' => $entry->formatStateUsing(fn (mixed $state): string => AppFormat::number($state)),
            $name === 'machine_id' => $entry->state(fn (AccidentReport $record): ?string => $record->machine?->name),
            $name === 'damages' => self::damagesEntry(),
            $name === 'total_damage' => $entry->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            $name === 'machine_record' => $entry->state(fn (AccidentReport $record): ?string => $record->machine_id === null ? null : (string) $record->machine_id),
            $name === 'reporter_code' => $entry->state(fn (AccidentReport $record): ?string => $record->reporter->getAttribute('employee_code')),
            $name === 'customer_code' => $entry->state(fn (AccidentReport $record): ?string => $record->order?->customer?->code),
            $name === 'creator', $name === 'updater' => $entry->state($user($name))->html(),
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

    private static function damagesForm(): Repeater
    {
        $number = fn (string $field): TextInput => TextInput::make($field)->hiddenLabel()->numeric()->step('any')->rule('decimal:0,4')
            ->minValue(-999999999999)->maxValue(999999999999)->live(onBlur: true);

        return RecordTable::form(
            Repeater::make('damages')->label(self::label('damages'))->relationship('damages')->orderColumn('sort'),
            self::damageHeaders(),
        )->schema([
            Select::make('item')->hiddenLabel()->native()->placeholder('-----')->options(ProductionChoices::options(ProductionChoices::DAMAGE_ITEMS)),
            $number('sheets')->suffix('枚'),
            $number('cost')->prefix('@'),
            $number('quantity'),
            TextEntry::make('subtotal')->hiddenLabel()->placeholder('')->alignEnd()
                ->state(fn (Get $get): string => AppFormat::yen(AccidentDamage::subtotalOf($get('cost'), $get('quantity')))),
        ]);
    }

    private static function damagesEntry(): RepeatableEntry
    {
        $number = fn (string $field): TextEntry => TextEntry::make($field)->placeholder('')->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::number($state));

        return RecordTable::entry(
            RepeatableEntry::make('damages')->label(self::label('damages'))->placeholder(''),
            self::damageHeaders(),
        )->schema([
            TextEntry::make('item')->placeholder(''),
            $number('sheets'),
            TextEntry::make('cost')->placeholder('')->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::price($state)),
            $number('quantity'),
            TextEntry::make('subtotal')->placeholder('')->alignEnd()
                ->state(fn (AccidentDamage $record): string => AppFormat::yen($record->subtotal())),
        ]);
    }

    /** @return list<array{0: string, 1: int}> */
    private static function damageHeaders(): array
    {
        return array_map(fn (string $key, int $width): array => [self::label($key), $width], array_keys(self::DAMAGE_COLUMNS), self::DAMAGE_COLUMNS);
    }

    /**
     * 担当機械 of the machine master, for the list filter.
     *
     * @return array<string, string>
     */
    public static function machineOptions(): array
    {
        return Machine::query()->orderByDesc('id')->pluck('name', 'id')->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all();
    }
}
