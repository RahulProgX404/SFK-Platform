<?php

declare(strict_types=1);

namespace Modules\Sales\Domain\Receivables;

use Modules\Core\Domain\Calendar\YearMonth;

/**
 * Checks one customer's receivable months of the legacy system before they are replaced (legacy import, docs/receivables.md
 * "Migration"): reports — never repairs or copies — what the legacy month creation got wrong:
 *
 * - a hole between two months although the earlier one did not close at 0 (only balances > 0 were carried, and only one
 *   month back — defects 1 and 5);
 * - 前月売掛残 differing from 当月売掛残 of the month before (rows not updated after a correction — defects 3 and 4);
 * - a last month with a balance ≠ 0 before the end of the export — a credit that vanished (defect 1).
 */
final class LegacyLedgerCheck
{
    /**
     * @param  array<string, array{opening: int|null, closing: int|null}>  $months  YYYY-MM => 前月売掛残 / 当月売掛残
     * @param  YearMonth  $until  the last month of the export
     * @return list<string> the findings, in month order
     */
    public static function issues(array $months, YearMonth $until): array
    {
        ksort($months);
        $issues = [];
        $previous = null;

        foreach ($months as $key => $month) {
            $period = YearMonth::parse($key);
            if ($previous !== null) {
                [$previousKey, $previousMonth] = $previous;
                $expected = YearMonth::parse($previousKey)->next();
                $closing = $previousMonth['closing'];
                if (! $expected->equals($period)) {
                    if ($closing !== null && $closing !== 0) {
                        $issues[] = sprintf('%s〜%s の月がありません（%s の%s %s 円が繰り越されていません）',
                            $expected->label(), $period->previous()->label(), YearMonth::parse($previousKey)->label(), self::balanceName($closing), number_format($closing));
                    }
                } elseif ($closing !== null && $month['opening'] !== null && $month['opening'] !== $closing) {
                    $issues[] = sprintf('%s の前月売掛残 %s 円が %s の当月売掛残 %s 円と一致しません',
                        $period->label(), number_format($month['opening']), YearMonth::parse($previousKey)->label(), number_format($closing));
                }
            }
            $previous = [$key, $month];
        }

        if ($previous !== null) {
            [$lastKey, $last] = $previous;
            $closing = $last['closing'];
            if ($closing !== null && $closing !== 0 && $lastKey < $until->key()) {
                $issues[] = sprintf('%s の%s %s 円が %s 以降に繰り越されていません',
                    YearMonth::parse($lastKey)->label(), self::balanceName($closing), number_format($closing), YearMonth::parse($lastKey)->next()->label());
            }
        }

        return $issues;
    }

    private static function balanceName(int $balance): string
    {
        return $balance < 0 ? 'マイナス残高（過入金）' : '当月売掛残';
    }
}
