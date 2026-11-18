<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Cache\OutsideDataCache;
use Modules\Messaging\Domain\ConversationType;
use Modules\Messaging\Domain\GroupSettings;

/**
 * A トーク: direct (two users, one per pair — direct_key) or a group.
 *
 * @property int $id
 * @property ConversationType $type
 * @property string|null $name
 * @property string|null $description
 * @property string|null $avatar_path
 * @property string|null $direct_key
 * @property array<string, mixed>|null $settings
 * @property int|null $created_by
 * @property int|null $last_message_id
 * @property Carbon|null $last_message_at
 * @property Carbon|null $archived_at
 * @property Carbon $created_at
 */
final class Conversation extends Model implements OutsideDataCache
{
    protected $guarded = ['id'];

    /** A conversation with a name, members, rules and 管理者: a group or a channel (not a direct one). */
    public function isGroup(): bool
    {
        return $this->type->isShared();
    }

    public function isChannel(): bool
    {
        return $this->type === ConversationType::Channel;
    }

    /** An archived channel: readable, but nobody posts any more. */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function groupSettings(): GroupSettings
    {
        return GroupSettings::fromArray($this->settings);
    }

    /** @return HasMany<Participant, $this> everyone who ever took part (left_at marks who left) */
    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    /** @return HasMany<Participant, $this> */
    public function members(): HasMany
    {
        return $this->participants()->whereNull('left_at');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'settings' => 'array',
            'last_message_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
