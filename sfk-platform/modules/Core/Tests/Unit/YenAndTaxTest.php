<?php

use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Money\Yen;
use Modules\Core\Domain\Tax\TaxRate;

dataset('rounding', [
    // amount,     切捨,   切上,   四捨五入
    ['1234.1', 1234, 1235, 1234],
    ['1234.5', 1234, 1235, 1235],
    ['1234.9', 1234, 1235, 1235],
    ['1234', 1234, 1234, 1234],
    ['1234.0000', 1234, 1234, 1234],
    ['-1234.7', -1234, -1235, -1235], // credit notes round towards / away from zero like the legacy system
]);

it('rounds yen like the legacy system ROUNDDOWN / ROUNDUP / ROUND', function (string $amount, int $truncate, int $up, int $halfUp) {
    expect(Yen::round($amount, RoundingMode::Truncate))->toBe($truncate)
        ->and(Yen::round($amount, RoundingMode::RoundUp))->toBe($up)
        ->and(Yen::round($amount, RoundingMode::HalfUp))->toBe($halfUp);
})->with('rounding');

it('treats a blank rounding setting as 切捨 (BR-TX-01)', function () {
    expect(RoundingMode::fromLabel(null))->toBe(RoundingMode::Truncate)
        ->and(RoundingMode::fromLabel(''))->toBe(RoundingMode::Truncate)
        ->and(RoundingMode::fromLabel('四捨五入'))->toBe(RoundingMode::HalfUp);
});

it('computes tax on a tax-exclusive amount (外税)', function () {
    // SYSTEM_BRAIN example: ¥12,345 × 10% = ¥1,234.5
    expect(TaxRate::Standard->taxOn(12345, RoundingMode::Truncate))->toBe(1234)
        ->and(TaxRate::Standard->taxOn(12345, RoundingMode::RoundUp))->toBe(1235)
        ->and(TaxRate::Standard->taxOn(12345, RoundingMode::HalfUp))->toBe(1235)
        ->and(TaxRate::Reduced->taxOn(1000, RoundingMode::Truncate))->toBe(80)
        ->and(TaxRate::NonTaxable->taxOn(1000, RoundingMode::Truncate))->toBe(0);
});

it('computes tax contained in a tax-inclusive amount (内税)', function () {
    // ¥10,000 incl. 10%: 10000 − 10000/1.1 = 909.09…
    expect(TaxRate::Standard->taxIncludedIn(10000, RoundingMode::Truncate))->toBe(909)
        ->and(TaxRate::Standard->taxIncludedIn(10000, RoundingMode::RoundUp))->toBe(910)
        ->and(TaxRate::OutOfScope->taxIncludedIn(10000, RoundingMode::Truncate))->toBe(0);
});

it('accepts the full-width ８% label found in legacy app 10 (C-07)', function () {
    expect(TaxRate::fromLabel('８%'))->toBe(TaxRate::Reduced)
        ->and(TaxRate::fromLabel('10%'))->toBe(TaxRate::Standard);
});

it('rejects non-decimal input', function () {
    Yen::round('12,345', RoundingMode::Truncate);
})->throws(InvalidArgumentException::class);
