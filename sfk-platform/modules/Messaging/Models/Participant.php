<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Cache\OutsideDataCache;
use Modules\Messaging\Domain\NotificationLevel;
use Modules\Messaging\Domain\ParticipantRole;

/**
 * A user's place in a conversation, with their own state of it: read / delivered watermarks, ピン留め, アーカイブ,
 * お気に入り, 通知オフ and 「トークを削除」 (cleared_message_id).
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property ParticipantRole $role
 * @property int|null $added_by
 * @property Carbon|null $joined_at
 * @property Carbon|null $left_at
 * @property int|null $visible_from_message_id
 * @property int|null $cleared_message_id
 * @property Carbon|null $hidden_at
 * @property int|null $last_read_message_id
 * @property Carbon|null $last_read_at
 * @property int|null $last_delivered_message_id
 * @property Carbon|null $pinned_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $favorite_at
 * @property Carbon|null $muted_until
 * @property NotificationLevel $notification_level
 * @property int|null $pin_order
 */
final class Participant extends Model implements OutsideDataCache
{
    protected $table = 'conversation_participants';

    protected $guarded = ['id'];

    public function isActive(): bool
    {
        return $this->left_at === null;
    }

    public function isAdmin(): bool
    {
        return $this->role === ParticipantRole::Admin;
    }

    public function isMuted(): bool
    {
        return $this->effectiveLevel() === NotificationLevel::None;
    }

    /** The level in force now: a running 通知オフ means nothing. */
    public function effectiveLevel(): NotificationLevel
    {
        if ($this->muted_until !== null && $this->muted_until->isFuture()) {
            return NotificationLevel::None;
        }

        return $this->notification_level;
    }

    /** The newest message id the member may NOT see (they see ids above it): 'visible from' and 「トークを削除」. */
    public function floorMessageId(): int
    {
        return max((int) $this->visible_from_message_id, (int) $this->cleared_message_id);
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'role' => ParticipantRole::class,
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'hidden_at' => 'datetime',
            'last_read_at' => 'datetime',
            'pinned_at' => 'datetime',
            'archived_at' => 'datetime',
            'favorite_at' => 'datetime',
            'muted_until' => 'datetime',
            'notification_level' => NotificationLevel::class,
            'pin_order' => 'integer',
            'visible_from_message_id' => 'integer',
            'cleared_message_id' => 'integer',
            'last_read_message_id' => 'integer',
            'last_delivered_message_id' => 'integer',
        ];
    }
}
