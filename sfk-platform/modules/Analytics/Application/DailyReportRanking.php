<?php

declare(strict_types=1);

namespace Modules\Analytics\Application;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Production\Filament\Resources\DailyReports\DailyReportResource;
use Modules\Production\Models\DailyReport;

/**
 * 分析 → 日報分析 (legacy DailyReportAnalysis.bundle.js, app 日報分析) and the 作業者 card of the dashboard: per 作業者 the
 * sum of 枚数単価 + 型替え単価 of 08. 日報入力 (出来上 × 加工単価(枚) + 型替単価(回) × 回数, by 作業日), cut to whole yen per
 * month like the legacy script, and the hours worked (作業時間 + 残業時間, BR-PR-01); the 年度別金額ランキング by 年間合計
 * (ties by name). Summed from the reports the user may see (DailyReportResource::onlyVisible) whenever it is shown.
 */
final class DailyReportRanking
{
    /** 枚数単価 + 型替え単価 of a report (DailyReportsTable `sheet_and_changeover_fee`) */
    private const FEE = 'coalesce(daily_reports.produced * daily_reports.processing_rate, 0) + coalesce(daily_reports.changeover_rate * daily_reports.changeover_count, 0)';

    /** 作業時間 + 残業時間 of a report */
    private const HOURS = 'coalesce(daily_reports.work_hours, 0) + coalesce(daily_reports.overtime_hours, 0)';

    /** @return list<int> the business years with reports, oldest first, up to the current one */
    public function years(): array
    {
        $first = $this->reports()->min('daily_reports.worked_on');
        $current = FiscalYear::startingYear(now());
        $from = $first !== null ? FiscalYear::startingYear(new \DateTimeImmutable((string) $first)) : $current;

        return range(min($from, $current), $current);
    }

    /**
     * @return list<array{rank: int, user_id: int, name: string, months: array<int, int>, hours: array<int, float>, total: int, total_hours: float}>
     *                                                                                                                                               by 年間合計, highest first
     */
    public function ranking(int $fiscalYear): array
    {
        [$from, $after] = FiscalYear::days($fiscalYear);
        $month = MonthBucket::sql('daily_reports.worked_on');

        $rows = $this->reports()
            ->where('daily_reports.worked_on', '>=', $from)->where('daily_reports.worked_on', '<', $after)
            ->groupBy('daily_reports.user_id', DB::raw($month))
            ->selectRaw('daily_reports.user_id, '.$month.' as month, sum('.self::FEE.') as total, sum('.self::HOURS.') as hours')
            ->toBase()
            ->get();

        $workers = [];
        foreach ($rows as $row) {
            [, $calendarMonth] = MonthBucket::parse((string) $row->month);
            $workers[(int) $row->user_id][$calendarMonth] = [(int) floor((float) $row->total), round((float) $row->hours, 2)];
        }

        $names = User::query()->whereIn('id', array_keys($workers))->pluck('name', 'id')->all();
        $list = [];
        foreach ($workers as $userId => $sums) {
            $months = array_fill_keys(FiscalYear::MONTHS, 0);
            $hours = array_fill_keys(FiscalYear::MONTHS, 0.0);
            foreach ($sums as $calendarMonth => [$amount, $worked]) {
                $months[$calendarMonth] = $amount;
                $hours[$calendarMonth] = $worked;
            }
            $list[] = [
                'rank' => 0, 'user_id' => $userId, 'name' => (string) ($names[$userId] ?? ''),
                'months' => $months, 'hours' => $hours, 'total' => array_sum($months), 'total_hours' => round(array_sum($hours), 2),
            ];
        }

        return $this->ranked($list);
    }

    /**
     * The 作業者 of a period (the dashboard): amount (cut to whole yen) and hours, highest amount first.
     *
     * @return list<array{rank: int, user_id: int, name: string, total: int, total_hours: float, reports: int}>
     */
    public function top(string $from, string $after, int $limit = 5): array
    {
        $rows = $this->reports()
            ->where('daily_reports.worked_on', '>=', $from)->where('daily_reports.worked_on', '<', $after)
            ->groupBy('daily_reports.user_id')
            ->selectRaw('daily_reports.user_id, sum('.self::FEE.') as total, sum('.self::HOURS.') as hours, count(*) as reports')
            ->toBase()
            ->get();

        $names = User::query()->whereIn('id', $rows->pluck('user_id')->all())->pluck('name', 'id')->all();
        $list = $rows->map(fn (object $row): array => [
            'rank' => 0, 'user_id' => (int) $row->user_id, 'name' => (string) ($names[(int) $row->user_id] ?? ''),
            'total' => (int) floor((float) $row->total), 'total_hours' => round((float) $row->hours, 2), 'reports' => (int) $row->reports,
        ])->all();

        return array_slice($this->ranked($list), 0, $limit);
    }

    /**
     * @template T of array{rank: int, name: string, total: int}
     *
     * @param  list<T>  $list
     * @return list<T>
     */
    private function ranked(array $list): array
    {
        usort($list, fn (array $a, array $b): int => [$b['total'], $a['name']] <=> [$a['total'], $b['name']]);
        foreach ($list as $index => $row) {
            $list[$index]['rank'] = $index + 1;
        }

        return $list;
    }

    /** @return Builder<DailyReport> */
    private function reports(): Builder
    {
        return DailyReportResource::onlyVisible(DailyReport::query());
    }
}
