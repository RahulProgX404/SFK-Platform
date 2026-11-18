<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Cache\OutsideDataCache;

/**
 * The audit of a change of a message: its text before an edit or a 送信取消.
 *
 * @property int $id
 * @property int $message_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $body
 */
final class MessageEdit extends Model implements OutsideDataCache
{
    public const UPDATED_AT = null;

    protected $table = 'message_edits';

    protected $guarded = ['id'];
}
