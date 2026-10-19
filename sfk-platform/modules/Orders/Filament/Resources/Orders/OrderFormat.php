<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Orders;

use DateTimeInterface;
use Modules\Core\Domain\Money\UnitPrice;

/**
 * How 04. 受注入力 shows its values, like the legacy app: amounts 「¥1,234」, numbers with thousands separators and no
 * trailing zeros (sizes with their 2 decimals), units after the value (mm, 枚, 通 …), 利益率 「12.5%」.
 */
final class OrderFormat
{
    /** Units of the legacy number fields, by attribute (form suffix and display). */
    public const UNITS = [
        'expected_hours' => '時間',
        'width' => 'mm', 'height' => 'mm', 'flap' => 'mm',
        'window_width' => 'mm', 'window_height' => 'mm', 'window_from_left' => 'mm', 'window_from_bottom' => 'mm',
        'per_case' => '枚', 'band_sheets' => '枚', 'legacy_size_no' => '号',
        'weight_kg' => 'kg', 'sheets' => '枚', 'bundles' => '束',
        'imposition' => '丁', 'passes' => '通', 'spare_passes' => '通',
    ];

    /** Sizes shown with 2 decimals (legacy displayScale 2). */
    public const SIZES = [
        'width', 'height', 'flap', 'bottom', 'unfolded_width', 'unfolded_height', 'window_width', 'window_height', 'window_from_left',
        'window_from_bottom', 'legacy_size_no',
    ];

    public static function yen(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        $amount = (int) $amount;

        return ($amount < 0 ? '-¥' : '¥').number_format(abs($amount));
    }

    public static function price(mixed $price): string
    {
        return UnitPrice::format(is_scalar($price) ? (string) $price : null);
    }

    /** A decimal number: thousands separators, no trailing zeros (or the given decimals), and the unit. */
    public static function number(mixed $value, ?string $unit = null, ?int $decimals = null): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return '';
        }

        $text = number_format((float) $value, $decimals ?? 4, '.', ',');
        if ($decimals === null) {
            $text = rtrim(rtrim($text, '0'), '.');
        }

        return $unit !== null ? $text.$unit : $text;
    }

    /** A number field by attribute (its legacy unit and decimals). */
    public static function field(string $attribute, mixed $value): string
    {
        return self::number($value, self::UNITS[$attribute] ?? null, in_array($attribute, self::SIZES, true) ? 2 : null);
    }

    public static function percent(mixed $rate): string
    {
        return $rate === null || $rate === '' ? '' : self::number($rate).'%';
    }

    public static function date(mixed $date): string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : (is_string($date) ? substr($date, 0, 10) : '');
    }

    public static function time(mixed $time): string
    {
        return is_string($time) && $time !== '' ? substr($time, 0, 5) : '';
    }
}
