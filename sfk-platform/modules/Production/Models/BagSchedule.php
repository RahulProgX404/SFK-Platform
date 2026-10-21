<?php

declare(strict_types=1);

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistory;
use Modules\Core\Models\Concerns\TracksAuthors;
use Modules\MasterData\Models\Machine;
use Modules\Orders\Models\Order;
use Modules\Production\Domain\ProductionChoices;

/**
 * A booking of 12. 製袋予定管理 (SYSTEM_BRAIN APP_12): 開始 / 終了日時 on a machine with its 使用状況, for a job (注番 —
 * 入力方法 「受注から注番を取得」) or entered freely (注番(フリー) / 品名(フリー)). The job's values (品名, 持込日, 納品日,
 * 受注先, 数量, 持込便, 抜き, 窓, 口糊) are read from the job, not copied.
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $machine_id
 * @property string|null $usage_status
 * @property string $entry_mode
 * @property int|null $order_id
 * @property string|null $free_order_number
 * @property string|null $free_product_name
 * @property string|null $copies
 * @property string|null $notes
 * @property-read Machine|null $machine
 * @property-read Order|null $order
 */
final class BagSchedule extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistory;

    protected $guarded = ['id', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    /** @var array<string, mixed> */
    protected $attributes = ['entry_mode' => ProductionChoices::ENTRY_FROM_ORDER];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'copies' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        // only the fields of the chosen 入力方法 are kept
        self::saving(function (self $schedule): void {
            if ($schedule->entry_mode === ProductionChoices::ENTRY_FREE) {
                $schedule->order_id = null;
            } else {
                $schedule->free_order_number = null;
                $schedule->free_product_name = null;
            }
        });
    }

    /** 注番(P): the job's number, else the free one. */
    public function orderNumber(): ?string
    {
        return $this->order !== null ? $this->order->order_number : $this->free_order_number;
    }

    /** 品名(P): the job's product, else the free one. */
    public function productName(): ?string
    {
        return $this->order !== null && filled($this->order->product_name) ? $this->order->product_name : $this->free_product_name;
    }

    /** @return BelongsTo<Machine, $this> 機械名 */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return BelongsTo<Order, $this> 注番 */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
