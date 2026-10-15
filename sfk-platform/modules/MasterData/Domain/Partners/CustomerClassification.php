<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Partners;

use Modules\Core\Domain\Concerns\HasOptions;

/**
 * 得意先分類区分 (SYSTEM_BRAIN APP_01). Encodes the same classification as グループ名 (C-12); both are kept
 * until the business decides which one survives.
 */
enum CustomerClassification: string
{
    use HasOptions;

    case Dnp = 'DNP';
    case Toppan = '凸版印刷';
    case PrivatePrinter = '民間印刷会社';
    case PrivateOther = '民間その他';
    case PublicTender = '公共機関入札';
    case OnlineSales = 'ネット販売';
    case DirectSales = '直需販売';
    case OtherSales = 'その他売上';
}
