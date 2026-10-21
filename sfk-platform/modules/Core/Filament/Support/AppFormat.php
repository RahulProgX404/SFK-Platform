<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use BackedEnum;
use DateTimeInterface;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Domain\Money\UnitPrice;

/**
 * How app screens show values, like the legacy apps: amounts 「¥1,234」 (「-¥1,234」), unit prices with their
 * decimals, numbers with thousands separators and no trailing zeros, dates 「2026-10-20」, months 「2026年10月」, choices
 * by their label.
 */
final class AppFormat
{
    public static function yen(mixed $amount): string
    {
        if ($amount === null || $amount === '' || ! is_numeric($amount)) {
            return '';
        }

        $amount = (int) $amount;

        return ($amount < 0 ? '-¥' : '¥').number_format(abs($amount));
    }

    public static function price(mixed $price): string
    {
        return UnitPrice::format(is_scalar($price) && $price !== '' ? (string) $price : null);
    }

    public static function number(mixed $value): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
    }

    /** A choice as its label: the value of a choice cast to an enum, else as stored. */
    public static function text(mixed $value): ?string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (is_scalar($value) && $value !== '' ? (string) $value : null);
    }

    public static function date(mixed $date): string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : (is_string($date) ? substr($date, 0, 10) : '');
    }

    /** 「2026年10月」 of a YYYY-MM key */
    public static function month(?string $key): string
    {
        return filled($key) ? YearMonth::parse((string) $key)->label() : '';
    }
}
