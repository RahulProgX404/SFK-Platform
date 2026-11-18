<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Cache\OutsideDataCache;

/**
 * Reaction of one person to one message (one per person).
 *
 * @property int $id
 * @property int $message_id
 * @property int $user_id
 * @property string $emoji
 */
final class MessageReaction extends Model implements OutsideDataCache
{
    protected $table = 'message_reactions';

    protected $guarded = ['id'];
}
