<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Partners;

use Modules\Core\Domain\Concerns\HasOptions;

/**
 * 請求書発行区分 / 納品書発行区分: how invoices and delivery notes are handed over (SYSTEM_BRAIN APP_01). The live
 * legacy app names the customer's own form per document: 「(無)指定請求書」 for invoices, 「(無)指定納品書」 for
 * delivery notes (the app template had the invoice one on both).
 */
enum DocumentIssuance: string
{
    use HasOptions;

    case Mail = '郵送';
    case ByStaff = '担当渡';
    case DesignatedApp = '電子指定アプリ';
    case Email = 'メール';
    case CustomerForm = '(無)指定請求書';
    case CustomerDeliveryForm = '(無)指定納品書';
    case None = '無';

    /** @return array<string, string> 請求書発行区分 */
    public static function invoiceOptions(): array
    {
        return array_diff_key(self::options(), [self::CustomerDeliveryForm->value => true]);
    }

    /** @return array<string, string> 納品書発行区分 */
    public static function deliveryNoteOptions(): array
    {
        return array_diff_key(self::options(), [self::CustomerForm->value => true]);
    }
}
