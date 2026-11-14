<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Dashboard;

use Closure;
use Modules\Core\Filament\Support\Tip;

/**
 * A line chart of the 12 months of a year (personal-growth.png, bug.md 2026-11-07; drawn by
 * analytics::widgets.partials.trend-chart): one y axis from 0 to a round top with 4 steps, smooth lines that never
 * swing past their points (monotone cubic) over a soft area. Months without a value (null: the rest of this year, or no
 * data — `missing`) are left out and never bridged: a line stops before them and starts again after them, so no sales
 * are suggested where none were recorded. A month whose value is still incomplete (`notes`: this month so far, the last
 * month of data) is reached by a dotted segment and marked with a ring; its note follows the value in the tooltip.
 * The plot is a 1000 × 300 box stretched to the card; the labels and the hover marks are HTML, placed in % of it.
 * With `compare` (a formatter of a signed difference) the tooltip of a month also shows the first line against the
 * second — 差額 and 前年同月比 — when both have a whole month's value (an incomplete month says why it is not compared).
 */
final class TrendChart
{
    public const WIDTH = 1000;

    public const HEIGHT = 300;

    public const MONTHS = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];

    /**
     * @param  list<array{key: string, label: string, color: string, values: array<int, int|float|null>, dashed?: bool, notes?: array<int, string>, missing?: list<int>}>  $series  values by month 1–12; notes: month => what is incomplete about its value (「11/14時点」); missing: months without data (「データなし」 in the tooltip and the table)
     * @param  Closure(int|float): string  $axisLabel  short value for the y axis (e.g. 「120万」)
     * @param  Closure(int|float): string  $valueLabel  full value for the tooltip and the table (e.g. 「¥1,200,000」)
     * @param  (Closure(int|float): string)|null  $compare  signed difference (e.g. 「−¥1,200」): compare the first line with the second
     * @return array{ticks: list<array{label: string, top: float}>, series: list<array{key: string, label: string, color: string, dashed: bool, line: string, partialLine: string, area: string, points: list<array{left: float, top: float, partial: bool}|null>, values: list<string|null>}>, months: list<string>, lefts: list<float>, empty: bool, compare: list<array{difference?: string, change?: string|null, tone?: string, note?: string}|null>}
     */
    public static function build(array $series, Closure $axisLabel, Closure $valueLabel, ?Closure $compare = null): array
    {
        $max = 0.0;
        foreach ($series as $line) {
            foreach ($line['values'] as $value) {
                $max = max($max, (float) ($value ?? 0));
            }
        }

        $step = self::niceStep($max / 4);
        $top = $step * 4;
        $ticks = [];
        for ($i = 4; $i >= 0; $i--) {
            $ticks[] = ['label' => $axisLabel($step * $i), 'top' => round((4 - $i) * 25, 3)];
        }

        $lefts = array_map(fn (int $index): float => round($index / 11 * 100, 3), range(0, 11));
        $built = [];
        foreach ($series as $line) {
            $notes = $line['notes'] ?? [];
            $missing = $line['missing'] ?? [];
            $points = [];
            $values = [];
            foreach (range(1, 12) as $month) {
                $value = $line['values'][$month] ?? null;
                $values[] = match (true) {
                    $value !== null => $valueLabel($value).(isset($notes[$month]) ? '（'.$notes[$month].'）' : ''),
                    in_array($month, $missing, true) => 'データなし',
                    default => null,
                };
                $points[] = $value === null ? null : [
                    'x' => ($month - 1) / 11 * self::WIDTH,
                    'y' => self::HEIGHT - ($top > 0 ? (float) $value / $top : 0) * self::HEIGHT,
                    'partial' => isset($notes[$month]),
                ];
            }

            [$solid, $dotted, $area] = self::paths($points);
            $built[] = [
                'key' => $line['key'],
                'label' => $line['label'],
                'color' => $line['color'],
                // a line for comparison (e.g. the year before): dashed, without the area under it
                'dashed' => (bool) ($line['dashed'] ?? false),
                'line' => $solid,
                'partialLine' => $dotted,
                'area' => $area,
                'points' => array_map(fn (?array $point): ?array => $point === null ? null : [
                    'left' => round($point['x'] / self::WIDTH * 100, 3),
                    'top' => round($point['y'] / self::HEIGHT * 100, 3),
                    'partial' => $point['partial'],
                ], $points),
                'values' => $values,
            ];
        }

        return [
            'ticks' => $ticks,
            'series' => $built,
            'months' => self::MONTHS,
            'lefts' => $lefts,
            'empty' => $max <= 0,
            'compare' => $compare !== null && count($series) >= 2 ? self::compared($series[0], $series[1], $compare) : array_fill(0, 12, null),
        ];
    }

    /**
     * Per month: the first line against the second — 差額 (formatted) and 前年同月比 (null without a base) — only when both
     * have a whole month's value; an incomplete month gets a note instead, a month without both nothing.
     *
     * @param  array{values: array<int, int|float|null>, notes?: array<int, string>}  $now
     * @param  array{values: array<int, int|float|null>, notes?: array<int, string>}  $before
     * @param  Closure(int|float): string  $difference
     * @return list<array{difference?: string, change?: string|null, tone?: string, note?: string}|null>
     */
    private static function compared(array $now, array $before, Closure $difference): array
    {
        $list = [];
        foreach (range(1, 12) as $month) {
            $a = $now['values'][$month] ?? null;
            $b = $before['values'][$month] ?? null;
            if ($a === null || $b === null) {
                $list[] = null;
            } elseif (isset($now['notes'][$month]) || isset($before['notes'][$month])) {
                $list[] = ['note' => '月の途中のため比較なし'];
            } else {
                $change = $b > 0 ? round(($a - $b) / $b * 100, 1) : null;
                $list[] = ['difference' => $difference($a - $b), 'change' => $change === null ? null : Tip::percent($change), 'tone' => Tip::tone($a - $b)];
            }
        }

        return $list;
    }

    /** 1, 2, 2.5 or 5 × 10ⁿ at or above the value (1 for nothing). */
    public static function niceStep(float $raw): float
    {
        if ($raw <= 0) {
            return 1.0;
        }

        $power = 10 ** floor(log10($raw));
        foreach ([1, 2, 2.5, 5, 10] as $factor) {
            if ($raw <= $factor * $power) {
                return $factor * $power;
            }
        }

        return 10 * $power;
    }

    /** Yen for an axis: 「0」「5,000」「120万」「1.5億」. */
    public static function yenAxis(int|float $value): string
    {
        $value = (float) $value;

        return match (true) {
            $value >= 100_000_000 => self::trim($value / 100_000_000).'億',
            $value >= 10_000 => self::trim($value / 10_000).'万',
            default => number_format($value),
        };
    }

    public static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }

    /**
     * The paths of a line: each run of months with a value is its own smooth path (never across a month without one);
     * the segments to and from an incomplete month are dotted.
     *
     * @param  list<array{x: float, y: float, partial: bool}|null>  $points  by month
     * @return array{0: string, 1: string, 2: string} solid line, dotted line, area
     */
    private static function paths(array $points): array
    {
        $solid = $dotted = $area = [];
        $run = [];
        foreach ([...$points, null] as $point) {
            if ($point !== null) {
                $run[] = $point;

                continue;
            }
            if ($run === []) {
                continue;
            }

            $segments = self::smoothSegments($run);
            if ($segments === []) {
                // a month alone: a point (its mark shows it)
                $solid[] = sprintf('M %.1f %.1f', $run[0]['x'], $run[0]['y']);
            }
            foreach ($segments as $index => $segment) {
                if ($run[$index]['partial'] || $run[$index + 1]['partial']) {
                    $dotted[] = $segment;
                } else {
                    $solid[] = $segment;
                }
            }
            if ($segments !== []) {
                $area[] = self::joined($segments).sprintf(' L %.1f %d L %.1f %d Z', end($run)['x'], self::HEIGHT, $run[0]['x'], self::HEIGHT);
            }
            $run = [];
        }

        return [self::joined($solid), self::joined($dotted), implode(' ', $area)];
    }

    /**
     * Segments as one path: a segment that starts where the one before ended continues it (no new 「M」), so a run
     * stays one stroke with round joins.
     *
     * @param  list<string>  $segments
     */
    private static function joined(array $segments): string
    {
        $path = '';
        $end = null;
        foreach ($segments as $segment) {
            if (preg_match('/^M (\S+ \S+) C (.*)$/', $segment, $parts) === 1) {
                $path .= ($end === $parts[1] ? ' C ' : ($path === '' ? '' : ' ').'M '.$parts[1].' C ').$parts[2];
                $end = preg_match('/(\S+ \S+)$/', $parts[2], $last) === 1 ? $last[1] : null;
            } else {
                $path .= ($path === '' ? '' : ' ').$segment;
                $end = null;
            }
        }

        return $path;
    }

    /**
     * A monotone cubic path through the points (Fritsch–Carlson), one segment per pair of neighbours (「M x y C …」):
     * smooth, and between two points it stays between them — never below 0 or above the top.
     *
     * @param  list<array{x: float, y: float, partial: bool}>  $points
     * @return list<string>
     */
    private static function smoothSegments(array $points): array
    {
        $count = count($points);
        if ($count < 2) {
            return [];
        }

        $slopes = [];
        for ($i = 0; $i < $count - 1; $i++) {
            $slopes[] = ($points[$i + 1]['y'] - $points[$i]['y']) / ($points[$i + 1]['x'] - $points[$i]['x']);
        }

        $tangents = [$slopes[0]];
        for ($i = 1; $i < $count - 1; $i++) {
            $tangents[] = $slopes[$i - 1] * $slopes[$i] <= 0 ? 0.0 : ($slopes[$i - 1] + $slopes[$i]) / 2;
        }
        $tangents[] = $slopes[$count - 2];

        for ($i = 0; $i < $count - 1; $i++) {
            if ($slopes[$i] == 0.0) {
                $tangents[$i] = 0.0;
                $tangents[$i + 1] = 0.0;

                continue;
            }
            $a = $tangents[$i] / $slopes[$i];
            $b = $tangents[$i + 1] / $slopes[$i];
            $sum = $a * $a + $b * $b;
            if ($sum > 9) {
                $scale = 3 / sqrt($sum);
                $tangents[$i] = $scale * $a * $slopes[$i];
                $tangents[$i + 1] = $scale * $b * $slopes[$i];
            }
        }

        $segments = [];
        for ($i = 0; $i < $count - 1; $i++) {
            $dx = ($points[$i + 1]['x'] - $points[$i]['x']) / 3;
            $segments[] = sprintf(
                'M %.1f %.1f C %.1f %.1f, %.1f %.1f, %.1f %.1f',
                $points[$i]['x'], $points[$i]['y'],
                $points[$i]['x'] + $dx, $points[$i]['y'] + $tangents[$i] * $dx,
                $points[$i + 1]['x'] - $dx, $points[$i + 1]['y'] - $tangents[$i + 1] * $dx,
                $points[$i + 1]['x'], $points[$i + 1]['y'],
            );
        }

        return $segments;
    }
}
