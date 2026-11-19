<?php

declare(strict_types=1);

namespace Modules\Sales\Console;

use Illuminate\Console\Command;
use Modules\Sales\Application\Receivables\ReceivableLedger;
use Modules\Sales\Domain\Receivables\ReceivableBasis;

/**
 * The month close / roll-forward of 23. 売掛管理 and 23-2. 売掛管理(締日別) (docs/receivables.md): rebuilds every customer's
 * months from the sales and receipts up to the current month — the missing ones from each customer's first month,
 * corrected ones after a back-dated change, credits carried. Idempotent: a second run reports no change. Scheduled
 * daily (SalesServiceProvider); run it by hand after a rule change to re-apply it to all existing data.
 */
final class RollReceivablesCommand extends Command
{
    protected $signature = 'sales:receivables-roll {--customer=* : 得意先ID (default: every customer with sales, receipts or receivables)}';

    protected $description = 'Roll the receivables of every customer forward to the current month (idempotent)';

    public function handle(ReceivableLedger $ledger): int
    {
        $ids = array_values(array_map('intval', (array) $this->option('customer')));
        $totals = $ledger->rollForward($ids === [] ? null : $ids);

        $this->info(sprintf(
            '%d customer(s): %d month(s) created, %d updated, %d removed (%s).',
            $totals['customers'], $totals['created'], $totals['updated'], $totals['deleted'],
            implode(' / ', array_map(fn (ReceivableBasis $basis): string => $basis->rule(), ReceivableBasis::cases())),
        ));

        return self::SUCCESS;
    }
}
