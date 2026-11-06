<?php

/*
 * The figures of a daily report task (SYSTEM_BRAIN APP_08 formulas, BR-PR-01..04) and the fixed parts of the 製袋予定表 of
 * 12. 製袋予定管理.
 */

use Modules\Production\Domain\DailyReports\TaskCosting;
use Modules\Production\Domain\Schedules\ScheduleBoard;

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
    expect(TaskCosting::internalCost('1200', '3000.5', '2.75', '2.75', '1500', '1.5', '10.25', '50', '0.5'))->toBe(13862)
        ->and(TaskCosting::internalCost('1200', null, '2.75', '2.75', null, null, null, null, null))->toBe(3300)
        // 2.75 × 3,000.5 + 1,200 × 出来上 10 = 20,251.375
        ->and(TaskCosting::manufacturingSales('1200', '3000.5', '2.75', '10'))->toBe(20251)
        ->and(TaskCosting::manufacturingSales('1000', '1', '0.5', null))->toBe(1) // 0.5 → 1
        ->and(TaskCosting::product('2.5', '3'))->toBe('7.5000');
});

it('counts the hours of a task by its 勤務種別: a 残業 task is overtime from 開始 to 終了', function () {
    // 通常: 作業時間 with the machine; the separate overtime span of the earlier records still counts
    expect(TaskCosting::hours(false, '09:00', '17:00', '00:00'))->toBe(['work' => '8.00', 'overtime' => null, 'machine' => '8.00'])
        ->and(TaskCosting::hours(false, '08:00', '17:00', '01:00', '17:00', '19:30', '00:00'))->toBe(['work' => '8.00', 'overtime' => '2.50', 'machine' => '8.00'])
        // 残業: the whole span at the 残業単価, the machine runs as long
        ->and(TaskCosting::hours(true, '17:00', '19:30', '00:00'))->toBe(['work' => '0.00', 'overtime' => '2.50', 'machine' => '2.50'])
        ->and(TaskCosting::hours(true, null, '19:30', '00:00'))->toBe(['work' => null, 'overtime' => null, 'machine' => null]);
});

it('splits 出来上 per person, rounded down (BR-PR-04)', function () {
    expect(TaskCosting::perPerson('1000', '3'))->toBe(333)
        ->and(TaskCosting::perPerson('1000', '0'))->toBeNull()
        ->and(TaskCosting::perPerson(null, '2'))->toBeNull();
});

it('formats a 注番 typed on the 製袋予定表 (123 → sfk-0000123) and keeps its lines in the order of the board', function () {
    expect(ScheduleBoard::orderNumber('123'))->toBe('sfk-0000123')
        ->and(ScheduleBoard::orderNumber(' 7070 '))->toBe('sfk-0007070')
        ->and(ScheduleBoard::orderNumber('12345678'))->toBeNull()
        ->and(ScheduleBoard::orderNumber('12a'))->toBeNull()
        ->and(ScheduleBoard::orderNumber(''))->toBeNull()
        ->and(ScheduleBoard::isOrderNumber('sfk-0000123'))->toBeTrue()
        ->and(ScheduleBoard::isOrderNumber('sfk-123'))->toBeFalse()
        ->and(array_slice(array_column(ScheduleBoard::LINES, 'label'), 0, 6))->toBe(['1号機', '2号機', '5号機', '6号機', '3号機', '3-2号機・4号機'])
        ->and(count(ScheduleBoard::LINES))->toBe(17)
        ->and(array_unique(ScheduleBoard::codes()))->toHaveCount(17)
        ->and(array_keys(ScheduleBoard::HIGHLIGHTS))->toBe(['yellow', 'rose', 'orange', 'green', 'blue', 'purple']);
});
