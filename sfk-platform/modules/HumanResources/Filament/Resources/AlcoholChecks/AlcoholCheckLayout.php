<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\AlcoholChecks;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Records\RecordSections;
use Modules\HumanResources\Domain\HrChoices;
use Modules\HumanResources\Domain\HrFields;
use Modules\HumanResources\Filament\Support\Employees;

/**
 * Form and record page of 18. アルコールチェック (fields of SYSTEM_BRAIN APP_18 fields.md), on a 12-column grid of the 1,500 px
 * canvas: 基本情報 (検査日, 氏名, 従業員名), then 運転前 — 検査時刻 (now to start with), 車両番号, 確認方法, 酒気帯び運転の有無,
 * 確認者, 指示項目 (the legacy default text), その他必要な項目 — and 運転後 with the same checks in the same places.
 */
final class AlcoholCheckLayout
{
    private const MAIN = [
        '基本情報',
        [['checked_on', 250], ['driver_id', 625], ['employee_id', 625]],
        '運転前',
        [['before_time', 250], ['before_vehicle', 250], ['before_method', 250], ['before_detected', 375], ['before_checker_id', 375]],
        [['before_instruction', 750], ['before_notes', 750]],
        '運転後',
        [['after_time', 250], ['after_vehicle', 250], ['after_method', 250], ['after_detected', 375], ['after_checker_id', 375]],
        [['after_instruction', 750], ['after_notes', 750]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [[['legacy_id', 193]]];

    /** Users of the record: attribute => relation. */
    public const USERS = ['driver_id' => 'driver', 'employee_id' => 'employee', 'before_checker_id' => 'beforeChecker', 'after_checker_id' => 'afterChecker'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::components(self::input(...), withFooter: false));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::components(self::entry(...), withFooter: true));
    }

    /** @param  bool  $qualified  with 「(運転前)」 / 「(運転後)」 (lists, 絞り込む) */
    public static function label(string $attribute, bool $qualified = false): string
    {
        return HrFields::alcohol($attribute, $qualified);
    }

    private static function input(string $name): ?Component
    {
        $label = self::label($name);
        $block = str_starts_with($name, 'after_') ? 'after' : 'before';
        $select = fn (array $choices): Select => Select::make($name)->label($label)->placeholder('-----')->options(HrChoices::options($choices))->native(false);

        return match (true) {
            $name === 'checked_on' => DatePicker::make($name)->label($label)->required()->default(fn (): string => now()->toDateString()),
            str_ends_with($name, '_time') => TimePicker::make($name)->label($label)->native()->seconds(false)->default(fn (): string => now()->format('H:i')),
            $name === 'driver_id' => Employees::user($name, 'driver', $label)->required()->default(fn (): ?int => auth()->id() === null ? null : (int) auth()->id()),
            $name === 'employee_id' => Employees::employee($name, 'employee', $label),
            str_ends_with($name, '_checker_id') => Employees::user($name, self::USERS[$name], $label),
            str_ends_with($name, '_vehicle') => $select(HrChoices::VEHICLES),
            str_ends_with($name, '_method') => $select(HrChoices::METHODS),
            str_ends_with($name, '_detected') => $select(HrChoices::DETECTED),
            str_ends_with($name, '_instruction') => TextInput::make($name)->label($label)->maxLength(255)
                ->default($block === 'before' ? HrChoices::BEFORE_INSTRUCTION : HrChoices::AFTER_INSTRUCTION),
            str_ends_with($name, '_notes') => Textarea::make($name)->label($label)->rows(3)->maxLength(4000),
            $name === 'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');

        return match (true) {
            $name === 'checked_on' => $entry->date('Y-m-d'),
            str_ends_with($name, '_time') => $entry->formatStateUsing(fn (mixed $state): string => is_string($state) ? substr($state, 0, 5) : ''),
            // user names open the ユーザー情報 popup
            isset(self::USERS[$name]) => $entry->state(Employees::popup(self::USERS[$name]))->html(),
            $name === 'creator', $name === 'updater' => $entry->state(Employees::popup($name))->html(),
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
