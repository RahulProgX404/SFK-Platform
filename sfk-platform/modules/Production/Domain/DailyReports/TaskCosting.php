<?php

declare(strict_types=1);

namespace Modules\Production\Domain\DailyReports;

use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Money\Yen;

/**
 * The figures of one task of 08. 日報入力 (SYSTEM_BRAIN APP_08 formulas, BR-PR-01..04), exact decimals:
 *
 *   作業時間     = ROUND((終了時間 − 開始時間 − 休憩時間) / 3600, 2)                               (BR-PR-01)
 *   残業時間     = ROUND((残業終了時間 − 残業開始時間 − 休憩時間(残業)) / 3600, 2)
 *   時給計算     = 作業単価 × 作業時間;  残業時給計算 = 残業時間 × 残業単価;  機械使用料 = 機械単価(H) × 作業時間
 *   枚数単価     = 出来上 × 加工単価(枚);  型替え単価 = 型替単価(回) × 回数
 *   社内原価     = ROUND(時給計算 + 残業時給計算 + 機械使用料 + 糊代 + ダンボール原価 + ダンボール原価(仕切))  (BR-PR-02)
 *   製造売上     = ROUND(作業時間 × 機械単価(H) + 作業単価 × 出来上)                               (BR-PR-03, the 08 formula)
 *   人数割       = ROUNDDOWN(出来上 / 作業人数)                                                   (BR-PR-04)
 *
 * ROUND rounds .5 away from zero like the legacy function (社内原価 was unrounded in the legacy app). Times are 'H:i'
 * (or 'H:i:s'); blank values count as 0.
 */
final class TaskCosting
{
    private const SCALE = 4;

    /** 作業時間 / 残業時間 in hours, 2 decimals (null while the start / end are missing). */
    public static function workHours(?string $start, ?string $end, ?string $break): ?string
    {
        $from = self::seconds($start);
        $to = self::seconds($end);
        if ($from === null || $to === null) {
            return null;
        }

        $seconds = $to - $from - (self::seconds($break) ?? 0);

        return self::round(bcdiv((string) $seconds, '3600', 6), 2);
    }

    /** 時給計算 / 機械使用料 / 枚数単価 / 型替え単価: rate × measure, exact. */
    public static function product(?string $rate, ?string $measure): string
    {
        return bcmul(self::decimal($rate), self::decimal($measure), self::SCALE);
    }

    /** 社内原価 (whole yen). */
    public static function internalCost(?string $workRate, ?string $machineRate, ?string $hours, ?string $overtimeRate, ?string $overtimeHours, ?string $glueCost, ?string $cardboardCost, ?string $partitionCost): int
    {
        $sum = '0';
        foreach ([self::product($workRate, $hours), self::product($overtimeHours, $overtimeRate), self::product($machineRate, $hours), $glueCost, $cardboardCost, $partitionCost] as $cost) {
            $sum = bcadd($sum, self::decimal($cost), self::SCALE);
        }

        return Yen::round($sum, RoundingMode::HalfUp);
    }

    /** 製造売上 (whole yen). */
    public static function manufacturingSales(?string $workRate, ?string $machineRate, ?string $hours, ?string $produced): int
    {
        return Yen::round(bcadd(self::product($hours, $machineRate), self::product($workRate, $produced), self::SCALE), RoundingMode::HalfUp);
    }

    /** 人数割 (null while 出来上 / 作業人数 are missing or 作業人数 is 0). */
    public static function perPerson(?string $produced, ?string $crew): ?int
    {
        if (! is_numeric($produced) || ! is_numeric($crew) || bccomp(self::decimal($crew), '0', self::SCALE) === 0) {
            return null;
        }

        return Yen::round(bcdiv(self::decimal($produced), self::decimal($crew), self::SCALE), RoundingMode::Truncate);
    }

    /** Seconds since midnight of 'H:i[:s]' (null when blank or not a time). */
    public static function seconds(?string $time): ?int
    {
        if ($time === null || preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($time), $parts) !== 1) {
            return null;
        }

        return (int) $parts[1] * 3600 + (int) $parts[2] * 60 + (int) ($parts[3] ?? 0);
    }

    private static function decimal(?string $value): string
    {
        return is_numeric($value) ? bcadd($value, '0', self::SCALE) : '0';
    }

    /** ROUND(x, places): .5 away from zero. */
    private static function round(string $value, int $places): string
    {
        $shift = bcpow('10', (string) $places, 0);
        $whole = Yen::round(bcmul($value, $shift, 6), RoundingMode::HalfUp);

        return bcdiv((string) $whole, $shift, $places);
    }
}
