<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Money;

use InvalidArgumentException;

/**
 * Exact yen arithmetic. Amounts are integers (whole yen); intermediate values are decimal strings
 * computed with bcmath, never floats, so ¥0.5 boundaries round deterministically.
 */
final class Yen
{
    private const SCALE = 10;

    /** Round a decimal amount to whole yen using the given rule. */
    public static function round(int|string $amount, RoundingMode $mode): int
    {
        $value = self::normalize($amount);
        $negative = str_starts_with($value, '-');
        [$integer, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
        $fraction = rtrim($fraction, '0');
        $integer = $integer === '' ? '0' : $integer;

        $roundAway = match ($mode) {
            RoundingMode::Truncate => false,
            RoundingMode::RoundUp => $fraction !== '',
            RoundingMode::HalfUp => $fraction !== '' && $fraction[0] >= '5',
        };

        $result = (int) ($roundAway ? bcadd($integer, '1', 0) : $integer);

        return $negative ? -$result : $result;
    }

    /** Exact product of two decimals (e.g. quantity × unit price, amount × tax rate). */
    public static function multiply(int|string $a, int|string $b): string
    {
        return bcmul(self::normalize($a), self::normalize($b), self::SCALE);
    }

    /** Exact quotient of two decimals. */
    public static function divide(int|string $a, int|string $b): string
    {
        $divisor = self::normalize($b);

        if (bccomp($divisor, '0', self::SCALE) === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return bcdiv(self::normalize($a), $divisor, self::SCALE);
    }

    private static function normalize(int|string $amount): string
    {
        $value = is_int($amount) ? (string) $amount : trim($amount);

        if (preg_match('/^-?\d+(\.\d+)?$/', $value) !== 1) {
            throw new InvalidArgumentException("Invalid decimal amount [{$value}].");
        }

        return $value;
    }
}
