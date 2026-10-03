<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Tax;

use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Money\Yen;

/**
 * 税率 — consumption-tax categories used on sales lines and documents (BR-TX-03).
 * Values are the labels stored in kintone, so migrated data maps directly.
 */
enum TaxRate: string
{
    case Standard = '10%';
    case Reduced = '8%';
    /** 非課税 — non-taxable (e.g. stamps, postcards). */
    case NonTaxable = '非課税';
    /** 対象外 — outside the scope of consumption tax. */
    case OutOfScope = '対象外';

    /**
     * Accepts kintone labels including the full-width "８%" found in app 10 (contradiction C-07).
     */
    public static function fromLabel(string $label): self
    {
        $normalized = mb_convert_kana(trim($label), 'a');

        return self::from($normalized);
    }

    /** Decimal rate as a string, e.g. "0.10". */
    public function rate(): string
    {
        return match ($this) {
            self::Standard => '0.10',
            self::Reduced => '0.08',
            self::NonTaxable, self::OutOfScope => '0',
        };
    }

    /** 外税: tax on a tax-exclusive amount, e.g. ¥12,345 × 10% → ¥1,234 (切捨). BR-TX-04/05. */
    public function taxOn(int $netAmount, RoundingMode $rounding): int
    {
        return Yen::round(Yen::multiply($netAmount, $this->rate()), $rounding);
    }

    /** 内税: tax contained in a tax-inclusive amount: gross − gross ÷ (1 + rate). BR-TX-07. */
    public function taxIncludedIn(int $grossAmount, RoundingMode $rounding): int
    {
        if ($this->rate() === '0') {
            return 0;
        }

        $net = Yen::divide($grossAmount, bcadd('1', $this->rate(), 10));

        return Yen::round(bcsub((string) $grossAmount, $net, 10), $rounding);
    }
}
