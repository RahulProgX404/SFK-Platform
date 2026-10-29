<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Legacy;

use Modules\Sales\Domain\Receivables\ReceivableBasis;

/** 23. 売掛管理 (legacy app 38): receivables by calendar month (LegacyReceivables). */
final class LegacyReceivableImport extends LegacyReceivables
{
    public function legacyAppId(): string
    {
        return '38';
    }

    public function label(): string
    {
        return '23. 売掛管理';
    }

    public function step(): int
    {
        return 190;
    }

    protected function basis(): ReceivableBasis
    {
        return ReceivableBasis::Calendar;
    }
}
