<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Cache\OutsideDataCache;

/**
 * 「自分のみ削除」 of one message.
 *
 * @property int $id
 * @property int $user_id
 * @property int $message_id
 */
final class MessageHide extends Model implements OutsideDataCache
{
    public const UPDATED_AT = null;

    protected $table = 'message_hides';

    protected $guarded = ['id'];
}
