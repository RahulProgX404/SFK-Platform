<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Cache\OutsideDataCache;

/**
 * 全社アナウンス: published by an administrator to everyone, some 従業員カテゴリ or some employees. The recipients are
 * fixed when it is published (AnnouncementRecipient, with 既読 / 確認).
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property string $priority normal | important | urgent
 * @property Carbon|null $expires_at
 * @property bool $requires_confirmation
 * @property string $audience all | categories | users
 * @property list<int>|null $audience_ids
 * @property Carbon $published_at
 * @property int|null $created_by
 * @property Carbon $created_at
 */
final class Announcement extends Model implements OutsideDataCache
{
    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_CATEGORIES = 'categories';

    public const AUDIENCE_USERS = 'users';

    /** the members of chosen groups / channels, when it is published */
    public const AUDIENCE_GROUPS = 'groups';

    protected $guarded = ['id'];

    /** @return HasMany<AnnouncementAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(AnnouncementAttachment::class)->orderBy('id');
    }

    /** @return HasMany<AnnouncementRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(AnnouncementRecipient::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'requires_confirmation' => 'boolean',
            'audience_ids' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
