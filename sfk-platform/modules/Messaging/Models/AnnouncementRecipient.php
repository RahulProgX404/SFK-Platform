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
 * @property int $announcement_id
 * @property int $user_id
 * @property Carbon|null $read_at
 * @property Carbon|null $confirmed_at
 */
final class AnnouncementRecipient extends Model implements OutsideDataCache
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }
}
