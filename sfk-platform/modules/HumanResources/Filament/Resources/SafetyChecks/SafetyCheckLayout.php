<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\SafetyChecks;

use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
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
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Records\RecordTable;
use Modules\Core\Filament\Support\AppFormat;
use Modules\HumanResources\Domain\HrChoices;
use Modules\HumanResources\Domain\HrFields;
use Modules\HumanResources\Domain\SafetyChecks\ChecklistTemplate;
use Modules\HumanResources\Filament\Support\Employees;
use Modules\HumanResources\Models\SafetyCheckItem;

/**
 * Form and record page of 17. 安全衛生チェック (fields of SYSTEM_BRAIN APP_17 fields.md), on a 12-column grid of the 1,500 px
 * canvas: 基本情報 (日付, 名前, 従業員名; 部署, 部署・機械), the legacy labels on who fills which items, and the table チェックリスト — No, 項目,
 * 評価 (はい / いいえ) and チェック (required once a row is filled in), 備考. A new checklist starts with the items of its
 * 部署 (ChecklistTemplate, BR-HR-05: 営業 1–6 / 14 / 15 / 19 and driving 20–25, the others 1–19); a typed No brings its
 * item.
 */
final class SafetyCheckLayout
{
    private const MAIN = [
        '基本情報',
        [['checked_on', 250], ['user_id', 625], ['employee_id', 625]],
        [['department', 750], ['department_machine', 750]],
        '※営業は1～6・14・15・19を記入してください',
        '※運転をしてる方は20～25を記入してください',
        [['items', 1500]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [[['legacy_id', 193]]];

    /** チェックリスト columns => width (legacy template; No wide enough for two digits at 16px). */
    private const ITEM_COLUMNS = ['number' => 80, 'item' => 542, 'answer' => 209, 'checked' => 97, 'notes' => 315];

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
        return HrFields::SAFETY[$attribute];
    }

    private static function input(string $name): ?Component
    {
        $label = self::label($name);

        return match ($name) {
            'department_machine' => TextInput::make($name)->label($label)->maxLength(255),
            'department' => Select::make($name)->label($label)->placeholder('-----')->options(HrChoices::options(HrChoices::DEPARTMENTS))->native(false)->required()
                ->live()
                // a new checklist takes the items of the department while none is checked yet (BR-HR-05)
                ->afterStateUpdated(function (mixed $state, Get $get, Set $set, ?Model $record): void {
                    $answered = collect((array) $get('items'))->contains(fn (mixed $row): bool => is_array($row) && ($row['checked'] ?? []) !== []);
                    if ($record === null && ! $answered) {
                        $set('items', self::templateRows(is_string($state) ? $state : null));
                    }
                }),
            'user_id' => Employees::user($name, 'user', $label)->required()->default(fn (): ?int => auth()->id() === null ? null : (int) auth()->id()),
            'employee_id' => Employees::employee($name, 'employee', $label),
            'checked_on' => DatePicker::make($name)->label($label)->required()->default(fn (): string => now()->toDateString()),
            'items' => self::itemsForm(),
            'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');

        return match ($name) {
            // user names open the ユーザー情報 popup
            'user_id' => $entry->state(Employees::popup('user'))->html(),
            'employee_id' => $entry->state(Employees::popup('employee'))->html(),
            'checked_on' => $entry->date('Y-m-d'),
            'items' => self::itemsEntry(),
            'creator', 'updater' => $entry->state(Employees::popup($name))->html(),
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

    private static function itemsForm(): Repeater
    {
        $filled = fn (Get $get): bool => filled($get('number')) || filled($get('item')) || filled($get('notes'));

        return RecordTable::form(
            Repeater::make('items')->label(self::label('items'))->relationship('items')->orderColumn('sort'),
            self::headers(),
            ['answer' => HrChoices::ANSWERS[0]],
        )->schema([
            TextInput::make('number')->hiddenLabel()->numeric()->step('any')->minValue(0)->maxValue(9999)->live(onBlur: true)
                // a typed No brings its item (e.g. drivers adding 20–25)
                ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                    $item = is_numeric($state) ? ChecklistTemplate::item((int) $state) : null;
                    if ($item !== null && blank($get('item'))) {
                        $set('item', $item);
                    }
                }),
            Textarea::make('item')->hiddenLabel()->rows(2)->maxLength(4000),
            Radio::make('answer')->hiddenLabel()->inline()->options(HrChoices::options(HrChoices::ANSWERS))->default(HrChoices::ANSWERS[0])->required($filled),
            CheckboxList::make('checked')->hiddenLabel()->options([HrChoices::CHECK => HrChoices::CHECK])
                ->formatStateUsing(fn (mixed $state): array => $state === true || $state === 1 || $state === '1' ? [HrChoices::CHECK] : (is_array($state) ? $state : []))
                ->dehydrateStateUsing(fn (mixed $state): bool => in_array(HrChoices::CHECK, (array) $state, true))
                ->required($filled),
            Textarea::make('notes')->hiddenLabel()->rows(2)->maxLength(4000),
        ]);
    }

    private static function itemsEntry(): RepeatableEntry
    {
        return RecordTable::entry(
            RepeatableEntry::make('items')->label(self::label('items'))->placeholder(''),
            self::headers(),
        )->schema([
            TextEntry::make('number')->placeholder('')->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::number($state)),
            TextEntry::make('item')->placeholder(''),
            TextEntry::make('answer')->placeholder(''),
            TextEntry::make('checked')->placeholder('')->state(fn (SafetyCheckItem $record): ?string => $record->checked ? HrChoices::CHECK : null),
            TextEntry::make('notes')->placeholder(''),
        ]);
    }

    /**
     * The rows of a new checklist: the items of the department (ChecklistTemplate), unanswered.
     *
     * @return list<array{number: int, item: string, answer: string, checked: list<string>, notes: null}>
     */
    public static function templateRows(?string $department): array
    {
        return array_map(fn (int $number): array => [
            'number' => $number,
            'item' => (string) ChecklistTemplate::item($number),
            'answer' => HrChoices::ANSWERS[0],
            'checked' => [],
            'notes' => null,
        ], ChecklistTemplate::numbersFor($department));
    }

    /** @return list<array{0: string, 1: int}> */
    private static function headers(): array
    {
        return array_map(fn (string $key, int $width): array => [self::label($key), $width], array_keys(self::ITEM_COLUMNS), self::ITEM_COLUMNS);
    }
}
