<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Dashboard;

use Closure;

/**
 * A line chart of the 12 months of a year (personal-growth.png, bug.md 2026-11-07; drawn by
 * analytics::widgets.partials.trend-chart): one y axis from 0 to a round top with 4 steps, smooth lines that never
 * swing past their points (monotone cubic) over a soft area. Months without a value yet (the rest of this year) are
 * left out, so a line ends at the last month that has passed. The plot is a 1000 × 300 box stretched to the card;
 * the labels and the hover marks are HTML, placed in % of it.
 */
final class TrendChart
{
    public const WIDTH = 1000;

    public const HEIGHT = 300;

    public const MONTHS = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];

    /**
     * @param  list<array{key: string, label: string, color: string, values: array<int, int|float|null>}>  $series  values by month 1–12
     * @param  Closure(int|float): string  $axisLabel  short value for the y axis (e.g. 「120万」)
     * @param  Closure(int|float): string  $valueLabel  full value for the tooltip and the table (e.g. 「¥1,200,000」)
     * @return array{ticks: list<array{label: string, top: float}>, series: list<array{key: string, label: string, color: string, line: string, area: string, points: list<array{left: float, top: float}|null>, values: list<string|null>}>, months: list<string>, lefts: list<float>, empty: bool}
     */
    public static function build(array $series, Closure $axisLabel, Closure $valueLabel): array
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
            $points = [];
            $values = [];
            foreach (range(1, 12) as $month) {
                $value = $line['values'][$month] ?? null;
                $values[] = $value === null ? null : $valueLabel($value);
                $points[] = $value === null ? null : [
                    'x' => ($month - 1) / 11 * self::WIDTH,
                    'y' => self::HEIGHT - ($top > 0 ? (float) $value / $top : 0) * self::HEIGHT,
                ];
            }

            $drawn = array_values(array_filter($points));
            $path = self::smoothPath($drawn);
            $built[] = [
                'key' => $line['key'],
                'label' => $line['label'],
                'color' => $line['color'],
                'line' => $path,
                'area' => $drawn === [] ? '' : $path.sprintf(' L %.1f %d L %.1f %d Z', end($drawn)['x'], self::HEIGHT, $drawn[0]['x'], self::HEIGHT),
                'points' => array_map(fn (?array $point): ?array => $point === null ? null : [
                    'left' => round($point['x'] / self::WIDTH * 100, 3),
                    'top' => round($point['y'] / self::HEIGHT * 100, 3),
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
        ];
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
     * A monotone cubic path through the points (Fritsch–Carlson): smooth, and between two points it stays between them —
     * never below 0 or above the top.
     *
     * @param  list<array{x: float, y: float}>  $points
     */
    private static function smoothPath(array $points): string
    {
        $count = count($points);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return sprintf('M %.1f %.1f', $points[0]['x'], $points[0]['y']);
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

        $path = sprintf('M %.1f %.1f', $points[0]['x'], $points[0]['y']);
        for ($i = 0; $i < $count - 1; $i++) {
            $dx = ($points[$i + 1]['x'] - $points[$i]['x']) / 3;
            $path .= sprintf(
                ' C %.1f %.1f, %.1f %.1f, %.1f %.1f',
                $points[$i]['x'] + $dx, $points[$i]['y'] + $tangents[$i] * $dx,
                $points[$i + 1]['x'] - $dx, $points[$i + 1]['y'] - $tangents[$i + 1] * $dx,
                $points[$i + 1]['x'], $points[$i + 1]['y'],
            );
        }

        return $path;
    }
}
