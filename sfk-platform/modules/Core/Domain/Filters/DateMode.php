<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Filters;

use Carbon\CarbonImmutable;

/**
 * Value of a date / date-time condition: a given day (日付を指定 / 日時を指定) or a period relative to today
 * (legacy functions TODAY(), THIS_MONTH(), LAST_MONTH() …). Weeks run Sunday–Saturday. 今週/先週/来週 take a
 * weekday ("all" or 0 = 日曜日 … 6 = 土曜日), the months a day ("all", "1" … "31", "last" = 末日).
 */
enum DateMode: string
{
    case Specified = 'specified';
    case Today = 'today';
    case Yesterday = 'yesterday';
    case Tomorrow = 'tomorrow';
    case ThisWeek = 'this_week';
    case LastWeek = 'last_week';
    case NextWeek = 'next_week';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case NextMonth = 'next_month';
    case ThisYear = 'this_year';

    public function label(FilterFieldType $type): string
    {
        return match ($this) {
            self::Specified => $type === FilterFieldType::DateTime ? '日時を指定' : '日付を指定',
            self::Today => '今日',
            self::Yesterday => '昨日',
            self::Tomorrow => '明日',
            self::ThisWeek => '今週',
            self::LastWeek => '先週',
            self::NextWeek => '来週',
            self::ThisMonth => '今月',
            self::LastMonth => '先月',
            self::NextMonth => '来月',
            self::ThisYear => '今年',
        };
    }

    public function isWeek(): bool
    {
        return in_array($this, [self::ThisWeek, self::LastWeek, self::NextWeek], true);
    }

    public function isMonth(): bool
    {
        return in_array($this, [self::ThisMonth, self::LastMonth, self::NextMonth], true);
    }

    /** @return array<int|string, string> choices of the second value select (すべて, weekdays or days) */
    public function subOptions(): array
    {
        if ($this->isWeek()) {
            return ['all' => 'すべて', '0' => '日曜日', '1' => '月曜日', '2' => '火曜日', '3' => '水曜日', '4' => '木曜日', '5' => '金曜日', '6' => '土曜日'];
        }

        if ($this->isMonth()) {
            $days = ['all' => 'すべて'];
            foreach (range(1, 31) as $day) {
                $days[(string) $day] = $day.'日';
            }

            return [...$days, 'last' => '末日'];
        }

        return [];
    }

    /**
     * The period [start, end) the value stands for, or null when it is incomplete or invalid.
     *
     * @param  string|null  $date  Y-m-d (Specified)
     * @param  string|null  $time  H:i (Specified, date-time fields; blank = the whole day)
     * @param  string|null  $sub  weekday or day, see subOptions()
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function period(CarbonImmutable $now, ?string $date = null, ?string $time = null, ?string $sub = null): ?array
    {
        $today = $now->startOfDay();

        return match ($this) {
            self::Specified => self::specified($date, $time, $now->getTimezone()->getName()),
            self::Today => [$today, $today->addDay()],
            self::Yesterday => [$today->subDay(), $today],
            self::Tomorrow => [$today->addDay(), $today->addDays(2)],
            self::ThisWeek, self::LastWeek, self::NextWeek => self::week(
                $today->subDays($today->dayOfWeek)->addWeeks(match ($this) {
                    self::LastWeek => -1,
                    self::NextWeek => 1,
                    default => 0,
                }),
                $sub,
            ),
            self::ThisMonth, self::LastMonth, self::NextMonth => self::month(
                $today->startOfMonth()->addMonthsNoOverflow(match ($this) {
                    self::LastMonth => -1,
                    self::NextMonth => 1,
                    default => 0,
                }),
                $sub,
            ),
            self::ThisYear => [$today->startOfYear(), $today->startOfYear()->addYear()],
        };
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable}|null */
    private static function specified(?string $date, ?string $time, string $timezone): ?array
    {
        if ($date === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            return null;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        if (! $day instanceof CarbonImmutable) {
            return null;
        }

        if ($time !== null && preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m) === 1) {
            $start = $day->setTime((int) $m[1], (int) $m[2]);

            return [$start, $start->addMinute()];
        }

        return [$day, $day->addDay()];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable}|null */
    private static function week(CarbonImmutable $sunday, ?string $sub): ?array
    {
        if ($sub === null || $sub === 'all') {
            return [$sunday, $sunday->addWeek()];
        }

        if (preg_match('/^[0-6]$/', $sub) !== 1) {
            return null;
        }

        $day = $sunday->addDays((int) $sub);

        return [$day, $day->addDay()];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable}|null */
    private static function month(CarbonImmutable $first, ?string $sub): ?array
    {
        if ($sub === null || $sub === 'all') {
            return [$first, $first->addMonthNoOverflow()];
        }

        if ($sub === 'last') {
            $day = $first->endOfMonth()->startOfDay();

            return [$day, $day->addDay()];
        }

        if (preg_match('/^([1-9]|[12]\d|3[01])$/', $sub) !== 1 || (int) $sub > $first->daysInMonth) {
            return null;
        }

        $day = $first->addDays((int) $sub - 1);

        return [$day, $day->addDay()];
    }
}
