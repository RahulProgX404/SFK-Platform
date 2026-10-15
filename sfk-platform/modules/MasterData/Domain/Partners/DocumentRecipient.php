<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Partners;

use Modules\Core\Domain\Concerns\HasOptions;

/**
 * 請求書送付先 / 納品書送付先: invoices and delivery notes go to the parent company or to the customer itself
 * (BR-MD-06; default 得意先名).
 */
enum DocumentRecipient: string
{
    use HasOptions;

    case ParentCompany = '親会社';
    case Customer = '得意先名';
}
