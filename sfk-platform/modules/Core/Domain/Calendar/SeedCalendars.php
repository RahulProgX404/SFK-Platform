<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Calendar;

/**
 * 初期データ of the 会社カレンダー: the company's printed calendar, year by year. Sundays are left out (they are
 * closed without being marked).
 */
final class SeedCalendars
{
    /** @var array<int, array<string, list<string>>> year → type → dates */
    private const YEARS = [
        2026 => [ // 令和8年
            'national_holiday' => [
                '2026-01-12', // 成人の日
                '2026-02-11', // 建国記念の日
                '2026-02-23', // 天皇誕生日
                '2026-03-20', // 春分の日
                '2026-04-29', // 昭和の日
                '2026-05-04', // みどりの日
                '2026-05-05', // こどもの日
                '2026-05-06', // 振替休日
                '2026-07-20', // 海の日
                '2026-08-11', // 山の日
                '2026-09-21', // 敬老の日
                '2026-09-22', // 国民の休日
                '2026-09-23', // 秋分の日
                '2026-10-12', // スポーツの日
                '2026-11-03', // 文化の日
                '2026-11-23', // 勤労感謝の日
            ],
            'company_holiday' => [
                '2026-01-01', '2026-01-02', '2026-01-03',
                '2026-02-14', '2026-02-28',
                '2026-03-14', '2026-03-21',
                '2026-04-11',
                '2026-05-02',
                '2026-06-13', '2026-06-20',
                '2026-07-04', '2026-07-18',
                '2026-08-12', '2026-08-13', '2026-08-14', '2026-08-15', // お盆
                '2026-09-05', '2026-09-19',
                '2026-10-10', '2026-10-24',
                '2026-11-28',
                '2026-12-26',
                '2026-12-28', '2026-12-29', '2026-12-30', '2026-12-31', // 年末年始
            ],
            'half_day' => [
                '2026-01-17', '2026-01-24',
                '2026-02-07', '2026-02-21',
                '2026-03-07',
                '2026-04-04', '2026-04-25',
                '2026-05-16', '2026-05-23',
                '2026-06-06', '2026-06-27',
                '2026-07-11',
                '2026-08-01', '2026-08-22',
                '2026-09-12',
                '2026-10-17',
                '2026-11-07', '2026-11-21',
                '2026-12-05', '2026-12-12',
            ],
        ],
    ];

    public static function has(int $year): bool
    {
        return isset(self::YEARS[$year]);
    }

    /** @return array<string, CalendarDayType> date (Y-m-d) → type; empty when the year has no 初期データ */
    public static function for(int $year): array
    {
        $days = [];
        foreach (self::YEARS[$year] ?? [] as $type => $dates) {
            foreach ($dates as $date) {
                $days[$date] = CalendarDayType::from($type);
            }
        }
        ksort($days);

        return $days;
    }
}
