<?php

declare(strict_types=1);

namespace Modules\Analytics\Domain;

/** An amount in few characters for small boxes and chart axes: ¥1.2億, ¥1,234万, ¥9,800 (the full amount goes in the title). */
final class ShortYen
{
    public static function format(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $value = abs($amount);

        return $sign.match (true) {
            $value >= 100_000_000 => '¥'.rtrim(rtrim(number_format($value / 100_000_000, 1), '0'), '.').'億',
            $value >= 100_000 => '¥'.number_format(intdiv($value, 10_000)).'万',
            default => '¥'.number_format($value),
        };
    }

    /**
     * Axis steps from 0 up to at least the largest value (5 steps, round numbers).
     *
     * @return list<int> from the top step down to 0
     */
    public static function axis(int $max, int $steps = 5): array
    {
        if ($max <= 0) {
            return [0];
        }
        $raw = $max / $steps;
        $magnitude = 10 ** (int) floor(log10($raw));
        $step = (int) (match (true) {
            $raw / $magnitude <= 1 => 1,
            $raw / $magnitude <= 2 => 2,
            $raw / $magnitude <= 2.5 => 2.5,
            $raw / $magnitude <= 5 => 5,
            default => 10,
        } * $magnitude);

        return array_reverse(array_map(fn (int $i): int => $i * max(1, $step), range(0, $steps)));
    }
}
