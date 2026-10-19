<?php

/*
 * 04. 受注入力 calculated fields (SYSTEM_BRAIN APP_04 formulas.md): BR-OR-02..06 (estimate), BR-OR-09/10 (remaining
 * quantities), BR-OR-11 (actual cost and profit, redefined per D-04-2), BR-OR-13 (AM/PM), rounding BR-TX-01.
 */

use Modules\Core\Domain\Money\RoundingMode;
use Modules\Orders\Domain\Orders\JobCosting;

it('prices paper per sheet or per kg and rounds with the customer rule (BR-OR-02, BR-TX-01)', function () {
    // 枚: 用紙単価 × 枚数
    expect(JobCosting::paperAmount('枚', '1001', null, '2.5', RoundingMode::Truncate))->toBe(2502)
        ->and(JobCosting::paperAmount('枚', '1001', null, '2.5', RoundingMode::HalfUp))->toBe(2503)
        ->and(JobCosting::paperAmount(null, '1001', null, '2.5', RoundingMode::RoundUp))->toBe(2503)
        // キロ: (枚数 / 1000) × kg × 用紙単価 = 1.5 × 90.5 × 333 = 45204.75
        ->and(JobCosting::paperAmount('キロ', '1500', '90.5', '333', RoundingMode::Truncate))->toBe(45204)
        ->and(JobCosting::paperAmount('キロ', '1500', '90.5', '333', RoundingMode::HalfUp))->toBe(45205)
        // blank inputs are 0
        ->and(JobCosting::paperAmount('キロ', null, '', null, RoundingMode::HalfUp))->toBe(0);
});

it('calculates cutting, plate, printing, Thomson and bag making (BR-OR-03)', function () {
    expect(JobCosting::product('3', '150.5', RoundingMode::Truncate))->toBe(451)
        ->and(JobCosting::printingAmount('1200', '2', '1.25', RoundingMode::HalfUp))->toBe(3000)
        ->and(JobCosting::printingRow(['passes' => '1000', 'colors' => '3', 'unit_price' => '0.333'], RoundingMode::RoundUp))->toBe(['amount' => 999, 'subtotal' => 999])
        ->and(JobCosting::finishingRow(['unit_price' => '2.5', 'passes' => '1001'], RoundingMode::HalfUp))->toBe(['amount' => 2503, 'subtotal' => 2503]);
});

it('lets a manual amount above 0 replace the calculated one (BR-OR-04)', function () {
    expect(JobCosting::chosen(5000, '4800', RoundingMode::Truncate))->toBe(4800)
        ->and(JobCosting::chosen(5000, '0', RoundingMode::Truncate))->toBe(5000)
        ->and(JobCosting::chosen(5000, null, RoundingMode::Truncate))->toBe(5000)
        ->and(JobCosting::paperRow([
            'pricing' => '枚', 'sheets' => '100', 'unit_price' => '10', 'manual_amount' => '900', 'bundles' => '2', 'cutting_unit_price' => '300',
        ], RoundingMode::Truncate))->toBe(['paper_amount' => 1000, 'paper_subtotal' => 900, 'cutting_amount' => 600, 'cutting_subtotal' => 600]);
});

it('totals the estimate, the remaining quantities and the actual cost and profit (BR-OR-05, 06, 09, 10, 11)', function () {
    $totals = JobCosting::totals(
        [
            'quantity' => '10000', 'manufactured_quantity' => '4000', 'sold_quantity' => '10000', 'design_fee' => 5000,
            'plate_count' => '4', 'plate_unit_price' => '2500', 'bag_unit_price' => '1.5', 'bag_manual_amount' => null,
            'case_cost' => 2000, 'shipping_cost' => 1500, 'sales_amount' => 120000,
            'purchase_amount' => 30000, 'purchase_cutting_amount' => 1000, 'purchase_plate_amount' => 10000, 'purchase_stored_plate_amount' => 0,
            'purchase_spot_color_amount' => 500, 'internal_cost' => 20000, 'sold_amount' => 118000,
        ],
        [['pricing' => '枚', 'sheets' => '2500', 'unit_price' => '8', 'bundles' => '5', 'cutting_unit_price' => '200']],
        [['passes' => '2500', 'colors' => '2', 'unit_price' => '1.2']],
        [['unit_price' => '3', 'passes' => '1000', 'manual_amount' => '2500']],
        RoundingMode::Truncate,
    );

    expect($totals)->toMatchArray([
        'manufacturing_remaining' => '6000.0000',
        'sales_remaining' => '0.0000',
        // 小計 = 用紙 20,000 + デザイン料 5,000 + 断裁 1,000
        'paper_subtotal' => 26000,
        'plate_amount' => 10000,
        'bag_amount' => 15000,
        'bag_subtotal' => 15000,
        // 26,000 + 10,000 + 印刷 6,000 + トムソン(手動) 2,500 + 15,000 + 2,000 + 1,500
        'estimated_cost_total' => 63000,
        'estimated_profit' => 57000,
        'purchase_cost_total' => 41500,
        'actual_cost_total' => 61500,
        'actual_sales_amount' => 78500,
        // 利益 = 現在売上済金額 (tax excluded, all sales lines) − 実原価合計 (D-04-2 fixed)
        'profit' => 56500,
        // ROUNDUP(56,500 / 120,000 × 100, 2) = 47.09 (47.0833…)
        'profit_rate' => '47.09',
    ]);
});

it('rounds 利益率 up away from zero and gives 0 without 売上金額', function () {
    expect(JobCosting::profitRate(1, 3))->toBe('33.34')
        ->and(JobCosting::profitRate(-1, 3))->toBe('-33.34')
        ->and(JobCosting::profitRate(500, 0))->toBe('0.00')
        ->and(JobCosting::summaryMargin(100000, 61500))->toBe('38.5')
        ->and(JobCosting::summaryMargin(0, 100))->toBeNull();
});

it('derives AM/PM from 時間 like the legacy formula (BR-OR-13)', function () {
    expect(JobCosting::amPm('09:30:00'))->toBe('AM')
        ->and(JobCosting::amPm('11:59'))->toBe('AM')
        ->and(JobCosting::amPm('12:00'))->toBe('PM')
        ->and(JobCosting::amPm('00:00'))->toBe('')
        ->and(JobCosting::amPm(null))->toBe('');
});

it('reads typed numbers safely', function () {
    expect(JobCosting::decimal('1,200.5'))->toBe('1200.5')
        ->and(JobCosting::decimal(2.5))->toBe('2.5')
        ->and(JobCosting::decimal(7))->toBe('7')
        ->and(JobCosting::decimal('abc'))->toBe('0')
        ->and(JobCosting::decimal('1e3'))->toBe('1000')
        ->and(JobCosting::yen('1999.9'))->toBe(1999);
});
