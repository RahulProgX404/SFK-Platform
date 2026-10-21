<?php

declare(strict_types=1);

namespace Modules\Purchasing\Domain\Payables;

use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Tax\TaxMode;

/**
 * The monthly roll-forward of a supplier's payable (BR-PU-06/07, BR-TX-07), month after month from the first month with
 * activity:
 *
 *   前月買掛残   = 当月買掛残 of the month before (0 for the first month)
 *   差引残高     = 前月買掛残 − 当月支払額
 *   消費税額     = PayableTax on 仕入額 (+ 消費税端数調整)
 *   当月合計金額 = 仕入額 (+ 消費税額 for 外)
 *   当月買掛残   = 差引残高 + 当月合計金額
 *
 * A month without activity still gets a row while the balance is open (> 0), up to `$until` (the legacy 当月 / 前月
 * 未作成レコード作成 buttons).
 */
final class PayableRoll
{
    /** Purchase figures summed per month. */
    public const PURCHASE_FIGURES = ['purchase_amount', 'cutting_amount', 'plate_amount', 'stored_plate_amount', 'spot_color_amount'];

    /**
     * @param  array<string, array<string, int>>  $purchases  YYYY-MM => PURCHASE_FIGURES and category columns (PayableCategories)
     * @param  array<string, int>  $payments  YYYY-MM => 支払額
     * @param  array<string, int>  $adjustments  YYYY-MM => 消費税端数調整
     * @return array<string, array<string, int>> YYYY-MM => the figures of payable_months
     */
    public static function months(array $purchases, array $payments, array $adjustments, ?TaxMode $mode, RoundingMode $rounding, YearMonth $until): array
    {
        $active = array_keys($purchases + $payments + $adjustments);
        if ($active === []) {
            return [];
        }
        sort($active);

        $month = YearMonth::parse($active[0]);
        $last = $active[array_key_last($active)];
        $rows = [];
        $balance = 0;

        while (true) {
            $key = $month->key();
            if ($key > $last && ($balance <= 0 || $key > $until->key())) {
                break;
            }

            $hasActivity = isset($purchases[$key]) || isset($payments[$key]) || isset($adjustments[$key]);
            if ($hasActivity || $balance !== 0) {
                $figures = $purchases[$key] ?? [];
                $amount = $figures['purchase_amount'] ?? 0;
                $paid = $payments[$key] ?? 0;
                $adjustment = $adjustments[$key] ?? 0;
                $tax = PayableTax::tax($amount, $mode, $rounding, $adjustment);
                $net = $balance - $paid;
                $total = PayableTax::monthTotal($amount, $tax, $mode);

                $row = [
                    'previous_balance' => $balance,
                    'paid' => $paid,
                    'net_balance' => $net,
                    'tax_amount' => $tax,
                    'tax_adjustment' => $adjustment,
                    'month_total' => $total,
                    'closing_balance' => $net + $total,
                ];
                foreach ([...self::PURCHASE_FIGURES, ...PayableCategories::columns()] as $column) {
                    $row[$column] = $figures[$column] ?? 0;
                }
                $rows[$key] = $row;
                $balance = $net + $total;
            }

            $month = $month->next();
        }

        return $rows;
    }
}
