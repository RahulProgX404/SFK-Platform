<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\LeaveRequests;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Support\UserPopup;
use Modules\HumanResources\Domain\HrChoices;
use Modules\HumanResources\Domain\HrFields;
use Modules\HumanResources\Domain\LeaveRequests\LeaveRequestStatus;
use Modules\HumanResources\Filament\Support\Employees;
use Modules\HumanResources\Models\LeaveRequest;

/**
 * Form and record page of 16. 申請管理 (SYSTEM_BRAIN APP_16 fields.md "Form layout", widths from the template): 申請区分,
 * 申請者 (the signed-in user to start with), 作業員名 (employees 在籍中), 申請分類, 対象日付, the late / early times in steps of
 * 30 minutes and 申請理由. The record page shows ステータス and 作業者 of the プロセス管理 above the fields.
 */
final class LeaveRequestLayout
{
    private const STATUS = [[['status', 117], ['assignee', 193]]];

    private const MAIN = [
        [['request_type', 196], ['applicant_id', 344], ['worker_id', 301]],
        [['category', 196], ['target_on', 117], ['total_time', 258], ['clock_time', 286]],
        [['reason', 540]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [[['legacy_id', 193]]];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::components(self::input(...), withRecordRows: false));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::components(self::entry(...), withRecordRows: true));
    }

    public static function label(string $attribute): string
    {
        return HrFields::LEAVE[$attribute];
    }

    /** 作業者: the 作成者 while 申請前, the administrators while 第一申請中, nobody once 承認済. */
    public static function assignee(LeaveRequest $request): HtmlString
    {
        return match ($request->status) {
            LeaveRequestStatus::Draft => UserPopup::html($request->creator),
            LeaveRequestStatus::Pending => new HtmlString(e('管理者')),
            LeaveRequestStatus::Approved => new HtmlString(''),
        };
    }

    private static function input(string $name): ?Component
    {
        $label = self::label($name);
        $time = fn (): TimePicker => TimePicker::make($name)->label($label)->native()->seconds(false)->extraInputAttributes(['step' => 1800])
            ->rule('regex:/^\d{2}:(00|30)(:00)?$/')->validationMessages(['regex' => '30分単位で入力してください。']);

        return match ($name) {
            'request_type' => Select::make($name)->label($label)->placeholder('-----')->options(HrChoices::options(HrChoices::REQUEST_TYPES))->native(false)->required(),
            'applicant_id' => Employees::user($name, 'applicant', $label)->required()->default(fn (): ?int => auth()->id() === null ? null : (int) auth()->id()),
            'worker_id' => Employees::employee($name, 'worker', $label),
            'category' => Select::make($name)->label($label)->placeholder('-----')->options(HrChoices::options(HrChoices::CATEGORIES))->native(false)->required(),
            'target_on' => DatePicker::make($name)->label($label)->required(),
            'total_time', 'clock_time' => $time(),
            'reason' => Textarea::make($name)->label($label)->rows(4)->maxLength(4000)->required(),
            'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');
        $time = fn (mixed $state): string => is_string($state) ? substr($state, 0, 5) : '';

        return match ($name) {
            'status' => $entry->state(fn (LeaveRequest $record): string => $record->status->value),
            'assignee' => $entry->state(fn (LeaveRequest $record): HtmlString => self::assignee($record))->html(),
            // user names open the ユーザー情報 popup
            'applicant_id' => $entry->state(Employees::popup('applicant'))->html(),
            'worker_id' => $entry->state(Employees::popup('worker'))->html(),
            'target_on' => $entry->date('Y-m-d'),
            'total_time', 'clock_time' => $entry->formatStateUsing($time),
            'creator', 'updater' => $entry->state(Employees::popup($name))->html(),
            'created_at', 'updated_at' => $entry->dateTime('Y-m-d H:i'),
            default => $entry,
        };
    }

    /**
     * @param  callable(string): ?Component  $make
     * @return list<Component>
     */
    private static function components(callable $make, bool $withRecordRows): array
    {
        $components = [];
        if ($withRecordRows) {
            $components[] = RecordSections::section(self::STATUS, $make);
        }
        $components[] = RecordSections::section(self::MAIN, $make);
        if ($withRecordRows) {
            $components[] = RecordSections::section(self::FOOTER, $make);
        }
        if (auth()->user() instanceof User && auth()->user()->isSuperAdmin()) {
            $components[] = RecordSections::section(self::DEVELOPER, $make, '開発者用フィールド', collapsed: true);
        }

        return $components;
    }
}
