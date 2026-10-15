<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Partners;

use Modules\Core\Domain\Concerns\HasOptions;

/**
 * グループ名 (SYSTEM_BRAIN APP_01, active options; the 9 disabled legacy names are not offered — see C-12).
 */
enum PartnerGroup: string
{
    use HasOptions;

    case G1 = '（１）大日本印刷関連';
    case G2 = '（２）凸版印刷関連';
    case G3 = '（３）民間・印刷会社';
    case G4 = '（４）民間・その他';
    case G5 = '（５）公共機関・入札';
    case G6 = '（６）ネット販売';
    case G7 = '（７）直需販売';
    case G8 = '（８）その他売上';
}
