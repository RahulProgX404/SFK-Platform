<?php

declare(strict_types=1);

namespace Modules\Analytics\Domain;

use DateTimeInterface;
use Modules\Core\Domain\Calendar\YearMonth;

/**
 * The business year of 22. 過去の売上データ and 25. 販売先別売上計画: May to April (the month order of both legacy apps;
 * the app setting fiscalYearStartMonth=4 contradicted it, SYSTEM_BRAIN APP_22 known issues). Month columns are `m05` …
 * `m12`, `m01` … `m04`.
 */
final class FiscalYear
{
    /** Calendar months in the order of the year. */
    public const MONTHS = [5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3, 4];

    /** @return list<string> the month columns, `<prefix>m05` … `<prefix>m04` */
    public static function columns(string $prefix = ''): array
    {
        return array_map(fn (int $month): string => $prefix.self::column($month), self::MONTHS);
    }

    public static function column(int $month): string
    {
        return 'm'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);
    }

    /** The calendar months of the business year that a day falls in (May of the year it starts). */
    public static function startingYear(DateTimeInterface $day): int
    {
        $year = (int) $day->format('Y');

        return (int) $day->format('n') >= self::MONTHS[0] ? $year : $year - 1;
    }

    /** @return array<string, YearMonth> month column => calendar month of the business year containing the day */
    public static function months(DateTimeInterface $day): array
    {
        $start = self::startingYear($day);
        $months = [];
        foreach (self::MONTHS as $month) {
            $months[self::column($month)] = YearMonth::of($month >= self::MONTHS[0] ? $start : $start + 1, $month);
        }

        return $months;
    }

    /**
     * 日付_和暦 of 22: 令和 from 2019-05-01, 平成 from 1989-01-08, else 昭和 (the legacy formula, by the year number).
     */
    public static function era(DateTimeInterface $day): string
    {
        $year = (int) $day->format('Y');
        $date = (int) $day->format('Ymd');

        return match (true) {
            $date >= 20190501 => '令和'.($year - 2018).'年',
            $date >= 19890108 => '平成'.($year - 1988).'年',
            default => '昭和'.($year - 1925).'年',
        };
    }

    /** 計画比進捗率 of 25: ROUNDUP(年間合計 / 計画値 × 100, 1) — away from zero; null without a plan. */
    public static function progress(int $actual, ?int $plan): ?string
    {
        if ($plan === null || $plan === 0) {
            return null;
        }

        $tenths = bcdiv(bcmul((string) $actual, '1000', 0), (string) $plan, 6);
        $whole = bcadd($tenths, '0', 0);
        if (bccomp($tenths, $whole, 6) !== 0) {
            $whole = bcadd($whole, str_starts_with($tenths, '-') ? '-1' : '1', 0);
        }

        return bcdiv($whole, '10', 1);
    }
}
