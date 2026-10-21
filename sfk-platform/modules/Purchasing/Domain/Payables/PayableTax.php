<?php

declare(strict_types=1);

namespace Modules\Purchasing\Domain\Payables;

use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Tax\TaxMode;
use Modules\Core\Domain\Tax\TaxRate;

/**
 * 消費税額 and 当月合計金額 of a payable month (BR-TX-07, the 23. 買掛管理 formulas), by the supplier's 消費税区分 and
 * 端数区分 (blank ⇒ 切捨, BR-TX-02); 計算区分 is not used:
 *
 *   外: round(仕入額 × 10%) + 消費税端数調整; 当月合計金額 = 仕入額 + 消費税額
 *   内: round(仕入額 − 仕入額 / 1.1) + 消費税端数調整; 当月合計金額 = 仕入額 (the tax is in it)
 *   無 (or blank): 0; 当月合計金額 = 仕入額
 *
 * Purchases are always taxed at 10% (8% purchases are not supported by the legacy formulas, a decision still open — F).
 */
final class PayableTax
{
    public static function tax(int $purchases, ?TaxMode $mode, RoundingMode $rounding, int $adjustment): int
    {
        return match ($mode) {
            TaxMode::Exclusive => TaxRate::Standard->taxOn($purchases, $rounding) + $adjustment,
            TaxMode::Inclusive => TaxRate::Standard->taxIncludedIn($purchases, $rounding) + $adjustment,
            default => 0,
        };
    }

    public static function monthTotal(int $purchases, int $tax, ?TaxMode $mode): int
    {
        return $mode === TaxMode::Exclusive ? $purchases + $tax : $purchases;
    }
}
