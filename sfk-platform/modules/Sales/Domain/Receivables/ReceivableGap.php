<?php

declare(strict_types=1);

namespace Modules\Sales\Domain\Receivables;

use Modules\Core\Domain\Calendar\YearMonth;
use RuntimeException;

/**
 * A customer's receivable months have a hole: the latest month before `$period` is not the month right before it, and
 * its 当月売掛残 is not 0, so 前月売掛残 of `$period` is unknown (legacy defect: one code path then took 0, another the
 * latest earlier row — docs/receivables.md, defect 4). Never caught to fall back to 0; roll the ledger forward instead.
 */
final class ReceivableGap extends RuntimeException
{
    public function __construct(public readonly int $customerId, public readonly YearMonth $period, public readonly YearMonth $latest, public readonly int $balance)
    {
        parent::__construct(sprintf(
            '売掛の月が欠落しています（得意先ID %d：%s の前月がなく、%s の当月売掛残 %s 円が繰り越されていません）。一括更新を実行してください。',
            $customerId, $period->label(), $latest->label(), number_format($balance),
        ));
    }
}
