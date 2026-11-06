<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Calendar\Concerns;

use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;
use Modules\Core\Application\Calendar\CompanyCalendar;
use Modules\Core\Domain\Calendar\CalendarDayType;

/**
 * The 会社カレンダー of a page (core::filament.company-calendar): the year (?year=, 2000–2100) as 12 small months, Sunday
 * first, with ‹ 年 ›. Shared by the editor of 共通管理 and the read-only page of the dashboards.
 */
trait ShowsCompanyCalendar
{
    #[Url]
    public string $year = '';

    /** bumped when the days change on the server (初期データを読み込む), so they are drawn again */
    public int $version = 0;

    public function mountShowsCompanyCalendar(): void
    {
        $this->year = (string) CompanyCalendar::year($this->year);
    }

    public function previousYear(): void
    {
        $this->year = (string) max(CompanyCalendar::FIRST_YEAR, $this->selectedYear() - 1);
    }

    public function nextYear(): void
    {
        $this->year = (string) min(CompanyCalendar::LAST_YEAR, $this->selectedYear() + 1);
    }

    protected function selectedYear(): int
    {
        return CompanyCalendar::year($this->year);
    }

    /**
     * What core::filament.company-calendar draws.
     *
     * @return array<string, mixed>
     */
    protected function calendarViewData(bool $editable): array
    {
        $year = $this->selectedYear();

        return [
            'editable' => $editable,
            'selectedYear' => $year,
            'version' => $this->version,
            'months' => array_map(fn (int $month): array => ['label' => "{$month}月", 'cells' => self::calendarCells($year, $month)], range(1, 12)),
            'days' => array_map(fn (CalendarDayType $type): string => $type->value, app(CompanyCalendar::class)->markedDays($year)),
            'cycle' => collect([null, ...CalendarDayType::cases()])
                ->mapWithKeys(fn (?CalendarDayType $type): array => [$type->value ?? '' => CalendarDayType::after($type)->value ?? ''])->all(),
            'labels' => collect(CalendarDayType::cases())->mapWithKeys(fn (CalendarDayType $type): array => [$type->value => $type->label()])->all(),
            'today' => CarbonImmutable::today()->toDateString(),
            'weekdays' => ['日', '月', '火', '水', '木', '金', '土'],
        ];
    }

    /**
     * The month from Sunday: empty cells before the 1st, then the days.
     *
     * @return list<array{date: string, day: int, sunday: bool}|null>
     */
    private static function calendarCells(int $year, int $month): array
    {
        $first = CarbonImmutable::create($year, $month);
        $cells = array_fill(0, $first->dayOfWeek, null);
        for ($day = $first; $day->month === $month; $day = $day->addDay()) {
            $cells[] = ['date' => $day->toDateString(), 'day' => $day->day, 'sunday' => $day->isSunday()];
        }

        return $cells;
    }
}
