<?php

declare(strict_types=1);

namespace Modules\Production\Domain\Schedules;

use DateInterval;
use DateTimeImmutable;

/**
 * The Gantt charts of 12. 製袋予定管理 (legacy views 日次 / 週次 / 月次 / 年次ガントチャート, SYSTEM_BRAIN APP_12): the period of
 * a scale around a day, its columns, and each booking as a bar on its machine's row — clipped to the period, placed by
 * its share of the period (left / width in %), bookings that overlap on one machine on separate lanes.
 */
final class GanttChart
{
    public const DAY = 'day';

    public const WEEK = 'week';

    public const MONTH = 'month';

    public const YEAR = 'year';

    public const SCALES = [self::DAY, self::WEEK, self::MONTH, self::YEAR];

    /**
     * Start (inclusive) and end (exclusive) of the period of the scale that contains the day. Weeks start on Sunday
     * (日–土, like the calendar views).
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    public static function period(string $scale, DateTimeImmutable $day): array
    {
        $day = $day->setTime(0, 0);

        return match ($scale) {
            self::WEEK => [$start = $day->modify('-'.$day->format('w').' days'), $start->add(new DateInterval('P7D'))],
            self::MONTH => [$start = $day->modify('first day of this month'), $start->add(new DateInterval('P1M'))],
            self::YEAR => [$start = $day->setDate((int) $day->format('Y'), 1, 1), $start->add(new DateInterval('P1Y'))],
            default => [$day, $day->add(new DateInterval('P1D'))],
        };
    }

    /** The day `$steps` periods before / after (‹ ›). */
    public static function shift(string $scale, DateTimeImmutable $day, int $steps): DateTimeImmutable
    {
        $unit = match ($scale) {
            self::WEEK => 'week',
            self::MONTH => 'month',
            self::YEAR => 'year',
            default => 'day',
        };

        return self::period($scale, $day)[0]->modify(($steps >= 0 ? '+' : '').$steps.' '.$unit);
    }

    /**
     * Column headers of the period: hours (日次), days (週次 / 月次) or months (年次).
     *
     * @return list<array{label: string, start: DateTimeImmutable}>
     */
    public static function columns(string $scale, DateTimeImmutable $day): array
    {
        [$start, $end] = self::period($scale, $day);
        $step = match ($scale) {
            self::YEAR => new DateInterval('P1M'),
            self::DAY => new DateInterval('PT1H'),
            default => new DateInterval('P1D'),
        };
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];

        $columns = [];
        for ($at = $start; $at < $end; $at = $at->add($step)) {
            $columns[] = [
                'label' => match ($scale) {
                    self::YEAR => $at->format('n').'月',
                    self::DAY => $at->format('G'),
                    self::WEEK => $at->format('n/j').'('.$weekdays[(int) $at->format('w')].')',
                    default => $at->format('j'),
                },
                'start' => $at,
            ];
        }

        return $columns;
    }

    /**
     * The bars of the bookings that fall in the period, with their lane on the row; bookings without both times or
     * outside the period are left out.
     *
     * @template T
     *
     * @param  list<array{start: ?DateTimeImmutable, end: ?DateTimeImmutable, item: T}>  $bookings  of one row
     * @return list<array{left: float, width: float, lane: int, item: T}>
     */
    public static function bars(array $bookings, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $length = $end->getTimestamp() - $start->getTimestamp();
        $shown = array_values(array_filter($bookings, fn (array $booking): bool => $booking['start'] !== null && $booking['end'] !== null
            && $booking['start'] < $end && $booking['end'] > $start && $booking['end'] >= $booking['start']));
        usort($shown, fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

        $lanes = [];
        $bars = [];
        foreach ($shown as $booking) {
            $from = max($booking['start']->getTimestamp(), $start->getTimestamp());
            $to = min($booking['end']->getTimestamp(), $end->getTimestamp());

            $lane = 0;
            while (isset($lanes[$lane]) && $lanes[$lane] > $from) {
                $lane++;
            }
            $lanes[$lane] = $to;

            $bars[] = [
                'left' => round(($from - $start->getTimestamp()) / $length * 100, 4),
                'width' => max(round(($to - $from) / $length * 100, 4), 0.2),
                'lane' => $lane,
                'item' => $booking['item'],
            ];
        }

        return $bars;
    }
}
