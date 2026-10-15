<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Tax;

use Modules\Core\Domain\Concerns\HasOptions;

/**
 * 消費税区分 of a partner (customer side and supplier side, SYSTEM_BRAIN APP_01): whether amounts are entered
 * tax-exclusive (外), tax-inclusive (内) or without tax (無). Used by BR-TX-07 for AP tax.
 */
enum TaxMode: string
{
    use HasOptions;

    case Exclusive = '外';
    case Inclusive = '内';
    case None = '無';
}
