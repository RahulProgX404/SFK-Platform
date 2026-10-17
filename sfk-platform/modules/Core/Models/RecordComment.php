<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One コメント on an app record (Modules\Core\Application\Comments\RecordComments).
 *
 * @property int $id
 * @property string $record_type morph class of the record
 * @property int $record_id
 * @property int|null $user_id who wrote it
 * @property string $body
 * @property list<int> $mentions 宛先 (user ids)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
final class RecordComment extends Model
{
    protected $fillable = ['record_type', 'record_id', 'user_id', 'body', 'mentions'];

    protected function casts(): array
    {
        return [
            'record_id' => 'integer',
            'user_id' => 'integer',
            'mentions' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return Collection<int, User> the 宛先, by name */
    public function mentionedUsers(): Collection
    {
        return User::query()->with(['employeeCategories', 'position'])->whereIn('id', $this->mentions)->orderBy('name')->get();
    }
}
