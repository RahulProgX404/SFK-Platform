<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Cache\OutsideDataCache;

/**
 * A pinned message of a conversation.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $message_id
 * @property int|null $pinned_by
 */
final class MessagePin extends Model implements OutsideDataCache
{
    public const UPDATED_AT = null;

    protected $table = 'message_pins';

    protected $guarded = ['id'];
}
