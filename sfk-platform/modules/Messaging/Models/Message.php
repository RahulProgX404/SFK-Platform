<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Cache\OutsideDataCache;
use Modules\Messaging\Domain\MessageType;
use Modules\Messaging\Domain\Priority;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int|null $user_id
 * @property MessageType $type
 * @property string|null $body
 * @property array<string, mixed>|null $meta
 * @property int|null $reply_to_id
 * @property int|null $forwarded_from_id
 * @property string|null $client_id
 * @property Carbon|null $edited_at
 * @property Carbon|null $unsent_at
 * @property Priority $priority
 * @property Carbon $created_at
 */
final class Message extends Model implements OutsideDataCache
{
    protected $guarded = ['id'];

    public function isUnsent(): bool
    {
        return $this->unsent_at !== null;
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Message, $this> */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /** @return HasMany<MessageAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class)->orderBy('id');
    }

    /** @return HasMany<MessageReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    /** @return HasMany<MessageEdit, $this> */
    public function edits(): HasMany
    {
        return $this->hasMany(MessageEdit::class);
    }

    /** @return HasMany<MessageMention, $this> */
    public function mentions(): HasMany
    {
        return $this->hasMany(MessageMention::class);
    }

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'meta' => 'array',
            'edited_at' => 'datetime',
            'unsent_at' => 'datetime',
            'priority' => Priority::class,
        ];
    }
}
