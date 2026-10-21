<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\LeaveRequests\Tables;

use App\Models\User;
use Closure;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Core\Access\UserType;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Filters\DateMode;
use Modules\Core\Domain\Filters\FilterCondition;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\HumanResources\Domain\HrChoices;
use Modules\HumanResources\Domain\LeaveRequests\LeaveRequestStatus;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestLayout;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestResource;
use Modules\HumanResources\Filament\Support\Employees;
use Modules\HumanResources\Models\LeaveRequest;

/**
 * Record list of 16. 申請管理 with the legacy views (SYSTEM_BRAIN APP_16 views.md): 申請一覧 (対象日付 from today, latest
 * first), 未承認一覧 (not 承認済), 作業者が自分, 休暇申請一覧 (and 休暇申請一覧2 by 対象日付) and 申請処理前一覧 (申請前 or
 * 第一申請中); the calendar test view was not carried over. （すべて） shows columns() in order. インライン編集 changes
 * 申請理由.
 */
final class LeaveRequestsTable
{
    public const VIEW_LIST = 'list';

    private const COLUMNS = ['status', 'created_at', 'applicant_id', 'worker_id', 'request_type', 'category', 'target_on', 'total_time', 'clock_time', 'reason'];

    private const USERS = ['applicant_id' => 'applicant', 'worker_id' => 'worker'];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', 'request_type', 'applicant_id', 'category', 'target_on', 'total_time', 'clock_time', 'reason', 'status', 'assignee', 'worker_id', 'created_at'] as $name) {
            $columns[$name] = LeaveRequestLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        $byId = [['field' => 'id', 'direction' => 'desc']];
        $leave = [['field' => 'request_type', 'operator' => FilterOperator::In->value, 'values' => ['休暇申請']]];
        $open = [['field' => 'status', 'operator' => FilterOperator::In->value, 'values' => [LeaveRequestStatus::Draft->value, LeaveRequestStatus::Pending->value]]];

        return [
            ListView::table(self::VIEW_LIST, '申請一覧', ['status', 'target_on', 'applicant_id', 'worker_id', 'request_type', 'category', 'reason', 'created_at', 'total_time',
                'clock_time'], [['field' => 'target_on', 'direction' => 'desc']],
                [['field' => 'target_on', 'operator' => FilterOperator::GreaterOrEqual->value, 'values' => [], 'mode' => DateMode::Today->value]]),
            ListView::table('unapproved', '未承認一覧', self::COLUMNS, $byId,
                [['field' => 'status', 'operator' => FilterOperator::NotIn->value, 'values' => [LeaveRequestStatus::Approved->value]]]),
            ListView::table('assigned', '作業者が自分', self::COLUMNS, $byId,
                [['field' => 'assignee', 'operator' => FilterOperator::In->value, 'values' => [FilterField::LOGIN_USER]]]),
            ListView::table('leave', '休暇申請一覧', self::COLUMNS, $byId, $leave),
            ListView::table('open', '申請処理前一覧', ['status', 'id', 'request_type', 'applicant_id', 'worker_id', 'category', 'target_on', 'total_time', 'clock_time', 'reason',
                'assignee'], $byId, $open),
            ListView::table('leave-by-date', '休暇申請一覧2', self::COLUMNS, [['field' => 'target_on', 'direction' => 'asc']], $leave),
        ];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['applicant', 'worker', 'creator']))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(LeaveRequestResource::class))
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->emptyStateHeading('申請がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_16 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => LeaveRequestLayout::label($name);

        return [
            FilterField::choice('request_type', $label('request_type'), HrChoices::options(HrChoices::REQUEST_TYPES)),
            FilterField::user('applicant_id', $label('applicant_id')),
            FilterField::user('worker_id', $label('worker_id')),
            FilterField::choice('category', $label('category'), HrChoices::options(HrChoices::CATEGORIES)),
            FilterField::date('target_on', $label('target_on')),
            FilterField::textarea('reason', $label('reason')),
            FilterField::choice('status', $label('status'), LeaveRequestStatus::options()),
            FilterField::user('assignee', $label('assignee'))->query(self::filterAssignee(...)),
            FilterField::number('id', $label('id')),
            FilterField::user('created_by', $label('creator')),
            FilterField::dateTime('created_at', $label('created_at')),
            FilterField::user('updated_by', $label('updater')),
            FilterField::dateTime('updated_at', $label('updated_at')),
        ];
    }

    /** @return list<InlineField> */
    public static function inlineFields(): array
    {
        return [InlineField::textarea('reason')->required()];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(LeaveRequestLayout::label($name))->placeholder('');
        $user = fn (string $key): Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(
            User::query()->select('users.name')->whereColumn('users.id', "leave_requests.{$key}"),
            $direction,
        );

        return match (true) {
            $name === 'reason' => $column->searchable()->limit(60),
            $name === 'target_on' => $column->date('Y-m-d'),
            $name === 'created_at' => $column->dateTime('Y-m-d H:i'),
            $name === 'status' => $column->state(fn (LeaveRequest $record): string => $record->status->value),
            $name === 'total_time', $name === 'clock_time' => $column->formatStateUsing(fn (mixed $state): string => is_string($state) ? substr($state, 0, 5) : ''),
            // user names open the ユーザー情報 popup, so the cell is not a link to the record
            isset(self::USERS[$name]) => $column->state(Employees::popup(self::USERS[$name]))->html()->disabledClick()->sortable(query: $user($name)),
            $name === 'assignee' => $column
                ->state(fn (LeaveRequest $record): HtmlString => LeaveRequestLayout::assignee($record))
                ->html()
                ->disabledClick()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('leave_requests.status', $direction)->orderBy(
                    User::query()->select('users.name')->whereColumn('users.id', 'leave_requests.created_by'),
                    $direction,
                )),
            default => $column,
        };
    }

    /**
     * 作業者 in (users): the requests waiting for them — their own 申請前 ones, and the 第一申請中 ones when an administrator
     * is among them (LeaveRequestWorkflow).
     *
     * @param  Builder<*>  $query
     */
    private static function filterAssignee(Builder $query, FilterCondition $condition): void
    {
        $ids = array_map(fn (string $value): int => $value === FilterField::LOGIN_USER ? (int) auth()->id() : (int) $value,
            array_values(array_filter($condition->values, fn (string $value): bool => $value !== '')));
        $blank = in_array('', $condition->values, true);
        $administrator = User::query()->whereKey($ids)->whereIn('user_type', [UserType::Admin->value, UserType::SuperAdmin->value])->exists();

        $matches = function (Builder $q) use ($ids, $blank, $administrator): void {
            $q->where(fn (Builder $own): Builder => $own->where('leave_requests.status', LeaveRequestStatus::Draft->value)
                ->whereIn('leave_requests.created_by', $ids === [] ? [0] : $ids));
            if ($administrator) {
                $q->orWhere('leave_requests.status', LeaveRequestStatus::Pending->value);
            }
            if ($blank) {
                $q->orWhere('leave_requests.status', LeaveRequestStatus::Approved->value);
            }
        };

        $condition->operator === FilterOperator::NotIn ? $query->whereNot($matches) : $query->where($matches);
    }
}
