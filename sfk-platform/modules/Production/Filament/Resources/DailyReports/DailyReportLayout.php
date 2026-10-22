<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\DailyReports;

use App\Models\User;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Modules\Core\Access\EmployeeDataAccess;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\UserPopup;
use Modules\MasterData\Domain\Consumables\ConsumableKind;
use Modules\MasterData\Models\Consumable;
use Modules\MasterData\Models\Machine;
use Modules\Orders\Domain\Orders\OrderChoices;
use Modules\Orders\Models\Order;
use Modules\Production\Application\DailyReports\BoxUsage;
use Modules\Production\Domain\DailyReports\TaskCosting;
use Modules\Production\Domain\ProductionChoices;
use Modules\Production\Domain\ProductionFields;
use Modules\Production\Models\DailyReport;

/**
 * Form and record page of 08. 日報入力 (fields of SYSTEM_BRAIN APP_08 fields.md), one record per task, grouped by task on
 * a 12-column grid of the 1,500 px canvas: 作業情報 (作業日, 作業者 — employees 在籍中, newest first — 機械名, 日種別), 注番情報,
 * 作業時間・出来高 (with 型変 and the overtime), 使用資材 (ダンボール, 仕切, 糊), 品質・ステータス, その他 and 原価. The job's 品名 / 受注数 / 製造済数 / 製造残数 / 現在のステータス and the boxes' 在庫数 are shown from
 * the masters; the figures follow TaskCosting (BR-PR-01..04). The money figures (時給計算 … 社内原価, 残業時給計算) and
 * 開発者用フィールド are shown to administrators only, like the cost data of the other production screens.
 */
final class DailyReportLayout
{
    private const MAIN = [
        '作業情報',
        [['worked_on', 250], ['user_id', 500], ['machine_id', 500], ['day_type', 250]],
        '注番情報',
        [['order_id', 375], ['product_name', 1125]],
        [['order_quantity', 500], ['manufactured_quantity', 500], ['manufacturing_remaining', 500]],
        '作業時間・出来高',
        [['started_at', 250], ['ended_at', 250], ['break_time', 250], ['produced', 250], ['crew', 250], ['per_person', 250]],
        [['changeover_minutes', 250], ['changeover_count', 250], ['overtime_started_at', 375], ['overtime_ended_at', 375], ['overtime_break', 250]],
        '使用資材',
        [['cardboard_id', 750], ['cardboard_stock', 375], ['cardboard_used', 375]],
        [['partition_id', 750], ['partition_stock', 375], ['partition_used', 375]],
        [['glue', 375], ['glue_cost', 375]],
        '品質・ステータス',
        [['quality_checks', 375], ['difficulty', 375], ['gloves', 250], ['damaged_before', 250], ['damaged_after', 250]],
        [['current_status', 375], ['update_status', 375], ['new_status', 375]],
        'その他',
        [['notes', 1500]],
        '原価',
        [['work_hours', 250], ['labor_cost', 250], ['machine_fee', 250], ['sheet_fee', 250], ['changeover_fee', 250], ['internal_cost', 250]],
        [['overtime_hours', 250], ['overtime_labor_cost', 250]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [
        [['worker_code', 193], ['employee_code', 193], ['sales_staff_code', 193], ['department_code', 193], ['work_rate', 193], ['overtime_rate', 193]],
        [['machine_code', 193], ['processing_rate', 193], ['changeover_rate', 193], ['handwork_rate', 193], ['machine_rate', 193], ['printing_rate', 193],
            ['plate_change_rate', 193], ['ink_mixing_rate', 193]],
        [['cardboard_cost', 193], ['cardboard_stock_after', 193], ['partition_cost', 193], ['partition_stock_after', 193]],
        [['manufacturing_sales', 193], ['sheet_and_changeover_fee', 193]],
    ];

    /** The money figures of the main card, administrators only. */
    private const COST_FIGURES = ['labor_cost', 'machine_fee', 'sheet_fee', 'changeover_fee', 'internal_cost', 'overtime_labor_cost'];

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
        return ProductionFields::DAILY_REPORT[$attribute];
    }

    /** Cost data (作業単価, 社内原価 …): administrators only. */
    public static function showsCosts(): bool
    {
        $user = auth()->user();

        return EmployeeDataAccess::isAdmin($user instanceof User ? $user : null);
    }

    private static function input(string $name): ?Component
    {
        if (in_array($name, self::COST_FIGURES, true) && ! self::showsCosts()) {
            return null;
        }

        $label = self::label($name);
        $worker = fn (Get $get): ?User => is_numeric($get('user_id')) ? User::query()->find((int) $get('user_id')) : null;
        $order = fn (Get $get): ?Order => is_numeric($get('order_id')) ? Order::query()->find((int) $get('order_id')) : null;
        $machine = fn (Get $get): ?Machine => is_numeric($get('machine_id')) ? Machine::query()->find((int) $get('machine_id')) : null;
        $shown = fn (Closure $state): TextEntry => TextEntry::make($name)->label($label)->placeholder('')->state($state);
        $number = fn (): TextInput => TextInput::make($name)->label($label)->numeric()->step('any')->rule('decimal:0,4')
            ->minValue(-999999999999)->maxValue(999999999999)->live(onBlur: true);
        $time = fn (string $default): TimePicker => TimePicker::make($name)->label($label)->native()->seconds(false)->default($default)->live(onBlur: true);
        $boxes = fn (Builder $query): Builder => $query->where('kind', ConsumableKind::Cardboard->value)->orderByDesc('name');
        // the record's copy while its worker / machine / box is unchanged, else the master's value (legacy lookup copies)
        $copy = fn (string $column, string $key, Closure $master): Closure => fn (Get $get, ?Model $record): ?string => $record instanceof DailyReport
            && $record->getAttribute($key) !== null && (int) $record->getAttribute($key) === (int) $get($key)
                ? self::decimal($record->getAttribute($column))
                : self::decimal($master($get));
        $rate = fn (string $column): Closure => $copy($column, 'machine_id', fn (Get $get): mixed => $machine($get)?->getAttribute($column));
        $workRate = $copy('work_rate', 'user_id', fn (Get $get): mixed => $worker($get)?->work_rate);
        $overtimeRate = $copy('overtime_rate', 'user_id', fn (Get $get): mixed => $worker($get)?->overtime_rate);
        $cardboardCost = $copy('cardboard_cost', 'cardboard_id', fn (Get $get): ?string => self::unitPrice($get('cardboard_id')));
        $partitionCost = $copy('partition_cost', 'partition_id', fn (Get $get): ?string => self::unitPrice($get('partition_id')));
        $hours = fn (Get $get): ?string => TaskCosting::workHours(self::time($get('started_at')), self::time($get('ended_at')), self::time($get('break_time')));
        $overtime = fn (Get $get): ?string => TaskCosting::workHours(self::time($get('overtime_started_at')), self::time($get('overtime_ended_at')),
            self::time($get('overtime_break')));
        $stock = fn (string $key): Closure => fn (Get $get): ?string => BoxUsage::stockOf(is_numeric($get($key)) ? (int) $get($key) : null);
        $sheetFee = fn (Get $get, ?Model $record): string => TaskCosting::product(self::decimal($get('produced')), $rate('processing_rate')($get, $record));
        $changeoverFee = fn (Get $get, ?Model $record): string => TaskCosting::product($rate('changeover_rate')($get, $record), self::decimal($get('changeover_count')));

        return match ($name) {
            'worked_on' => DatePicker::make($name)->label($label)->required()->default(fn (): string => now()->toDateString()),
            'user_id' => Select::make($name)
                ->label($label)
                ->relationship('worker', 'name', fn (Builder $query, ?DailyReport $record): Builder => $query
                    ->where(fn (Builder $q): Builder => $q->where('employment_status', EmploymentStatus::Active->value)
                        ->when($record !== null, fn (Builder $own): Builder => $own->orWhereKey($record?->user_id)))
                    ->orderByDesc('id'))
                ->searchable()
                ->preload()
                ->placeholder('-----')
                ->required()
                ->live(),
            'order_id' => Select::make($name)->label($label)->placeholder('-----')
                ->relationship('order', 'order_number', fn (Builder $query): Builder => $query->orderByDesc('id'))->searchable()->preload()->live(),
            'product_name' => $shown(fn (Get $get): ?string => $order($get)?->product_name),
            'order_quantity' => $shown(fn (Get $get): string => AppFormat::number($order($get)?->quantity)),
            'manufactured_quantity' => $shown(fn (Get $get): string => AppFormat::number($order($get)?->getAttribute('manufactured_quantity'))),
            'manufacturing_remaining' => $shown(fn (Get $get): string => AppFormat::number($order($get)?->getAttribute('manufacturing_remaining'))),
            'machine_id' => Select::make($name)->label($label)->placeholder('-----')
                ->relationship('machine', 'name', fn (Builder $query): Builder => $query->orderByDesc('id'))->searchable()->preload()->live()->required(),
            'cardboard_id' => Select::make($name)->label($label)->placeholder('-----')->relationship('cardboard', 'name', $boxes)->searchable()->preload()->live(),
            'partition_id' => Select::make($name)->label($label)->placeholder('-----')->relationship('partition', 'name', $boxes)->searchable()->preload()->live(),
            'cardboard_stock' => $shown(fn (Get $get): string => AppFormat::number($stock('cardboard_id')($get))),
            'partition_stock' => $shown(fn (Get $get): string => AppFormat::number($stock('partition_id')($get))),
            'cardboard_used', 'partition_used', 'glue_cost', 'changeover_minutes', 'changeover_count' => $number(),
            'glue' => Select::make($name)->label($label)->placeholder('-----')->options(ProductionChoices::options(ProductionChoices::GLUES))->native(false),
            'started_at', 'ended_at' => $time(ProductionChoices::DEFAULT_TIME)->required(),
            'break_time' => $time(ProductionChoices::DEFAULT_BREAK),
            'overtime_started_at', 'overtime_ended_at', 'overtime_break' => $time(ProductionChoices::DEFAULT_TIME),
            'produced', 'damaged_before' => $number()->required(),
            'damaged_after' => $number()->minValue(0)->required(),
            'crew' => $number()->minValue(1)->required(),
            'per_person' => $shown(fn (Get $get): string => AppFormat::number(TaskCosting::perPerson(self::decimal($get('produced')), self::decimal($get('crew'))))),
            'day_type' => Radio::make($name)->label($label)->inline()->options(ProductionChoices::options(ProductionChoices::DAY_TYPES))
                ->default(ProductionChoices::DAY_TYPES[0])->required(),
            'quality_checks' => CheckboxList::make($name)->label($label)->options(ProductionChoices::options(ProductionChoices::QUALITY_CHECKS))->columns(3)->required(),
            'gloves' => self::flag($name, ProductionChoices::GLOVES),
            'current_status' => $shown(fn (Get $get): ?string => $order($get)?->status),
            'update_status' => self::flag($name, ProductionChoices::UPDATE_STATUS),
            'new_status' => Select::make($name)->label($label)->placeholder('-----')->options(OrderChoices::options(OrderChoices::STATUSES))->native(false),
            'difficulty' => Select::make($name)->label($label)->placeholder('-----')->options(ProductionChoices::options(ProductionChoices::DIFFICULTIES))->native(false),
            'notes' => Textarea::make($name)->label($label)->rows(3)->maxLength(4000),
            'work_hours' => $shown(fn (Get $get): string => self::hoursText($hours($get))),
            'overtime_hours' => $shown(fn (Get $get): string => self::hoursText($overtime($get))),
            'labor_cost' => $shown(fn (Get $get, ?Model $record): string => AppFormat::price(TaskCosting::product($workRate($get, $record), $hours($get)))),
            'overtime_labor_cost' => $shown(fn (Get $get, ?Model $record): string => AppFormat::price(TaskCosting::product($overtime($get), $overtimeRate($get, $record)))),
            'machine_fee' => $shown(fn (Get $get, ?Model $record): string => AppFormat::price(TaskCosting::product($rate('machine_rate')($get, $record), $hours($get)))),
            'sheet_fee' => $shown(fn (Get $get, ?Model $record): string => AppFormat::price($sheetFee($get, $record))),
            'changeover_fee' => $shown(fn (Get $get, ?Model $record): string => AppFormat::price($changeoverFee($get, $record))),
            'internal_cost' => $shown(fn (Get $get, ?Model $record): string => AppFormat::yen(TaskCosting::internalCost(
                $workRate($get, $record), $rate('machine_rate')($get, $record), $hours($get), $overtimeRate($get, $record), $overtime($get),
                self::decimal($get('glue_cost')), $cardboardCost($get, $record), $partitionCost($get, $record),
            ))),
            'worker_code', 'employee_code', 'sales_staff_code', 'department_code' => $shown(fn (Get $get): ?string => self::text($worker($get)?->getAttribute($name))),
            'work_rate' => $shown(fn (Get $get, ?Model $record): string => AppFormat::price($workRate($get, $record))),
            'overtime_rate' => $shown(fn (Get $get, ?Model $record): string => AppFormat::price($overtimeRate($get, $record))),
            'machine_code' => $shown(fn (Get $get): ?string => $machine($get)?->code),
            'processing_rate', 'changeover_rate', 'handwork_rate', 'machine_rate', 'printing_rate', 'plate_change_rate', 'ink_mixing_rate' => $shown(
                fn (Get $get, ?Model $record): string => AppFormat::price($rate($name)($get, $record)),
            ),
            'cardboard_cost' => $shown(fn (Get $get, ?Model $record): string => AppFormat::price($cardboardCost($get, $record))),
            'partition_cost' => $shown(fn (Get $get, ?Model $record): string => AppFormat::price($partitionCost($get, $record))),
            'cardboard_stock_after' => $shown(fn (Get $get): string => AppFormat::number(self::stockAfter($stock('cardboard_id')($get), $get('cardboard_used')))),
            'partition_stock_after' => $shown(fn (Get $get): string => AppFormat::number(self::stockAfter($stock('partition_id')($get), $get('partition_used')))),
            'manufacturing_sales' => $shown(fn (Get $get, ?Model $record): string => AppFormat::yen(TaskCosting::manufacturingSales(
                $workRate($get, $record), $rate('machine_rate')($get, $record), $hours($get), self::decimal($get('produced')),
            ))),
            'sheet_and_changeover_fee' => $shown(fn (Get $get, ?Model $record): string => AppFormat::price(bcadd($sheetFee($get, $record), $changeoverFee($get, $record), 4))),
            default => null,
        };
    }

    private static function entry(string $name): ?Component
    {
        if (in_array($name, self::COST_FIGURES, true) && ! self::showsCosts()) {
            return null;
        }

        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');
        $state = fn (Closure $value): TextEntry => $entry->state(fn (DailyReport $record): mixed => $value($record));
        $number = fn (Closure $value): TextEntry => $entry->state(fn (DailyReport $record): string => AppFormat::number($value($record)));
        $price = fn (Closure $value): TextEntry => $entry->state(fn (DailyReport $record): string => AppFormat::price($value($record)));
        $rate = fn (DailyReport $record, string $column): ?string => self::decimal($record->getAttribute($column));
        $sheetFee = fn (DailyReport $record): string => TaskCosting::product($record->produced, $record->processing_rate);
        $changeoverFee = fn (DailyReport $record): string => TaskCosting::product($record->changeover_rate, $record->changeover_count);

        return match ($name) {
            'worked_on' => $entry->date('Y-m-d'),
            // user names open the ユーザー情報 popup
            'user_id' => $entry->state(fn (DailyReport $record): HtmlString => UserPopup::html($record->worker))->html(),
            'order_id' => $state(fn (DailyReport $record): ?string => $record->order?->order_number),
            'product_name' => $state(fn (DailyReport $record): ?string => $record->order?->product_name),
            'order_quantity' => $number(fn (DailyReport $record): mixed => $record->order?->quantity),
            'manufactured_quantity' => $number(fn (DailyReport $record): mixed => $record->order?->getAttribute('manufactured_quantity')),
            'manufacturing_remaining' => $number(fn (DailyReport $record): mixed => $record->order?->getAttribute('manufacturing_remaining')),
            'current_status' => $state(fn (DailyReport $record): ?string => $record->order?->status),
            'machine_id' => $state(fn (DailyReport $record): ?string => $record->machine?->name),
            'machine_code' => $state(fn (DailyReport $record): ?string => $record->machine?->code),
            'cardboard_id' => $state(fn (DailyReport $record): ?string => $record->cardboard?->name),
            'partition_id' => $state(fn (DailyReport $record): ?string => $record->partition?->name),
            'cardboard_stock' => $number(fn (DailyReport $record): ?string => BoxUsage::stockOf($record->cardboard_id)),
            'partition_stock' => $number(fn (DailyReport $record): ?string => BoxUsage::stockOf($record->partition_id)),
            'cardboard_stock_after' => $number(fn (DailyReport $record): ?string => self::stockAfter(BoxUsage::stockOf($record->cardboard_id), $record->cardboard_used)),
            'partition_stock_after' => $number(fn (DailyReport $record): ?string => self::stockAfter(BoxUsage::stockOf($record->partition_id), $record->partition_used)),
            'cardboard_used', 'partition_used', 'changeover_minutes', 'changeover_count', 'produced', 'crew', 'damaged_before', 'damaged_after' => $number(
                fn (DailyReport $record): mixed => $record->getAttribute($name),
            ),
            'per_person' => $number(fn (DailyReport $record): ?int => TaskCosting::perPerson($record->produced, $record->crew)),
            'started_at', 'ended_at', 'break_time', 'overtime_started_at', 'overtime_ended_at', 'overtime_break' => $state(
                fn (DailyReport $record): ?string => self::time($record->getAttribute($name)),
            ),
            'quality_checks' => $state(fn (DailyReport $record): ?string => implode('、', (array) $record->quality_checks) ?: null),
            'gloves' => $state(fn (DailyReport $record): ?string => $record->gloves ? ProductionChoices::GLOVES : null),
            'update_status' => $state(fn (DailyReport $record): ?string => $record->update_status ? ProductionChoices::UPDATE_STATUS : null),
            'work_hours', 'overtime_hours' => $state(fn (DailyReport $record): string => self::hoursText($record->getAttribute($name))),
            'labor_cost' => $price(fn (DailyReport $record): string => TaskCosting::product(self::decimal($record->work_rate), $record->work_hours)),
            'overtime_labor_cost' => $price(fn (DailyReport $record): string => TaskCosting::product($record->overtime_hours, self::decimal($record->overtime_rate))),
            'machine_fee' => $price(fn (DailyReport $record): string => TaskCosting::product($record->machine_rate, $record->work_hours)),
            'sheet_fee' => $price($sheetFee),
            'changeover_fee' => $price($changeoverFee),
            'sheet_and_changeover_fee' => $price(fn (DailyReport $record): string => bcadd($sheetFee($record), $changeoverFee($record), 4)),
            'internal_cost', 'manufacturing_sales' => $entry->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            'glue_cost', 'work_rate', 'overtime_rate', 'cardboard_cost', 'partition_cost', 'processing_rate', 'changeover_rate', 'handwork_rate', 'machine_rate',
            'printing_rate', 'plate_change_rate', 'ink_mixing_rate' => $price(fn (DailyReport $record): ?string => $rate($record, $name)),
            'worker_code', 'employee_code', 'sales_staff_code', 'department_code' => $state(fn (DailyReport $record): ?string => self::text($record->worker->getAttribute($name))),
            'creator', 'updater' => $entry->state(fn (DailyReport $record): HtmlString => UserPopup::html(
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
        if (self::showsCosts()) {
            $components[] = RecordSections::section(self::DEVELOPER, $make, '開発者用フィールド', collapsed: true);
        }

        return $components;
    }

    /** A legacy single check box (手袋 「着用」, ステータスの更新 「する」), stored as a boolean. */
    private static function flag(string $name, string $option): CheckboxList
    {
        return CheckboxList::make($name)
            ->label(self::label($name))
            ->options([$option => $option])
            // the stored boolean arrives as is or already wrapped by the check box list ([true])
            ->formatStateUsing(fn (mixed $state): array => collect(is_array($state) ? $state : [$state])
                ->contains(fn (mixed $value): bool => $value === $option || $value === true || $value === 1 || $value === '1') ? [$option] : [])
            ->dehydrateStateUsing(fn (mixed $state): bool => in_array($option, (array) $state, true));
    }

    /** 在庫数更新値 = 在庫数 − 使用数 (null while the stock is not kept). */
    private static function stockAfter(?string $stock, mixed $used): ?string
    {
        return $stock === null ? null : bcsub($stock, is_numeric($used) ? (string) $used : '0', 4);
    }

    /** 「7.5時間」 */
    private static function hoursText(mixed $hours): string
    {
        return is_numeric($hours) ? AppFormat::number($hours).'時間' : '';
    }

    private static function unitPrice(mixed $consumableId): ?string
    {
        return is_numeric($consumableId) ? self::decimal(Consumable::query()->whereKey((int) $consumableId)->value('unit_price')) : null;
    }

    private static function decimal(mixed $value): ?string
    {
        return is_numeric($value) ? (string) $value : null;
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    /** 'H:i' of a stored or entered time. */
    private static function time(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^(\d{1,2}:\d{2})/', $value, $parts) === 1 ? $parts[1] : null;
    }
}
