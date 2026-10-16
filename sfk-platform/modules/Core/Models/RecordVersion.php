<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One version in the 変更履歴 of an app record (Modules\Core\Application\History\RecordHistory).
 *
 * @property int $id
 * @property string $record_type morph class of the record
 * @property int $record_id
 * @property int $version 1 = the initial version
 * @property string $event created | updated | restored
 * @property array<string, mixed> $snapshot the record's content in this version (HasRecordHistory::historySnapshot())
 * @property list<string> $changed fields changed against the previous version
 * @property int|null $restored_from version restored by this one
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
final class RecordVersion extends Model
{
    public const UPDATED_AT = null;

    public const EVENT_CREATED = 'created';

    public const EVENT_UPDATED = 'updated';

    public const EVENT_RESTORED = 'restored';

    protected $fillable = ['record_type', 'record_id', 'version', 'event', 'snapshot', 'changed', 'restored_from', 'user_id'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot' => 'array',
            'changed' => 'array',
            'restored_from' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
