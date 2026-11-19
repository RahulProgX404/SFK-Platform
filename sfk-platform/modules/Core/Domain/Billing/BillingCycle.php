<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Billing;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Modules\Core\Domain\Calendar\YearMonth;

/**
 * Billing-cycle rules of the legacy system (SYSTEM_BRAIN 12_BUSINESS_RULES, section G/F).
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
     * The closing date of a billing-cycle month (月度) — the default 請求日付 of an invoice for that period, whatever day
     * it is made on: 5/10/15/20/25 → that day of the month; 末日, 随時請求 or blank → the last day of the month.
     * Replaces BR-SA-10's dependence on today (defaultInvoiceDate(): this month's closing day / last month's end), which
     * gave a different date — and so a different 月度 than the balances it carries — depending on the day it was run.
     */
    public static function closingDateOf(YearMonth $month, ?ClosingDay $closingDay): CarbonImmutable
    {
        $day = $closingDay?->dayOfMonth();

        return $day === null ? $month->lastDay() : $month->firstDay()->setDay($day);
    }

    /**
     * BR-SA-10 — default invoice date when the operator does not enter one (legacy, relative to today; invoices use
     * closingDateOf()).
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
