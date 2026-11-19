<?php

declare(strict_types=1);

namespace Modules\Purchasing\Console;

use Illuminate\Console\Command;
use Modules\Purchasing\Application\Payables\PayableLedger;

/**
 * The month close / roll-forward of 23. 買掛管理: rebuilds every supplier's months from the purchases and payments up to
 * the current month — missing ones written, corrected ones updated, credits carried (PayableRoll). Idempotent: a second
 * run reports no change. Scheduled daily (PurchasingServiceProvider); run it by hand after a rule change.
 */
final class RollPayablesCommand extends Command
{
    protected $signature = 'purchasing:payables-roll {--supplier=* : 仕入先ID (default: every supplier with purchases, payments or payables)}';

    protected $description = 'Roll the payables of every supplier forward to the current month (idempotent)';

    public function handle(PayableLedger $ledger): int
    {
        $ids = array_values(array_map('intval', (array) $this->option('supplier')));
        $totals = $ledger->rollForward($ids === [] ? null : $ids);

        $this->info(sprintf('%d supplier(s): %d month(s) created, %d updated, %d removed.', $totals['suppliers'], $totals['created'], $totals['updated'], $totals['deleted']));

        return self::SUCCESS;
    }
}
