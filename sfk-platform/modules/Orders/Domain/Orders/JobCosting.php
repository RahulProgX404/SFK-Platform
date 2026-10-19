<?php

declare(strict_types=1);

namespace Modules\Orders\Domain\Orders;

use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Money\Yen;

/**
 * The calculated fields of 04. 受注入力 (SYSTEM_BRAIN APP_04 formulas.md), from the record's own values: the estimate
 * (BR-OR-02..06), the remaining quantities (BR-OR-09/10), the actual cost and profit (BR-OR-11) and AM/PM (BR-OR-13).
 * Every amount rounds with the customer's 端数区分 (BR-TX-01, blank ⇒ 切捨). Values come as stored or typed (int,
 * float, decimal string or blank = 0); amounts are whole yen.
 *
 * BR-OR-11 is redefined as SYSTEM_BRAIN asks (F, D-04-2): 利益 = 現在売上済金額 (every sales line of the job, without
 * tax) − 実原価合計, instead of the first sales line with tax; 利益率 = 利益 / 売上金額 as in the legacy formula.
 */
final class JobCosting
{
    private const SCALE = 4;

    /** 用紙金額: 枚数 × 用紙単価, or (枚数 / 1000) × kg × 用紙単価 for 計算方法 キロ (BR-OR-02). */
    public static function paperAmount(?string $pricing, mixed $sheets, mixed $kg, mixed $unitPrice, RoundingMode $mode): int
    {
        $amount = $pricing === OrderChoices::PRICING_KG
            ? Yen::multiply(Yen::divide(self::decimal($sheets), '1000'), Yen::multiply(self::decimal($kg), self::decimal($unitPrice)))
            : Yen::multiply(self::decimal($unitPrice), self::decimal($sheets));

        return Yen::round($amount, $mode);
    }

    /** 断裁金額 = 束 × 断裁単価; 金額(刷版) = 版数 × 単価; 製袋 = 単価 × 数量; トムソン… = 単価 × 通し数 (BR-OR-03). */
    public static function product(mixed $a, mixed $b, RoundingMode $mode): int
    {
        return Yen::round(Yen::multiply(self::decimal($a), self::decimal($b)), $mode);
    }

    /** 印刷 金額 = 通し数 × 色数 × 単価 (BR-OR-03). */
    public static function printingAmount(mixed $passes, mixed $colors, mixed $unitPrice, RoundingMode $mode): int
    {
        return Yen::round(Yen::multiply(Yen::multiply(self::decimal($passes), self::decimal($colors)), self::decimal($unitPrice)), $mode);
    }

    /** 小計用: the manual amount when it is more than 0, else the calculated one (BR-OR-04). */
    public static function chosen(int $calculated, mixed $manual, RoundingMode $mode): int
    {
        $manual = self::decimal($manual);

        return bccomp($manual, '0', self::SCALE) > 0 ? Yen::round($manual, $mode) : $calculated;
    }

    /**
     * A row of 用紙情報 with its amounts.
     *
     * @param  array<string, mixed>  $row
     * @return array{paper_amount: int, paper_subtotal: int, cutting_amount: int, cutting_subtotal: int}
     */
    public static function paperRow(array $row, RoundingMode $mode): array
    {
        $paper = self::paperAmount(self::text($row['pricing'] ?? null), $row['sheets'] ?? null, $row['weight_kg'] ?? null, $row['unit_price'] ?? null, $mode);
        $cutting = self::product($row['bundles'] ?? null, $row['cutting_unit_price'] ?? null, $mode);

        return [
            'paper_amount' => $paper,
            'paper_subtotal' => self::chosen($paper, $row['manual_amount'] ?? null, $mode),
            'cutting_amount' => $cutting,
            'cutting_subtotal' => self::chosen($cutting, $row['cutting_manual_amount'] ?? null, $mode),
        ];
    }

    /**
     * A row of 印刷 with its amounts.
     *
     * @param  array<string, mixed>  $row
     * @return array{amount: int, subtotal: int}
     */
    public static function printingRow(array $row, RoundingMode $mode): array
    {
        $amount = self::printingAmount($row['passes'] ?? null, $row['colors'] ?? null, $row['unit_price'] ?? null, $mode);

        return ['amount' => $amount, 'subtotal' => self::chosen($amount, $row['manual_amount'] ?? null, $mode)];
    }

    /**
     * A row of トムソン・NO・シルク・箔 with its amounts.
     *
     * @param  array<string, mixed>  $row
     * @return array{amount: int, subtotal: int}
     */
    public static function finishingRow(array $row, RoundingMode $mode): array
    {
        $amount = self::product($row['unit_price'] ?? null, $row['passes'] ?? null, $mode);

        return ['amount' => $amount, 'subtotal' => self::chosen($amount, $row['manual_amount'] ?? null, $mode)];
    }

    /**
     * Every calculated field of the record.
     *
     * @param  array<string, mixed>  $order  the record's values by attribute
     * @param  iterable<array<string, mixed>>  $papers  rows of 用紙情報
     * @param  iterable<array<string, mixed>>  $printings  rows of 印刷
     * @param  iterable<array<string, mixed>>  $finishings  rows of トムソン・NO・シルク・箔
     * @return array{manufacturing_remaining: string, sales_remaining: string, paper_subtotal: int, plate_amount: int, bag_amount: int, bag_subtotal: int, estimated_cost_total: int, estimated_profit: int, purchase_cost_total: int, actual_cost_total: int, actual_sales_amount: int, profit: int, profit_rate: string}
     */
    public static function totals(array $order, iterable $papers, iterable $printings, iterable $finishings, RoundingMode $mode): array
    {
        $paperTotal = 0;
        $cuttingTotal = 0;
        foreach ($papers as $row) {
            $amounts = self::paperRow($row, $mode);
            $paperTotal += $amounts['paper_subtotal'];
            $cuttingTotal += $amounts['cutting_subtotal'];
        }

        $printingTotal = 0;
        foreach ($printings as $row) {
            $printingTotal += self::printingRow($row, $mode)['subtotal'];
        }

        $finishingTotal = 0;
        foreach ($finishings as $row) {
            $finishingTotal += self::finishingRow($row, $mode)['subtotal'];
        }

        // 小計 = Σ用紙金額(小計用) + デザイン料 + Σ断裁金額(小計用)
        $paperSubtotal = $paperTotal + self::yen($order['design_fee'] ?? null) + $cuttingTotal;
        $plateAmount = self::product($order['plate_unit_price'] ?? null, $order['plate_count'] ?? null, $mode);
        $bagAmount = self::product($order['bag_unit_price'] ?? null, $order['quantity'] ?? null, $mode);
        $bagSubtotal = self::chosen($bagAmount, $order['bag_manual_amount'] ?? null, $mode);

        // BR-OR-05 / BR-OR-06
        $estimatedCost = $paperSubtotal + $plateAmount + $printingTotal + $finishingTotal + $bagSubtotal
            + self::yen($order['case_cost'] ?? null) + self::yen($order['shipping_cost'] ?? null);
        $salesAmount = self::yen($order['sales_amount'] ?? null);

        // 仕入原価計 (= 仕入合計, BR-OR-07), 実原価合計, 実売上金額, 利益, 利益率 (BR-OR-11)
        $purchaseCost = self::yen($order['purchase_amount'] ?? null) + self::yen($order['purchase_cutting_amount'] ?? null)
            + self::yen($order['purchase_plate_amount'] ?? null) + self::yen($order['purchase_stored_plate_amount'] ?? null)
            + self::yen($order['purchase_spot_color_amount'] ?? null);
        $actualCost = $purchaseCost + self::yen($order['internal_cost'] ?? null);
        $profit = self::yen($order['sold_amount'] ?? null) - $actualCost;

        $quantity = self::decimal($order['quantity'] ?? null);

        return [
            'manufacturing_remaining' => bcsub($quantity, self::decimal($order['manufactured_quantity'] ?? null), self::SCALE),
            'sales_remaining' => bcsub($quantity, self::decimal($order['sold_quantity'] ?? null), self::SCALE),
            'paper_subtotal' => $paperSubtotal,
            'plate_amount' => $plateAmount,
            'bag_amount' => $bagAmount,
            'bag_subtotal' => $bagSubtotal,
            'estimated_cost_total' => $estimatedCost,
            'estimated_profit' => $salesAmount - $estimatedCost,
            'purchase_cost_total' => $purchaseCost,
            'actual_cost_total' => $actualCost,
            'actual_sales_amount' => $salesAmount - $purchaseCost,
            'profit' => $profit,
            'profit_rate' => self::profitRate($profit, $salesAmount),
        ];
    }

    /** 利益率: IF(売上金額 = 0, 0, ROUNDUP(利益 / 売上金額 × 100, 2)) — ROUNDUP away from zero. */
    public static function profitRate(int $profit, int $salesAmount): string
    {
        if ($salesAmount === 0) {
            return '0.00';
        }

        $hundredths = Yen::round(Yen::divide($profit * 10000, $salesAmount), RoundingMode::RoundUp);
        $sign = $hundredths < 0 ? '-' : '';
        $hundredths = abs($hundredths);

        return $sign.intdiv($hundredths, 100).'.'.str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Margin of the list summary (集計サマリー): (実売上金額 − 実原価合計) / 実売上金額 in %, one decimal; null when there
     * are no actual sales.
     */
    public static function summaryMargin(int $actualSales, int $actualCost): ?string
    {
        if ($actualSales === 0) {
            return null;
        }

        return number_format((float) Yen::divide(($actualSales - $actualCost) * 100, $actualSales), 1, '.', '');
    }

    /** AM/PM of 時間 (BR-OR-13): before 12:00 ⇒ AM; blank (and 0:00, like the legacy formula) ⇒ "". */
    public static function amPm(?string $time): string
    {
        if ($time === null || preg_match('/^(\d{1,2}):(\d{2})/', $time, $m) !== 1) {
            return '';
        }

        $minutes = (int) $m[1] * 60 + (int) $m[2];

        return match (true) {
            $minutes === 0 => '',
            $minutes < 720 => 'AM',
            default => 'PM',
        };
    }

    /** A stored or typed number as a plain decimal string; blank or invalid = "0". */
    public static function decimal(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return is_finite($value) ? rtrim(rtrim(sprintf('%.10F', $value), '0'), '.') : '0';
        }
        if (is_string($value)) {
            $value = trim(str_replace(',', '', $value));
            if (preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
                return $value;
            }
            if (is_numeric($value)) {
                return self::decimal((float) $value);
            }
        }

        return '0';
    }

    /** A yen amount (whole yen; a typed fraction is cut off). */
    public static function yen(mixed $value): int
    {
        return Yen::round(self::decimal($value), RoundingMode::Truncate);
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
