<?php

declare(strict_types=1);

namespace Modules\Analytics\Application;

use App\Models\User;
use Closure;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Analytics\Domain\Period;
use Modules\Core\Application\Cache\DataCache;
use Modules\HumanResources\Domain\LeaveRequests\LeaveRequestStatus;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestResource;
use Modules\HumanResources\Models\LeaveRequest;
use Modules\Production\Filament\Resources\DailyReports\DailyReportResource;
use Modules\Production\Models\DailyReport;

/**
 * 個人の概要 (bug.md 2026-11-06, overview.png) of one person for a period:
 * - 勤務時間: 作業時間 + 残業時間 of their 08. 日報入力 (作業者, 作業日; BR-PR-01);
 * - 出勤日数: the days (作業日) they filled in a 日報 for — a day with several reports counts once;
 * - 収入: 時給計算 + 残業時給計算 of those reports (作業単価 × 作業時間 + 残業時間 × 残業単価, the rates the report
 *   copied from 02. 従業員マスタ — TaskCosting), cut to whole yen;
 * - 休暇日数 and 有給休暇: the 承認済 requests of 16. 申請管理 for them (作業員名, else 申請者) by 対象日付, counted by
 *   申請分類 (SYSTEM_BRAIN APP_16): a whole day or a half (半有給 / 半休); 遅刻・早退 (時間欠勤) are hours, not days.
 *   休暇日数 is every day off (有給 + the others), 有給休暇 the paid ones.
 * Read from the records the viewer may see (onlyVisible); 作業単価 makes it confidential — shown to the person and to
 * administrators only (EmployeeDataAccess::canViewRates, the callers' check).
 */
final class PersonalOverview
{
    /** 申請分類 => [days, paid] */
    public const LEAVE_DAYS = [
        '有給休暇' => [1.0, true],
        '半有給(午前有給)' => [0.5, true],
        '半有給(午後有給)' => [0.5, true],
        '振替休暇(代休)' => [1.0, false],
        '慶弔休暇' => [1.0, false],
        '休暇(欠勤)' => [1.0, false],
        '半休(午前欠勤)' => [0.5, false],
        '半休(午後欠勤)' => [0.5, false],
    ];

    /** @return array{hours: float, income: int, reports: int, work_days: int, leave_days: float, paid_leave_days: float} */
    public function for(User $person, Period $period): array
    {
        return $this->cached('for', [(string) $person->id, $period->start().'~'.$period->after()], function () use ($person, $period): array {
            $work = $this->reports($person, $period)
                ->selectRaw('count(*) as reports, count(distinct daily_reports.worked_on) as work_days, '
                    .'coalesce(sum(coalesce(daily_reports.work_hours, 0) + coalesce(daily_reports.overtime_hours, 0)), 0) as hours, '
                    .'coalesce(sum(coalesce(daily_reports.work_hours, 0) * coalesce(daily_reports.work_rate, 0) + coalesce(daily_reports.overtime_hours, 0) * coalesce(daily_reports.overtime_rate, 0)), 0) as income')
                ->toBase()
                ->first();

            $leave = 0.0;
            $paid = 0.0;
            $categories = $this->leaves($person, $period)->pluck('category');
            foreach ($categories as $category) {
                [$days, $isPaid] = self::LEAVE_DAYS[(string) $category] ?? [0.0, false];
                $leave += $days;
                $paid += $isPaid ? $days : 0.0;
            }

            return [
                'hours' => round((float) ($work->hours ?? 0), 2),
                'income' => (int) floor((float) ($work->income ?? 0)),
                'reports' => (int) ($work->reports ?? 0),
                'work_days' => (int) ($work->work_days ?? 0),
                'leave_days' => $leave,
                'paid_leave_days' => $paid,
            ];
        });
    }

    /**
     * 勤務時間 and 収入 (as in for()) per calendar month of a year (勤務時間・収入の推移, bug.md 2026-11-07).
     *
     * @return array{hours: array<int, float>, income: array<int, int>} month (1–12) => value
     */
    public function monthly(User $person, int $year): array
    {
        return $this->cached('monthly', [(string) $person->id, (string) $year], function () use ($person, $year): array {
            $month = MonthBucket::sql('daily_reports.worked_on');
            $hours = array_fill(1, 12, 0.0);
            $income = array_fill(1, 12, 0);
            $rows = $this->reports($person, Period::between(sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year), new DateTimeImmutable))
                ->selectRaw("{$month} as month, "
                    .'coalesce(sum(coalesce(daily_reports.work_hours, 0) + coalesce(daily_reports.overtime_hours, 0)), 0) as hours, '
                    .'coalesce(sum(coalesce(daily_reports.work_hours, 0) * coalesce(daily_reports.work_rate, 0) + coalesce(daily_reports.overtime_hours, 0) * coalesce(daily_reports.overtime_rate, 0)), 0) as income')
                ->groupByRaw($month)
                ->toBase()
                ->get();

            foreach ($rows as $row) {
                [, $number] = MonthBucket::parse((string) $row->month);
                $hours[$number] = round((float) $row->hours, 2);
                $income[$number] = (int) floor((float) $row->income);
            }

            return ['hours' => $hours, 'income' => $income];
        });
    }

    /** @return Builder<DailyReport> */
    private function reports(User $person, Period $period): Builder
    {
        return DailyReportResource::onlyVisible(DailyReport::query())
            ->where('daily_reports.user_id', $person->id)
            ->where('daily_reports.worked_on', '>=', $period->start())
            ->where('daily_reports.worked_on', '<', $period->after());
    }

    /** @return Builder<LeaveRequest> the approved requests for the person (作業員名, else 申請者) */
    private function leaves(User $person, Period $period): Builder
    {
        return LeaveRequestResource::onlyVisible(LeaveRequest::query())
            ->where('status', LeaveRequestStatus::Approved->value)
            ->where(fn (Builder $query) => $query
                ->where('worker_id', $person->id)
                ->orWhere(fn (Builder $query) => $query->whereNull('worker_id')->where('applicant_id', $person->id)))
            ->where('target_on', '>=', $period->start())
            ->where('target_on', '<', $period->after());
    }

    /**
     * A figure from the cache (DataCache — added up afresh after any record changes): per viewer (their rights narrow
     * what is added up), the person and arguments.
     *
     * @template T
     *
     * @param  list<string>  $args
     * @param  Closure(): T  $compute
     * @return T
     */
    private function cached(string $name, array $args, Closure $compute): mixed
    {
        return DataCache::remember('personal-overview:'.auth()->id().':'.$name.':'.implode('|', $args), $compute);
    }
}
