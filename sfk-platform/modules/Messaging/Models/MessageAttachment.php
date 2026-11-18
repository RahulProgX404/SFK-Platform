<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Application\Cache\OutsideDataCache;
use Modules\Messaging\Domain\AttachmentKind;

/**
 * A file of a message on the private uploads disk; read only through the authorized download route.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int|null $message_id
 * @property int|null $user_id
 * @property AttachmentKind $kind
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property int|null $duration_ms
 */
final class MessageAttachment extends Model implements OutsideDataCache
{
    protected $guarded = ['id'];

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    protected function casts(): array
    {
        return [
            'kind' => AttachmentKind::class,
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_ms' => 'integer',
        ];
    }
}
