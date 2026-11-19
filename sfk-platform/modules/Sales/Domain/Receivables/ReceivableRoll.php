<?php

declare(strict_types=1);

namespace Modules\Sales\Domain\Receivables;

use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Tax\TaxRate;
use Modules\Sales\Domain\Documents\TaxBreakdown;

/**
 * The monthly roll-forward of a customer's receivable (BR-AR-03, BR-AR-05, BR-TX-06; docs/receivables.md), month after
 * month from the first month with activity:
 *
 *   前月売掛残 = opening(): 当月売掛残 of the month before (0 for the first month)
 *   差引残高   = 前月売掛残 − 当月入金額
 *   消費税額   = Σ per rate round(売上額 of the rate × rate) by the customer's 端数区分
 *   当月合計   = 売上額 + 消費税額 + 消費税端数調整
 *   当月売掛残 = 差引残高 + 当月合計
 *
 * A month without activity still gets a row while the balance is not 0 — a credit (overpayment, payment before the
 * invoice) carries like a debt, up to `$until` (次月分判定 「要作成」; the legacy system carried only balances > 0, so
 * credits vanished — defect 1). Only a balance of exactly 0 without further activity stops the rows; later activity
 * starts again from 0.
 */
final class ReceivableRoll
{
    /**
     * @param  array<string, list<array{tax_rate: string, amount: int}>>  $sales  YYYY-MM => sale lines
     * @param  array<string, int>  $receipts  YYYY-MM => 入金 + 手数料 + 歩引き
     * @param  array<string, int>  $adjustments  YYYY-MM => 消費税端数調整
     * @return array<string, array{previous_balance: int, received: int, net_balance: int, sales_amount: int, sales_standard: int, sales_reduced: int, sales_exempt: int, sales_out_of_scope: int, tax_standard: int, tax_reduced: int, tax_amount: int, tax_adjustment: int, month_total: int, closing_balance: int}>
     */
    public static function months(array $sales, array $receipts, array $adjustments, RoundingMode $rounding, YearMonth $until, int $customerId = 0): array
    {
        $active = array_keys($sales + $receipts + $adjustments);
        if ($active === []) {
            return [];
        }
        sort($active);

        $month = YearMonth::parse($active[0]);
        $last = $active[array_key_last($active)];
        $end = max($last, $until->key());
        $rows = [];
        $closings = [];

        while ($month->key() <= $end) {
            $key = $month->key();
            $hasActivity = isset($sales[$key]) || isset($receipts[$key]) || isset($adjustments[$key]);
            $balance = self::opening($closings, $month, $customerId);

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
                $closings[$key] = $net + $total;
            }

            $month = $month->next();
        }

        return $rows;
    }

    /**
     * 前月売掛残 of `$period` — THE definition, used by the roll above and, over the stored rows, by
     * ReceivableLedger::openingBalance() (every screen, batch and report): 当月売掛残 of the month right before; 0 when
     * there is no earlier month, or when the latest earlier month closed at exactly 0 (the rows may stop there). Any
     * other hole is a ReceivableGap — never read as 0.
     *
     * @param  array<string, int>  $closings  YYYY-MM => 当月売掛残 of the customer's months (any order; later ones ignored)
     *
     * @throws ReceivableGap
     */
    public static function opening(array $closings, YearMonth $period, int $customerId = 0): int
    {
        $previous = $period->previous()->key();
        if (array_key_exists($previous, $closings)) {
            return $closings[$previous];
        }

        $earlier = array_filter(array_keys($closings), fn (string $key): bool => $key < $previous);
        if ($earlier === []) {
            return 0;
        }

        $latest = max($earlier);
        if ($closings[$latest] !== 0) {
            throw new ReceivableGap($customerId, $period, YearMonth::parse($latest), $closings[$latest]);
        }

        return 0;
    }
}
