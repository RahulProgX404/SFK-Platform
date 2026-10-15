<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Partners;

use Modules\Core\Domain\Concerns\HasOptions;

/**
 * 取引先カテゴリ: the partner's roles, several allowed (SYSTEM_BRAIN APP_01, BR-MD-02). The disabled legacy option
 * 外注 is not offered.
 */
enum PartnerRole: string
{
    use HasOptions;

    case Customer = '得意先';
    case Supplier = '仕入先';
    case Other = 'その他';
}
