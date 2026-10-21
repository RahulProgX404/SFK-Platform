<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Consumables;

use Modules\Core\Domain\Concerns\HasOptions;

/** カテゴリ of a cardboard box (SYSTEM_BRAIN APP_19): plain A-type or with partitions (08N 仕切り付き). */
enum BoxType: string
{
    use HasOptions;

    case AType = '【A式】';
    case Partitioned = '【仕切り付き】';
}
