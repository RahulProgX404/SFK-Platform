<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Money;

/**
 * 端数区分 — how fractional yen are rounded. Configured per partner (customer and supplier side).
 *
 * BR-TX-01 / BR-TX-02: a blank setting means truncation (切捨).
 * Behaviour matches kintone ROUNDDOWN / ROUNDUP / ROUND (towards / away from zero).
 */
enum RoundingMode: string
{
    /** 切捨 — drop the fraction (towards zero). */
    case Truncate = '切捨';

    /** 切上 — any fraction rounds away from zero. */
    case RoundUp = '切上';

    /** 四捨五入 — .5 and above rounds away from zero. */
    case HalfUp = '四捨五入';

    public static function fromLabel(?string $label): self
    {
        $label = $label === null ? '' : trim($label);

        return $label === '' ? self::Truncate : self::from($label);
    }

    public function label(): string
    {
        return $this->value;
    }
}
