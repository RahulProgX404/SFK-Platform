<?php

declare(strict_types=1);

namespace Modules\Sales\Domain\Documents;

use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Tax\TaxRate;

/**
 * Amounts of a document or a month by tax rate: the subtotal per rate (10%対象小計 / 8%対象小計 / 非課税対象小計 /
 * 対象外小計) and the tax per rate on that subtotal, rounded by the customer's 端数区分 (BR-TX-04, BR-TX-06; 非課税 and
 * 対象外 have no tax).
 */
final readonly class TaxBreakdown
{
    /**
     * @param  array<string, int>  $subtotals  TaxRate value => yen
     * @param  array<string, int>  $taxes  TaxRate value => yen
     */
    private function __construct(public array $subtotals, public array $taxes) {}

    /**
     * @param  iterable<array{tax_rate: string|TaxRate|null, amount: int|string|null}>  $lines
     */
    public static function of(iterable $lines, RoundingMode $rounding): self
    {
        $subtotals = array_fill_keys(array_map(fn (TaxRate $rate): string => $rate->value, TaxRate::cases()), 0);

        foreach ($lines as $line) {
            $rate = $line['tax_rate'] instanceof TaxRate ? $line['tax_rate'] : TaxRate::tryFrom((string) $line['tax_rate']);
            $subtotals[($rate ?? TaxRate::Standard)->value] += (int) ($line['amount'] ?? 0);
        }

        $taxes = [];
        foreach (TaxRate::cases() as $rate) {
            $taxes[$rate->value] = $rate->taxOn($subtotals[$rate->value], $rounding);
        }

        return new self($subtotals, $taxes);
    }

    /** 納品金額合計(税抜) / 今回御買上額(税抜): every row. */
    public function subtotal(): int
    {
        return array_sum($this->subtotals);
    }

    /** 消費税合計 */
    public function tax(): int
    {
        return array_sum($this->taxes);
    }

    public function subtotalFor(TaxRate $rate): int
    {
        return $this->subtotals[$rate->value];
    }

    public function taxFor(TaxRate $rate): int
    {
        return $this->taxes[$rate->value];
    }
}
