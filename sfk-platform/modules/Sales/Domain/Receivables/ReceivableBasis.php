<?php

declare(strict_types=1);

namespace Modules\Sales\Domain\Receivables;

/**
 * The month a receivable is kept by: the calendar month (23. 売掛管理: 売上日 / 入金日) or the billing-cycle month 月度
 * (23-2. 売掛管理(締日別): BR-AR-01, by the sale's effective closing day / the invoice's 今回締日 for receipts).
 */
enum ReceivableBasis: string
{
    case Calendar = 'calendar';
    case Cycle = 'cycle';
}
