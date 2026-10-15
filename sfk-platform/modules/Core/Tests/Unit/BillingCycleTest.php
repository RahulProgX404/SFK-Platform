<?php

use Carbon\CarbonImmutable;
use Modules\Core\Domain\Billing\BillingCycle;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Calendar\YearMonth;

dataset('cycles', [
    // closing,          sale date,     expected 月度
    [ClosingDay::Day20, '2026-06-15', '2026年06月'],
    [ClosingDay::Day20, '2026-06-20', '2026年06月'],
    [ClosingDay::Day20, '2026-06-25', '2026年07月'],
    [ClosingDay::Day15, '2026-03-16', '2026年04月'],   // beginner-guide quiz Q4
    [ClosingDay::Day5, '2026-06-06', '2026年07月'],
    [ClosingDay::Day25, '2026-12-26', '2027年01月'],   // year rollover
    [ClosingDay::EndOfMonth, '2026-06-30', '2026年06月'],
    [ClosingDay::AdHoc, '2026-06-30', '2026年06月'],
    [null, '2026-06-30', '2026年06月'],                // blank closing day
]);

it('assigns the billing-cycle month 月度 (BR-AR-01)', function (?ClosingDay $closing, string $date, string $expected) {
    expect(BillingCycle::monthFor(CarbonImmutable::parse($date), $closing)->label())->toBe($expected);
})->with('cycles');

it('lets a per-sale override win over the partner closing day (BR-AR-02)', function () {
    expect(BillingCycle::effectiveClosingDay(ClosingDay::Day20, ClosingDay::AdHoc))->toBe(ClosingDay::AdHoc)
        ->and(BillingCycle::effectiveClosingDay(ClosingDay::Day20, null))->toBe(ClosingDay::Day20);
});

it('derives the default invoice date (BR-SA-10)', function () {
    $today = CarbonImmutable::parse('2026-05-02');

    expect(BillingCycle::defaultInvoiceDate(ClosingDay::Day20, $today)->toDateString())->toBe('2026-05-20')
        ->and(BillingCycle::defaultInvoiceDate(ClosingDay::EndOfMonth, $today)->toDateString())->toBe('2026-04-30') // quiz Q5
        ->and(BillingCycle::defaultInvoiceDate(ClosingDay::EndOfMonth, CarbonImmutable::parse('2026-03-31'))->toDateString())->toBe('2026-02-28')
        ->and(BillingCycle::defaultInvoiceDate(ClosingDay::AdHoc, $today)->toDateString())->toBe('2026-05-02')
        ->and(BillingCycle::defaultInvoiceDate(null, $today)->toDateString())->toBe('2026-05-02');
});

it('parses and formats year-months', function () {
    $ym = YearMonth::parse('2026年12月');

    expect($ym->next()->label())->toBe('2027年01月')
        ->and($ym->previous()->key())->toBe('2026-11')
        ->and(YearMonth::parse('2026-02')->lastDay()->toDateString())->toBe('2026-02-28');
});

it('maps legacy closing-day labels', function () {
    expect(ClosingDay::fromLabel('末日'))->toBe(ClosingDay::EndOfMonth)
        ->and(ClosingDay::fromLabel(''))->toBeNull()
        ->and(ClosingDay::Day25->dayOfMonth())->toBe(25);
});
