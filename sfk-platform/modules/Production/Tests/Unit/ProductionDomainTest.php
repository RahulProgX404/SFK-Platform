<?php

/*
 * The figures of a daily report task (SYSTEM_BRAIN APP_08 formulas, BR-PR-01..04) and the Gantt chart periods and bars of
 * 12. 製袋予定管理.
 */

use Modules\Production\Domain\DailyReports\TaskCosting;
use Modules\Production\Domain\Schedules\GanttChart;

it('works out 作業時間 to two decimals after the break (BR-PR-01)', function () {
    expect(TaskCosting::workHours('08:00', '17:00', '01:00'))->toBe('8.00')
        ->and(TaskCosting::workHours('08:00', '08:20', '00:00'))->toBe('0.33') // 0.3333…
        ->and(TaskCosting::workHours('08:00', '08:25', '00:00'))->toBe('0.42') // 0.41666… rounds up
        ->and(TaskCosting::workHours('08:00:00', '12:30:00', null))->toBe('4.50')
        ->and(TaskCosting::workHours(null, '12:00', '01:00'))->toBeNull()
        ->and(TaskCosting::workHours('9:00', 'x', null))->toBeNull();
});

it('costs a task with its overtime: 社内原価 and 製造売上 rounded half up (BR-PR-02 / BR-PR-03)', function () {
    // 1,200 × 2.75 + 1.5 × 1,500 + 3,000.5 × 2.75 + 10.25 + 50 + 0.5 = 13,862.125
    expect(TaskCosting::internalCost('1200', '3000.5', '2.75', '1500', '1.5', '10.25', '50', '0.5'))->toBe(13862)
        ->and(TaskCosting::internalCost('1200', null, '2.75', null, null, null, null, null))->toBe(3300)
        // 2.75 × 3,000.5 + 1,200 × 出来上 10 = 20,251.375
        ->and(TaskCosting::manufacturingSales('1200', '3000.5', '2.75', '10'))->toBe(20251)
        ->and(TaskCosting::manufacturingSales('1000', '1', '0.5', null))->toBe(1) // 0.5 → 1
        ->and(TaskCosting::product('2.5', '3'))->toBe('7.5000');
});

it('splits 出来上 per person, rounded down (BR-PR-04)', function () {
    expect(TaskCosting::perPerson('1000', '3'))->toBe(333)
        ->and(TaskCosting::perPerson('1000', '0'))->toBeNull()
        ->and(TaskCosting::perPerson(null, '2'))->toBeNull();
});

it('finds the period of each Gantt scale, weeks from Sunday', function () {
    $day = new DateTimeImmutable('2026-10-21 15:30'); // a Wednesday

    expect(array_map(fn (DateTimeImmutable $at): string => $at->format('Y-m-d H:i'), GanttChart::period(GanttChart::DAY, $day)))->toBe(['2026-10-21 00:00', '2026-10-22 00:00'])
        ->and(array_map(fn (DateTimeImmutable $at): string => $at->format('Y-m-d'), GanttChart::period(GanttChart::WEEK, $day)))->toBe(['2026-10-18', '2026-10-25'])
        ->and(array_map(fn (DateTimeImmutable $at): string => $at->format('Y-m-d'), GanttChart::period(GanttChart::MONTH, $day)))->toBe(['2026-10-01', '2026-11-01'])
        ->and(array_map(fn (DateTimeImmutable $at): string => $at->format('Y-m-d'), GanttChart::period(GanttChart::YEAR, $day)))->toBe(['2026-01-01', '2027-01-01'])
        ->and(GanttChart::shift(GanttChart::MONTH, $day, -1)->format('Y-m-d'))->toBe('2026-09-01')
        ->and(GanttChart::shift(GanttChart::WEEK, $day, 1)->format('Y-m-d'))->toBe('2026-10-25')
        ->and(count(GanttChart::columns(GanttChart::DAY, $day)))->toBe(24)
        ->and(array_column(GanttChart::columns(GanttChart::WEEK, $day), 'label'))->toBe(['10/18(日)', '10/19(月)', '10/20(火)', '10/21(水)', '10/22(木)', '10/23(金)', '10/24(土)'])
        ->and(count(GanttChart::columns(GanttChart::MONTH, $day)))->toBe(31)
        ->and(array_column(GanttChart::columns(GanttChart::YEAR, $day), 'label')[11])->toBe('12月');
});

it('places bookings as bars clipped to the period, overlapping ones on their own lane', function () {
    [$start, $end] = GanttChart::period(GanttChart::DAY, new DateTimeImmutable('2026-10-21'));
    $at = fn (string $time): DateTimeImmutable => new DateTimeImmutable($time);

    $bars = GanttChart::bars([
        ['start' => $at('2026-10-21 06:00'), 'end' => $at('2026-10-21 12:00'), 'item' => 'a'],
        ['start' => $at('2026-10-21 09:00'), 'end' => $at('2026-10-21 10:00'), 'item' => 'b'], // overlaps a
        ['start' => $at('2026-10-21 12:00'), 'end' => $at('2026-10-22 06:00'), 'item' => 'c'], // runs past midnight
        ['start' => $at('2026-10-20 08:00'), 'end' => $at('2026-10-20 09:00'), 'item' => 'd'], // the day before
        ['start' => null, 'end' => $at('2026-10-21 09:00'), 'item' => 'e'],
    ], $start, $end);

    expect(array_column($bars, 'item'))->toBe(['a', 'b', 'c'])
        ->and($bars[0])->toMatchArray(['left' => 25.0, 'width' => 25.0, 'lane' => 0])
        ->and($bars[1]['lane'])->toBe(1)
        ->and($bars[2])->toMatchArray(['left' => 50.0, 'width' => 50.0, 'lane' => 0]);
});
