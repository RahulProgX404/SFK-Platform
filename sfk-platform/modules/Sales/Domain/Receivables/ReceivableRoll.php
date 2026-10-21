<?php

declare(strict_types=1);

namespace Modules\Sales\Domain\Receivables;

use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Tax\TaxRate;
use Modules\Sales\Domain\Documents\TaxBreakdown;

/**
 * The monthly roll-forward of a customer's receivable (BR-AR-03, BR-AR-05, BR-TX-06), month after month from the first
 * month with activity:
 *
 *   前月売掛残 = 当月売掛残 of the month before (0 for the first month)
 *   差引残高   = 前月売掛残 − 当月入金額
 *   消費税額   = Σ per rate round(売上額 of the rate × rate) by the customer's 端数区分
 *   当月合計   = 売上額 + 消費税額 + 消費税端数調整
 *   当月売掛残 = 差引残高 + 当月合計
 *
 * A month without activity still gets a row while the balance is open (> 0 — 次月分判定 「要作成」), up to `$until`.
 */
final class ReceivableRoll
{
    /**
     * @param  array<string, list<array{tax_rate: string, amount: int}>>  $sales  YYYY-MM => sale lines
     * @param  array<string, int>  $receipts  YYYY-MM => 入金 + 手数料 + 歩引き
     * @param  array<string, int>  $adjustments  YYYY-MM => 消費税端数調整
     * @return array<string, array{previous_balance: int, received: int, net_balance: int, sales_amount: int, sales_standard: int, sales_reduced: int, sales_exempt: int, sales_out_of_scope: int, tax_standard: int, tax_reduced: int, tax_amount: int, tax_adjustment: int, month_total: int, closing_balance: int}>
     */
    public static function months(array $sales, array $receipts, array $adjustments, RoundingMode $rounding, YearMonth $until): array
    {
        $active = array_keys($sales + $receipts + $adjustments);
        if ($active === []) {
            return [];
        }
        sort($active);

        $month = YearMonth::parse($active[0]);
        $last = YearMonth::parse($active[array_key_last($active)]);
        $rows = [];
        $balance = 0;

        while (true) {
            $key = $month->key();
            $hasActivity = isset($sales[$key]) || isset($receipts[$key]) || isset($adjustments[$key]);
            $afterLast = $month->key() > $last->key();

            if ($afterLast && ($balance <= 0 || $month->key() > $until->key())) {
                break;
            }

            if ($hasActivity || $balance !== 0) {
                $breakdown = TaxBreakdown::of($sales[$key] ?? [], $rounding);
                $received = $receipts[$key] ?? 0;
                $adjustment = $adjustments[$key] ?? 0;
                $net = $balance - $received;
                $total = $breakdown->subtotal() + $breakdown->tax() + $adjustment;

                $rows[$key] = [
                    'previous_balance' => $balance,
                    'received' => $received,
                    'net_balance' => $net,
                    'sales_amount' => $breakdown->subtotal(),
                    'sales_standard' => $breakdown->subtotalFor(TaxRate::Standard),
                    'sales_reduced' => $breakdown->subtotalFor(TaxRate::Reduced),
                    'sales_exempt' => $breakdown->subtotalFor(TaxRate::NonTaxable),
                    'sales_out_of_scope' => $breakdown->subtotalFor(TaxRate::OutOfScope),
                    'tax_standard' => $breakdown->taxFor(TaxRate::Standard),
                    'tax_reduced' => $breakdown->taxFor(TaxRate::Reduced),
                    'tax_amount' => $breakdown->tax(),
                    'tax_adjustment' => $adjustment,
                    'month_total' => $total,
                    'closing_balance' => $net + $total,
                ];
                $balance = $net + $total;
            }

            $month = $month->next();
        }

        return $rows;
    }
}
