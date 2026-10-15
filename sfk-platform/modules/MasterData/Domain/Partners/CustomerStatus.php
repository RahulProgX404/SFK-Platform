<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Partners;

use Modules\Core\Domain\Concerns\HasOptions;

/**
 * 得意先ステータス (SYSTEM_BRAIN APP_01).
 */
enum CustomerStatus: string
{
    use HasOptions;

    case Lead = 'リード状態';
    case Customer = '顧客化';
    case CustomerOnCredit = '顧客化(掛払)';
}
