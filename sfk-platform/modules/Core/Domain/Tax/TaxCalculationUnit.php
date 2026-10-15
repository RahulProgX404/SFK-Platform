<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Tax;

use Modules\Core\Domain\Concerns\HasOptions;

/**
 * 計算区分 of a partner (SYSTEM_BRAIN APP_01): consumption tax is calculated per line (明細), per slip (伝票) or
 * per invoice (請求).
 */
enum TaxCalculationUnit: string
{
    use HasOptions;

    case PerLine = '明細';
    case PerSlip = '伝票';
    case PerInvoice = '請求';
}
