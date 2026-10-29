<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Legacy;

use Modules\Sales\Domain\Receivables\ReceivableBasis;

/** 23-2. 売掛管理(締日別) (legacy app 45): receivables by 月度 of the billing cycle (LegacyReceivables). */
final class LegacyCycleReceivableImport extends LegacyReceivables
{
    public function legacyAppId(): string
    {
        return '45';
    }

    public function label(): string
    {
        return '23-2. 売掛管理(締日別)';
    }

    public function step(): int
    {
        return 195;
    }

    protected function basis(): ReceivableBasis
    {
        return ReceivableBasis::Cycle;
    }
}
