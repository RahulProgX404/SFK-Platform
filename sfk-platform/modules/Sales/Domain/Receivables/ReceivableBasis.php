<?php

declare(strict_types=1);

namespace Modules\Sales\Domain\Receivables;

use DateTimeInterface;
use Modules\Core\Domain\Billing\BillingCycle;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Calendar\YearMonth;

/**
 * The month a receivable is kept by — two views of the same sales and receipts, never two maintained copies
 * (docs/receivables.md, defect 8):
 *
 * - Calendar (23. 売掛管理, legacy 売掛更新キー): the calendar month of 売上日 / 入金日.
 * - Cycle (23-2. 売掛管理(締日別), legacy 締日売掛更新キー / 月度, BR-AR-01): by the closing day — day ≤ 締日 (5/10/15/20/25)
 *   → that month, later → the next month; 末日 / 随時請求 / blank → the calendar month.
 *
 * periodOf() is the one place the rule is applied (ReceivableLedger for sales and receipts; Sale::billing_month uses the
 * same BillingCycle::monthFor()).
 */
enum ReceivableBasis: string
{
    case Calendar = 'calendar';
    case Cycle = 'cycle';

    /** The period a transaction of `$date` belongs to; `$closingDay` only matters for the cycle basis. */
    public function periodOf(DateTimeInterface $date, ?ClosingDay $closingDay): YearMonth
    {
        return match ($this) {
            self::Calendar => YearMonth::fromDate($date),
            self::Cycle => BillingCycle::monthFor($date, $closingDay),
        };
    }

    /** The rule of the basis, as stated on its screens and reports. */
    public function rule(): string
    {
        return match ($this) {
            self::Calendar => '暦月（売上日・入金日の月）',
            self::Cycle => '月度（締日で区切った月）',
        };
    }
}
