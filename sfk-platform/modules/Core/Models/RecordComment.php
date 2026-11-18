<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One コメント on an app record (Modules\Core\Application\Comments\RecordComments); a reply has the comment it answers
 * as parent (one level).
 *
 * @property int $id
 * @property string $record_type morph class of the record
 * @property int $record_id
 * @property int|null $parent_id the comment it answers (返信)
 * @property int|null $user_id who wrote it
 * @property string $body
 * @property list<int> $mentions 宛先 (user ids)
 * @property bool $mentions_all 宛先 「全員」
 * @property Carbon|null $edited_at last 編集 by the writer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
final class RecordComment extends Model
{
    protected $fillable = ['record_type', 'record_id', 'parent_id', 'user_id', 'body', 'mentions', 'mentions_all', 'edited_at'];

    protected function casts(): array
    {
        return [
            'record_id' => 'integer',
            'parent_id' => 'integer',
            'user_id' => 'integer',
            'mentions' => 'array',
            'mentions_all' => 'boolean',
            'edited_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return Collection<int, User> the 宛先, by name (read with the comments by RecordComments::threads()) */
    public function mentionedUsers(): Collection
    {
        if ($this->relationLoaded('mentioned')) {
            /** @var Collection<int, User> */
            return $this->getRelation('mentioned');
        }

        return User::query()->with(['employeeCategories', 'position'])->whereIn('id', $this->mentions)->orderBy('name')->get();
    }

    /** @return Collection<int, RecordComment> its replies, oldest first (set by RecordComments::threads()) */
    public function replies(): Collection
    {
        /** @var Collection<int, RecordComment> */
        return $this->relationLoaded('replies') ? $this->getRelation('replies') : new Collection;
    }
}
