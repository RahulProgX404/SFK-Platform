<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages\Concerns\Lists;

use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Modules\Core\Domain\Filters\FilterFieldType;

/**
 * カレンダー形式 views of an app list (core::filament.app-calendar): six weeks around the month shown, with the
 * records of each day by the view's 日付 field.
 *
 * Part of Modules\Core\Filament\AppPages\AppListRecords (the shared app list); uses its views, fields and access rights.
 */
trait CalendarView
{
    /** Most records drawn on one calendar month. */
    public const CALENDAR_LIMIT = 1000;

    /** カレンダー形式: the month shown (Y-m); blank = this month */
    #[Url(as: 'month')]
    public string $calendarMonth = '';

    public function calendarMonthStart(): CarbonImmutable
    {
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $this->calendarMonth, $m) === 1) {
            return CarbonImmutable::create((int) $m[1], (int) $m[2], 1) ?? CarbonImmutable::now()->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    public function shiftCalendarMonth(int $months): void
    {
        $this->calendarMonth = $this->calendarMonthStart()->addMonthsNoOverflow(max(-1200, min(1200, $months)))->format('Y-m');
    }

    public function showCurrentCalendarMonth(): void
    {
        $this->calendarMonth = '';
    }

    /**
     * カレンダー形式: six weeks (Sunday first) around the shown month with the records of each day (filtered like the
     * list), each with its タイトル and the link to the record.
     *
     * @return list<list<array{date: CarbonImmutable, inMonth: bool, isToday: bool, records: list<array{title: string, url: string|null}>}>>
     */
    public function calendarWeeks(): array
    {
        $view = $this->currentView();
        $fields = $this->filterFieldMap();
        $dateField = $fields[(string) $view->dateField] ?? null;
        $titleField = $fields[(string) $view->titleField] ?? null;

        $first = $this->calendarMonthStart();
        $start = $first->subDays($first->dayOfWeek);
        $end = $start->addWeeks(6);

        $byDay = [];
        if ($dateField !== null) {
            $query = $this->getFilteredSortedTableQuery();
            if ($query !== null) {
                $column = $query->qualifyColumn($dateField->getColumn());
                $format = $dateField->type === FilterFieldType::Date ? 'Y-m-d' : 'Y-m-d H:i:s';
                $records = $query->where($column, '>=', $start->format($format))->where($column, '<', $end->format($format))
                    ->limit(self::CALENDAR_LIMIT)->get();

                foreach ($records as $record) {
                    $date = $record->getAttribute($dateField->getColumn());
                    if ($date === null) {
                        continue;
                    }
                    $title = $titleField !== null ? $record->getAttribute($titleField->getColumn()) : $record->getKey();
                    $byDay[CarbonImmutable::parse($date)->format('Y-m-d')][] = [
                        'title' => $title instanceof BackedEnum ? (string) $title->value : (string) $title,
                        'url' => $this->appResource()::hasPage('view') ? $this->appResource()::getUrl('view', ['record' => $record]) : null,
                    ];
                }
            }
        }

        $weeks = [];
        for ($week = 0; $week < 6; $week++) {
            $days = [];
            for ($weekday = 0; $weekday < 7; $weekday++) {
                $date = $start->addDays($week * 7 + $weekday);
                $days[] = [
                    'date' => $date,
                    'inMonth' => $date->month === $first->month,
                    'isToday' => $date->isToday(),
                    'records' => $byDay[$date->format('Y-m-d')] ?? [],
                ];
            }
            $weeks[] = $days;
        }

        return $weeks;
    }
}
