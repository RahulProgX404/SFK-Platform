<?php

declare(strict_types=1);

namespace Modules\HumanResources\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistoryWithRelations;
use Modules\Core\Models\Concerns\TracksAuthors;

/**
 * A checklist of 17. 安全衛生チェック (SYSTEM_BRAIN APP_17): 部署・機械, 部署, 名前, 従業員名, 日付 and the rows of
 * チェックリスト. The item texts are not in the legacy template; a list is filled once and copied with レコードを再利用する.
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property string|null $department_machine
 * @property string $department
 * @property int $user_id
 * @property int|null $employee_id
 * @property Carbon $checked_on
 * @property-read User $user
 * @property-read User|null $employee
 * @property-read Collection<int, SafetyCheckItem> $items
 */
final class SafetyCheck extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistoryWithRelations;

    protected $guarded = ['id', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'checked_on' => 'date',
        ];
    }

    /** @return array<string, list<string>> */
    protected function historyTables(): array
    {
        return ['items' => SafetyCheckItem::COLUMNS];
    }

    /** @return BelongsTo<User, $this> 名前 */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> 従業員名 */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /** @return HasMany<SafetyCheckItem, $this> チェックリスト */
    public function items(): HasMany
    {
        return $this->hasMany(SafetyCheckItem::class)->orderBy('sort')->orderBy('id');
    }
}
