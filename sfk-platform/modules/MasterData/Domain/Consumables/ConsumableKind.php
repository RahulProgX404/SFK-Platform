<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Consumables;

use Modules\Core\Domain\Concerns\HasOptions;

/** 区分 of a consumable (SYSTEM_BRAIN APP_19). */
enum ConsumableKind: string
{
    use HasOptions;

    case Cardboard = '段ボール';
    case Paper = '紙';
    case Product = '商品';
    case Consumable = '消耗品';
}
