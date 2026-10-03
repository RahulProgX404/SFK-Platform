<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Billing;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Modules\Core\Domain\Calendar\YearMonth;

/**
 * Billing-cycle rules of the kintone system (SYSTEM_BRAIN 12_BUSINESS_RULES, section G/F).
 */
final class BillingCycle
{
    /**
     * BR-AR-01 — billing-cycle month (月度) a transaction belongs to.
     * Closing day D ∈ {5,10,15,20,25}: day ≤ D → same month, otherwise next month.
     * 末日, 随時請求 or blank → same month.
     */
    public static function monthFor(DateTimeInterface $date, ?ClosingDay $closingDay): YearMonth
    {
        $month = YearMonth::fromDate($date);
        $closing = $closingDay?->dayOfMonth();

        if ($closing === null) {
            return $month;
        }

        return (int) $date->format('j') <= $closing ? $month : $month->next();
    }

    /** BR-AR-02 — a per-sale override (請求締日変更) wins over the partner's closing day. */
    public static function effectiveClosingDay(?ClosingDay $partnerClosingDay, ?ClosingDay $override): ?ClosingDay
    {
        return $override ?? $partnerClosingDay;
    }

    /**
     * BR-SA-10 — default invoice date when the operator does not enter one.
     * 5/10/15/20/25 → that day of the current month; 末日 → last day of the previous month;
     * otherwise (blank / 随時請求) → today. Ad-hoc invoices use each sale's own billing date instead.
     */
    public static function defaultInvoiceDate(?ClosingDay $closingDay, DateTimeInterface $today): CarbonImmutable
    {
        $today = CarbonImmutable::instance($today)->startOfDay();

        if ($closingDay === ClosingDay::EndOfMonth) {
            return $today->subMonthNoOverflow()->endOfMonth()->startOfDay();
        }

        $day = $closingDay?->dayOfMonth();

        return $day === null ? $today : $today->setDay($day);
    }
}
