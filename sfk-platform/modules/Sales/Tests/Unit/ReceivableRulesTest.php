<?php

/*
 * The pure receivable rules (docs/receivables.md): THE opening balance (ReceivableRoll::opening, BR-AR-03/05), the roll
 * carrying credits, and the check of the legacy months before import (LegacyLedgerCheck).
 */

use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Sales\Domain\Receivables\LegacyLedgerCheck;
use Modules\Sales\Domain\Receivables\ReceivableGap;
use Modules\Sales\Domain\Receivables\ReceivableRoll;

it('takes 前月売掛残 from the month right before, 0 before the first month or after a 0 close, and refuses any other hole', function () {
    $closings = ['2026-07' => 912419, '2026-08' => 912419, '2026-09' => -2000];

    expect(ReceivableRoll::opening($closings, YearMonth::of(2026, 10)))->toBe(-2000)
        ->and(ReceivableRoll::opening($closings, YearMonth::of(2026, 8)))->toBe(912419)
        ->and(ReceivableRoll::opening($closings, YearMonth::of(2026, 7)))->toBe(0)
        ->and(ReceivableRoll::opening(['2026-05' => 0], YearMonth::of(2026, 9)))->toBe(0)
        ->and(fn () => ReceivableRoll::opening($closings, YearMonth::of(2026, 11), 629))
        ->toThrow(ReceivableGap::class, '得意先ID 629：2026年11月 の前月がなく、2026年09月 の当月売掛残 -2,000 円');
});

it('carries a credit through months without activity up to the end, and stops only at exactly 0', function () {
    $months = ReceivableRoll::months(['2026-07' => [['tax_rate' => '非課税', 'amount' => 1000]]], ['2026-08' => 1500], [], RoundingMode::Truncate, YearMonth::of(2026, 10));

    expect(array_map(fn (array $row): array => [$row['previous_balance'], $row['closing_balance']], $months))->toBe([
        '2026-07' => [0, 1000],
        '2026-08' => [1000, -500],
        '2026-09' => [-500, -500],
        '2026-10' => [-500, -500],
    ]);

    $settled = ReceivableRoll::months(['2026-07' => [['tax_rate' => '非課税', 'amount' => 1000]]], ['2026-08' => 1000], [], RoundingMode::Truncate, YearMonth::of(2026, 10));
    expect(array_keys($settled))->toBe(['2026-07', '2026-08']);
});

it('reports the legacy gaps, wrong openings and dropped credits instead of copying them', function () {
    expect(LegacyLedgerCheck::issues([
        '2026-07' => ['opening' => 0, 'closing' => 912419],
        '2026-08' => ['opening' => 912419, 'closing' => 912419],
        '2026-09' => ['opening' => 912419, 'closing' => -2000],
    ], YearMonth::of(2026, 11)))->toBe([
        '2026年09月 のマイナス残高（過入金） -2,000 円が 2026年10月 以降に繰り越されていません',
    ]);

    expect(LegacyLedgerCheck::issues([
        '2026-05' => ['opening' => 0, 'closing' => 5000],
        '2026-07' => ['opening' => 0, 'closing' => 5000],
        '2026-08' => ['opening' => 4000, 'closing' => 0],
    ], YearMonth::of(2026, 11)))->toBe([
        '2026年06月〜2026年06月 の月がありません（2026年05月 の当月売掛残 5,000 円が繰り越されていません）',
        '2026年08月 の前月売掛残 4,000 円が 2026年07月 の当月売掛残 5,000 円と一致しません',
    ]);

    // a balance of 0 may stop; a balance in the last month of the export is not a finding
    expect(LegacyLedgerCheck::issues(['2026-03' => ['opening' => 0, 'closing' => 0], '2026-09' => ['opening' => 0, 'closing' => 300]], YearMonth::of(2026, 9)))->toBe([]);
});

it('moves a month by any number of months', function () {
    expect(YearMonth::of(2026, 1)->addMonths(-2)->key())->toBe('2025-11')
        ->and(YearMonth::of(2026, 11)->addMonths(14)->key())->toBe('2028-01')
        ->and(YearMonth::of(2026, 11)->addMonths(0)->key())->toBe('2026-11');
});
