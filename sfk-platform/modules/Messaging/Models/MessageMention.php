<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Cache\OutsideDataCache;

/**
 * A user mentioned (@) in a message.
 *
 * @property int $id
 * @property int $user_id
 * @property int $message_id
 * @property int $conversation_id
 */
final class MessageMention extends Model implements OutsideDataCache
{
    public const UPDATED_AT = null;

    protected $table = 'message_mentions';

    protected $guarded = ['id'];
}
