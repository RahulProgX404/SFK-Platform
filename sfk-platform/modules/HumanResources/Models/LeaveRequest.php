<?php

declare(strict_types=1);

namespace Modules\HumanResources\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistory;
use Modules\Core\Models\Concerns\TracksAuthors;
use Modules\HumanResources\Domain\LeaveRequests\LeaveRequestStatus;

/**
 * An attendance request of 16. 申請管理 (SYSTEM_BRAIN APP_16): 申請区分, 申請者, 作業員名, 申請分類, 対象日付, the late /
 * early times (30 分単位) and 申請理由, with the legacy プロセス管理 (LeaveRequestStatus, LeaveRequestWorkflow) — the status
 * is changed by its actions only. Employees see only their own requests (legacy app description; visibleTo()).
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property string $request_type
 * @property int $applicant_id
 * @property int|null $worker_id
 * @property string $category
 * @property Carbon $target_on
 * @property string|null $total_time
 * @property string|null $clock_time
 * @property string $reason
 * @property LeaveRequestStatus $status
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read User $applicant
 * @property-read User|null $worker
 */
final class LeaveRequest extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistory;

    protected $guarded = ['id', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => '申請前'];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'target_on' => 'date',
            'status' => LeaveRequestStatus::class,
        ];
    }

    /** Whether the user applied for it or entered it (an employee's own requests). */
    public function isOwnedBy(User $user): bool
    {
        return $this->applicant_id === $user->id || $this->created_by === $user->id;
    }

    /**
     * The requests a user may see: all for administrators, else their own.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->user_type->isAdministrator()
            ? $query
            : $query->where(fn (Builder $own): Builder => $own->where('leave_requests.applicant_id', $user->id)->orWhere('leave_requests.created_by', $user->id));
    }

    /** @return list<User|null> コメント → 作成者 / 更新者, and the 申請者 */
    public function commentRecipients(): iterable
    {
        return [$this->creator, $this->updater, $this->applicant];
    }

    /** @return BelongsTo<User, $this> 申請者 */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    /** @return BelongsTo<User, $this> 作業員名 */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }
}
