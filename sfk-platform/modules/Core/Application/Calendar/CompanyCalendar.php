<?php

declare(strict_types=1);

namespace Modules\Core\Application\Calendar;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Domain\Calendar\CalendarDayType;
use Modules\Core\Domain\Calendar\SeedCalendars;
use Modules\Core\Models\CompanyCalendarDay;

/**
 * 会社カレンダー: which days the company is closed (祝日 / 会社休日, and every Sunday) or works only the morning
 * (半日出勤). Edited in 共通管理 → 会社カレンダー; other modules ask typeOf() / isClosed() / dayTypes().
 */
final class CompanyCalendar
{
    public const FIRST_YEAR = 2000;

    public const LAST_YEAR = 2100;

    /** ?year= of the screen: 2000–2100, anything else is this year. */
    public static function year(mixed $value): int
    {
        $year = is_numeric($value) ? (int) $value : 0;

        return $year >= self::FIRST_YEAR && $year <= self::LAST_YEAR && (string) $year === trim((string) $value) ? $year : CarbonImmutable::today()->year;
    }

    /**
     * The days marked in the year (an unmarked Sunday is not listed).
     *
     * @return array<string, CalendarDayType> Y-m-d → type
     */
    public function markedDays(int $year): array
    {
        return $this->markedBetween(CarbonImmutable::create($year)->startOfYear(), CarbonImmutable::create($year)->endOfYear());
    }

    /**
     * Every day of the period that is not a plain working day, Sundays included.
     *
     * @return array<string, CalendarDayType> Y-m-d → type
     */
    public function dayTypes(CarbonInterface $from, CarbonInterface $to): array
    {
        $marked = $this->markedBetween($from, $to);
        $days = [];
        for ($day = CarbonImmutable::parse($from->toDateString()); $day->lte($to); $day = $day->addDay()) {
            $type = CalendarDayType::effective($day, $marked[$day->toDateString()] ?? null);
            if ($type !== null) {
                $days[$day->toDateString()] = $type;
            }
        }

        return $days;
    }

    /** 祝日 / 会社休日 / 半日出勤 of the day, or null for a working day. */
    public function typeOf(CarbonInterface $date): ?CalendarDayType
    {
        return $this->dayTypes($date, $date)[$date->toDateString()] ?? null;
    }

    public function isClosed(CarbonInterface $date): bool
    {
        return $this->typeOf($date)?->isClosed() ?? false;
    }

    /** Marks the day (null: back to an unmarked day). */
    public function setDay(CarbonInterface $date, ?CalendarDayType $type): void
    {
        self::guardYear($date->year);
        $day = $date->toDateString();

        if ($type === null) {
            CompanyCalendarDay::query()->whereDate('day', $day)->delete();

            return;
        }

        $row = CompanyCalendarDay::query()->whereDate('day', $day)->first() ?? new CompanyCalendarDay(['day' => $day]);
        $row->type = $type;
        $row->save();
    }

    /** 初期データを読み込む: the whole year replaced by the printed calendar (SeedCalendars). */
    public function importSeed(int $year): void
    {
        self::guardYear($year);
        if (! SeedCalendars::has($year)) {
            throw new InvalidArgumentException("No seed calendar for [{$year}].");
        }

        DB::transaction(function () use ($year): void {
            CompanyCalendarDay::query()->whereYear('day', $year)->delete();
            $now = CarbonImmutable::now();
            CompanyCalendarDay::query()->insert(array_map(
                fn (string $day, CalendarDayType $type): array => ['day' => $day, 'type' => $type->value, 'created_at' => $now, 'updated_at' => $now],
                array_keys(SeedCalendars::for($year)),
                SeedCalendars::for($year),
            ));
        });
    }

    /**
     * The days marked from–to (an unmarked Sunday is not listed).
     *
     * @return array<string, CalendarDayType> Y-m-d → type
     */
    public function markedBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        return CompanyCalendarDay::query()
            ->whereDate('day', '>=', $from->toDateString())
            ->whereDate('day', '<=', $to->toDateString())
            ->orderBy('day')
            ->get()
            ->mapWithKeys(fn (CompanyCalendarDay $row): array => [$row->day->toDateString() => $row->type])
            ->all();
    }

    private static function guardYear(int $year): void
    {
        if ($year < self::FIRST_YEAR || $year > self::LAST_YEAR) {
            throw new InvalidArgumentException("Year [{$year}] is outside the calendar.");
        }
    }
}
