<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How one user uses one app (Modules\Core\Application\Apps\AppUsageLog): how often its screens were opened, when last,
 * and the record last opened in it.
 *
 * @property int $id
 * @property int $user_id
 * @property string $app_code
 * @property int $visits
 * @property Carbon|null $last_visited_at
 * @property string|null $record_type morph class of the record last opened
 * @property int|null $record_id
 * @property Carbon|null $record_visited_at
 * @property-read User $user
 */
final class AppUsage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'visits' => 'integer',
            'last_visited_at' => 'datetime',
            'record_id' => 'integer',
            'record_visited_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
