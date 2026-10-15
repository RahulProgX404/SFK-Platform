<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Billing;

/**
 * 請求締日 — a customer's billing closing day (01 取引先マスタ). Values are the legacy labels.
 */
enum ClosingDay: string
{
    case Day5 = '5日';
    case Day10 = '10日';
    case Day15 = '15日';
    case Day20 = '20日';
    case Day25 = '25日';
    /** 末日 — end of month. */
    case EndOfMonth = '末日';
    /** 随時請求 — ad-hoc billing; each sale carries its own billing date. */
    case AdHoc = '随時請求';

    /** Blank in the legacy system means "no closing day set" → null. */
    public static function fromLabel(?string $label): ?self
    {
        $label = $label === null ? '' : trim($label);

        return $label === '' ? null : self::from($label);
    }

    /** Day of month for fixed closing days (5/10/15/20/25), null otherwise. */
    public function dayOfMonth(): ?int
    {
        return match ($this) {
            self::Day5 => 5,
            self::Day10 => 10,
            self::Day15 => 15,
            self::Day20 => 20,
            self::Day25 => 25,
            self::EndOfMonth, self::AdHoc => null,
        };
    }
}
