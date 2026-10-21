<?php

declare(strict_types=1);

namespace Modules\Sales\Domain\Sales;

/**
 * The save checks of a sale on a job (legacy Customine 06 page 2 「二重売上防止/売上制限」), with the job's figures
 * without this sale (other sales only). Administrators are exempt like the legacy privileged user.
 *
 * - BR-SA-02: the job is fully sold (売上残数量 = 0) — only a マイナス処理 may still be booked;
 * - BR-SA-03: 売上数量 more than 売上残数量;
 * - BR-SA-04: 売上金額 of all the job's sales more than its planned 売上金額 (売上判定用 = false).
 */
final class SaleChecks
{
    /**
     * @param  string  $remaining  売上残数量 of the job without this sale
     * @param  int  $soldAmount  現在売上済金額 of the job without this sale
     * @param  int  $plannedAmount  売上金額 of the job
     * @return list<string> messages (empty = may be saved)
     */
    public static function problems(string $kind, ?string $quantity, int $amount, string $remaining, int $soldAmount, int $plannedAmount): array
    {
        if ($kind === SaleChoices::KIND_CREDIT) {
            return [];
        }

        if (bccomp($remaining, '0', 4) <= 0) {
            return ['この注番はすでに全数売上済みです（売上残数量 0）。マイナス処理以外は登録できません。'];
        }

        $problems = [];
        if ($quantity !== null && $quantity !== '' && bccomp($quantity, $remaining, 4) > 0) {
            $problems[] = '売上数量が売上残数量（'.self::number($remaining).'）を超えています。';
        }
        if ($plannedAmount > 0 && $soldAmount + $amount > $plannedAmount) {
            $problems[] = '売上金額の合計（¥'.number_format($soldAmount + $amount).'）が注番の売上金額（¥'.number_format($plannedAmount).'）を超えています。';
        }

        return $problems;
    }

    private static function number(string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
    }
}
