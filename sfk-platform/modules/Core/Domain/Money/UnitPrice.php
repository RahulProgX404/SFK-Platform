<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Money;

/**
 * A unit price in yen that may have decimals (e.g. a per-sheet processing rate of ¥0.35), kept as a decimal string.
 * Amounts computed from it are rounded to whole yen with Yen::round(Yen::multiply($quantity, $price), $mode).
 */
final class UnitPrice
{
    /** Decimal places kept (the legacy apps' decimal scale). */
    public const SCALE = 4;

    /** 「¥1,200」, 「¥0.35」; blank for no price. */
    public static function format(int|float|string|null $price): string
    {
        if ($price === null || $price === '') {
            return '';
        }

        $value = number_format((float) $price, self::SCALE, '.', ',');

        return '¥'.rtrim(rtrim($value, '0'), '.');
    }
}
