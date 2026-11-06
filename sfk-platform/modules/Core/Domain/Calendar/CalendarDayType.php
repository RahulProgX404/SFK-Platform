<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Calendar;

use Carbon\CarbonInterface;

/**
 * A marked day of the 会社カレンダー. A day that is not marked is a working day (平日), except Sundays: every Sunday
 * counts as 祝日 unless it is marked otherwise. Both holiday types mean the company is closed; 半日出勤 is a working
 * morning.
 */
enum CalendarDayType: string
{
    case NationalHoliday = 'national_holiday';
    case CompanyHoliday = 'company_holiday';
    case HalfDay = 'half_day';

    public function label(): string
    {
        return match ($this) {
            self::NationalHoliday => '祝日',
            self::CompanyHoliday => '会社休日',
            self::HalfDay => '半日出勤',
        };
    }

    public function isClosed(): bool
    {
        return $this !== self::HalfDay;
    }

    /** A click on a day: 平日 → 祝日 → 会社休日 → 半日出勤 → 平日. */
    public static function after(?self $type): ?self
    {
        return match ($type) {
            null => self::NationalHoliday,
            self::NationalHoliday => self::CompanyHoliday,
            self::CompanyHoliday => self::HalfDay,
            self::HalfDay => null,
        };
    }

    /** The marking wins; otherwise a Sunday is 祝日 and any other day 平日 (null). */
    public static function effective(CarbonInterface $date, ?self $marked): ?self
    {
        return $marked ?? ($date->isSunday() ? self::NationalHoliday : null);
    }
}
