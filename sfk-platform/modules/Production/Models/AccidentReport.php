<?php

declare(strict_types=1);

namespace Modules\Production\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistoryWithRelations;
use Modules\Core\Models\Concerns\TracksAuthors;
use Modules\MasterData\Models\Machine;
use Modules\Orders\Models\Order;

/**
 * An incident of 14. 事故報告管理 (SYSTEM_BRAIN APP_14): 事故発生日, 報告者, the job (得意先名 / 品名 from it), 担当機械,
 * 製造者1 / 2, 営業関係者, what happened and what was done, and the damage table 損害 (小計 = 原価 × 数量); 合計損害額 =
 * Σ 小計 — refreshTotals() after the rows changed. The legacy approval (未処理 → 工場長確認中) was switched off and is not
 * carried over.
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property Carbon $occurred_on
 * @property int $reporter_id
 * @property int|null $order_id
 * @property string|null $quantity
 * @property int|null $machine_id
 * @property int|null $maker1_id
 * @property int|null $maker2_id
 * @property string|null $description
 * @property string|null $cause
 * @property string|null $response
 * @property string|null $prevention
 * @property string|null $notes
 * @property int $total_damage
 * @property-read User $reporter
 * @property-read Order|null $order
 * @property-read Machine|null $machine
 * @property-read User|null $maker1
 * @property-read User|null $maker2
 * @property-read Collection<int, User> $salesUsers
 * @property-read Collection<int, AccidentDamage> $damages
 */
final class AccidentReport extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistoryWithRelations;

    protected $guarded = ['id', 'total_damage', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'occurred_on' => 'date',
            'quantity' => 'decimal:4',
            'total_damage' => 'integer',
        ];
    }

    /** Recalculates 合計損害額 from the saved rows (saved without events when it changed). */
    public function refreshTotals(): void
    {
        $this->unsetRelation('damages');
        $this->forceFill(['total_damage' => (int) $this->damages()->get()->sum(fn (AccidentDamage $damage): int => $damage->subtotal())]);
        if ($this->isDirty()) {
            $this->saveQuietly();
        }
    }

    /** @return list<string> */
    protected function historyUsers(): array
    {
        return ['salesUsers'];
    }

    /** @return array<string, list<string>> */
    protected function historyTables(): array
    {
        return ['damages' => AccidentDamage::COLUMNS];
    }

    protected function afterHistoryRestore(): void
    {
        $this->refreshTotals();
    }

    /** @return BelongsTo<User, $this> 報告者氏名 */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<Order, $this> 注番 */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Machine, $this> 担当機械 */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return BelongsTo<User, $this> 製造者1 */
    public function maker1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maker1_id');
    }

    /** @return BelongsTo<User, $this> 製造者2 */
    public function maker2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maker2_id');
    }

    /** @return BelongsToMany<User, $this> 営業関係者 */
    public function salesUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->orderBy('name');
    }

    /** @return HasMany<AccidentDamage, $this> 損害 */
    public function damages(): HasMany
    {
        return $this->hasMany(AccidentDamage::class)->orderBy('sort')->orderBy('id');
    }
}
