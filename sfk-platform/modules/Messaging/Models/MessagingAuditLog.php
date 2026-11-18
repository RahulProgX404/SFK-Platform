<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Cache\OutsideDataCache;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property int|null $conversation_id
 * @property array<string, mixed>|null $details
 * @property Carbon $created_at
 */
final class MessagingAuditLog extends Model implements OutsideDataCache
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }
}
