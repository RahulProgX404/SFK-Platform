<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Cache\OutsideDataCache;

/**
 * A company sticker (スタンプ) uploaded by an administrator.
 *
 * @property int $id
 * @property string $name
 * @property string $path
 * @property string $mime
 * @property int $sort_order
 * @property int|null $created_by
 */
final class Sticker extends Model implements OutsideDataCache
{
    protected $table = 'messaging_stickers';

    protected $guarded = ['id'];
}
