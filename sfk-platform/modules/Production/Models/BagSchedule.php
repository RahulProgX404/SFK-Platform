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
 * A schedule of 12. 製袋予定管理 (SYSTEM_BRAIN APP_12) on the 製袋予定表 board (production-schedule.js): a day
 * (`scheduled_on`) on a line (機械) with 担当者, 注番 (a job), 品名, 部数, 備考 (up to 10, one per line) and a background
 * highlight. With a 注番, 担当者 / 品名 / 部数 are taken from 06. 売上入力 when saved (Application\Schedules). The job's
 * other values (持込日, 納品日, 受注先, 数量, 持込便, 抜き, 窓, 口糊) are read from the job, not copied.
 *
 * Records taken over from the legacy app also keep 開始 / 終了日時, 使用状況 and 注番(フリー) / 品名(フリー); 入力方法
 * follows the 注番 (「受注から注番を取得」 with a job, else 「フリー入力」).
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property Carbon|null $scheduled_on
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $machine_id
 * @property string|null $person_initial
 * @property string|null $usage_status
 * @property string $entry_mode
 * @property int|null $order_id
 * @property string|null $product_name
 * @property string|null $free_order_number
 * @property string|null $free_product_name
 * @property string|null $copies
 * @property string|null $notes
 * @property string|null $highlight_color
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
            'scheduled_on' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'copies' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $schedule): void {
            // 入力方法 follows the 注番; a schedule of a job keeps no free 注番 / 品名
            $schedule->entry_mode = $schedule->order_id !== null ? ProductionChoices::ENTRY_FROM_ORDER : ProductionChoices::ENTRY_FREE;
            if ($schedule->order_id !== null) {
                $schedule->free_order_number = null;
                $schedule->free_product_name = null;
            }
            // the day of the board: that of 開始日時 for the schedules of the legacy app
            if ($schedule->scheduled_on === null && $schedule->starts_at !== null) {
                $schedule->scheduled_on = $schedule->starts_at->copy()->startOfDay();
            }
        });
    }

    /** 注番(P): the job's number, else the free one. */
    public function orderNumber(): ?string
    {
        return $this->order !== null ? $this->order->order_number : $this->free_order_number;
    }

    /** 品名: as saved (from 06. 売上入力 or typed), else — records of the legacy app — the job's product or the free one. */
    public function productName(): ?string
    {
        if (filled($this->product_name)) {
            return $this->product_name;
        }

        return $this->order !== null && filled($this->order->product_name) ? $this->order->product_name : $this->free_product_name;
    }

    /** @return list<string> 備考, one per line */
    public function noteList(): array
    {
        return array_values(array_filter(array_map(trim(...), preg_split('/\r\n|\r|\n/', (string) $this->notes) ?: []), fn (string $note): bool => $note !== ''));
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
