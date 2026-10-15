<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Partners;

use Modules\Core\Domain\Concerns\HasOptions;

/**
 * 請求書発行区分 / 納品書発行区分: how invoices and delivery notes are handed over (SYSTEM_BRAIN APP_01).
 */
enum DocumentIssuance: string
{
    use HasOptions;

    case Mail = '郵送';
    case ByStaff = '担当渡';
    case DesignatedApp = '電子指定アプリ';
    case Email = 'メール';
    case CustomerForm = '(無)指定請求書';
    case None = '無';
}
