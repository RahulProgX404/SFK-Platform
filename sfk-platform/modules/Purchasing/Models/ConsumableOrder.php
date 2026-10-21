<?php

declare(strict_types=1);

namespace Modules\Purchasing\Models;

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
use Modules\MasterData\Models\Partner;
use Modules\Purchasing\Domain\ConsumableOrders\ConsumableOrderStatus;

/**
 * A purchase order of 20. 消耗品発注 (SYSTEM_BRAIN APP_20): 発注日, 発注先 (仕入先), 発注者 and the items of 19. 消耗品マスタ
 * (発注明細), with the legacy プロセス管理 未処理 → 確認中 → 承認済 (BR-PU-09, ConsumableOrderWorkflow). The status is
 * changed by its actions only, not by the form.
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property Carbon $ordered_on
 * @property int|null $supplier_id
 * @property ConsumableOrderStatus $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Partner|null $supplier
 * @property-read Collection<int, User> $orderers
 * @property-read Collection<int, ConsumableOrderLine> $lines
 */
final class ConsumableOrder extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistoryWithRelations;

    protected $guarded = ['id', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => '未処理'];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'ordered_on' => 'date',
            'status' => ConsumableOrderStatus::class,
        ];
    }

    protected static function booted(): void
    {
        // row by row, so the delivered stock of 19 is taken back
        self::deleting(function (self $order): void {
            $order->lines()->get()->each(fn (ConsumableOrderLine $line) => $line->delete());
        });
    }

    /** 作業者: whom the order waits for — the 作成者 while 未処理 (legacy assignee), administrators while 確認中. */
    public function waitsForCreator(): bool
    {
        return $this->status === ConsumableOrderStatus::Unprocessed;
    }

    /** @return list<string> */
    protected function historyUsers(): array
    {
        return ['orderers'];
    }

    /** @return array<string, list<string>> */
    protected function historyTables(): array
    {
        return ['lines' => ConsumableOrderLine::COLUMNS];
    }

    /** @return BelongsTo<Partner, $this> 発注先 */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }

    /** @return BelongsToMany<User, $this> 発注者 */
    public function orderers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->orderBy('name');
    }

    /** @return HasMany<ConsumableOrderLine, $this> 発注明細 */
    public function lines(): HasMany
    {
        return $this->hasMany(ConsumableOrderLine::class)->orderBy('sort')->orderBy('id');
    }
}
