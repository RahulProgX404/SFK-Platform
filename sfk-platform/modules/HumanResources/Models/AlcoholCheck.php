<?php

declare(strict_types=1);

namespace Modules\HumanResources\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistory;
use Modules\Core\Models\Concerns\TracksAuthors;

/**
 * An alcohol check of 18. アルコールチェック (SYSTEM_BRAIN APP_18): 検査日 and 氏名 of the driver with the check 運転前 and
 * 運転後 — 検査時刻, 車両番号, 確認方法, 酒気帯び運転の有無, 確認者, 指示項目 and その他必要な項目 each.
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property Carbon $checked_on
 * @property int $driver_id
 * @property int|null $employee_id
 * @property string|null $before_time
 * @property string|null $before_vehicle
 * @property string|null $before_method
 * @property string|null $before_detected
 * @property int|null $before_checker_id
 * @property string|null $before_instruction
 * @property string|null $before_notes
 * @property string|null $after_time
 * @property string|null $after_vehicle
 * @property string|null $after_method
 * @property string|null $after_detected
 * @property int|null $after_checker_id
 * @property string|null $after_instruction
 * @property string|null $after_notes
 * @property-read User $driver
 * @property-read User|null $employee
 * @property-read User|null $beforeChecker
 * @property-read User|null $afterChecker
 */
final class AlcoholCheck extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistory;

    protected $guarded = ['id', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'checked_on' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> 氏名 */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /** @return BelongsTo<User, $this> 従業員名 */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /** @return BelongsTo<User, $this> 確認者 (運転前) */
    public function beforeChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'before_checker_id');
    }

    /** @return BelongsTo<User, $this> 確認者 (運転後) */
    public function afterChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'after_checker_id');
    }
}
