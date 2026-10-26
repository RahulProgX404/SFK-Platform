<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Documents;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;

/**
 * 書式 of a field placed on a 帳票: as shown on the record page, or the stored value as a number, an amount or a date
 * (Western or Japanese era). A value the format does not fit (e.g. a text under 日付) is printed as shown.
 */
enum ValueFormat: string
{
    /** 表示どおり: the text of the record page */
    case Display = 'display';
    /** 数値: 1,234 */
    case Number = 'number';
    /** 金額: ¥1,234 */
    case Yen = 'yen';
    /** 日付: 2026/10/26 */
    case Date = 'date';
    /** 日付: 2026年10月26日 */
    case DateJapanese = 'date_jp';
    /** 和暦: 令和8年10月26日 */
    case Wareki = 'wareki';
    /** 日時: 2026/10/26 10:00 */
    case DateTime = 'datetime';

    /** Eras of 和暦 by their first day, newest first. */
    private const ERAS = [['2019-05-01', '令和'], ['1989-01-08', '平成'], ['1926-12-25', '昭和']];

    public function label(): string
    {
        return match ($this) {
            self::Display => '表示どおり',
            self::Number => '数値（1,234）',
            self::Yen => '金額（¥1,234）',
            self::Date => '日付（2026/10/26）',
            self::DateJapanese => '日付（2026年10月26日）',
            self::Wareki => '和暦（令和8年10月26日）',
            self::DateTime => '日時（2026/10/26 10:00）',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function apply(DocumentValue $value): string
    {
        $raw = $value->raw;

        return match ($this) {
            self::Display => $value->text,
            self::Number, self::Yen => self::number($raw, $this === self::Yen) ?? $value->text,
            self::Date, self::DateJapanese, self::Wareki, self::DateTime => self::date($raw, $this) ?? $value->text,
        };
    }

    private static function number(mixed $raw, bool $yen): ?string
    {
        if (is_bool($raw) || ! is_numeric($raw)) {
            return null;
        }

        // as many decimals as the value has (unit prices such as 12.5), at most 4
        $point = strpos((string) $raw, '.');
        $decimals = $point === false ? 0 : min(4, strlen(rtrim(substr((string) $raw, $point + 1), '0')));

        return ($yen ? '¥' : '').number_format((float) $raw, $decimals);
    }

    private static function date(mixed $raw, self $format): ?string
    {
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) === 1) {
            try {
                $raw = new DateTimeImmutable($raw);
            } catch (Exception) {
                return null;
            }
        }
        if (! $raw instanceof DateTimeInterface) {
            return null;
        }

        return match ($format) {
            self::DateJapanese => $raw->format('Y年n月j日'),
            self::Wareki => self::wareki($raw),
            self::DateTime => $raw->format('Y/m/d H:i'),
            default => $raw->format('Y/m/d'),
        };
    }

    private static function wareki(DateTimeInterface $date): string
    {
        foreach (self::ERAS as [$start, $era]) {
            if ($date->format('Y-m-d') >= $start) {
                $year = (int) $date->format('Y') - (int) substr($start, 0, 4) + 1;

                return $era.($year === 1 ? '元' : (string) $year).'年'.$date->format('n月j日');
            }
        }

        return $date->format('Y年n月j日');
    }
}
