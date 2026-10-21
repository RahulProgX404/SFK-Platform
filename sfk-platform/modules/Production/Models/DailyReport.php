<?php

declare(strict_types=1);

namespace Modules\Production\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistory;
use Modules\Core\Models\Concerns\TracksAuthors;
use Modules\MasterData\Models\Consumable;
use Modules\MasterData\Models\Machine;
use Modules\Orders\Models\Order;
use Modules\Production\Domain\DailyReports\TaskCosting;

/**
 * A daily report of 08. 日報入力 (SYSTEM_BRAIN APP_08): one record per task — 作業日, 作業者, 注番, 機械名, the boxes used,
 * 糊, times with the overtime, 出来上 and the checks. The worker's 作業単価 / 残業単価, the machine's rates and the box
 * prices are copied when they are chosen (legacy lookups, snapshots); 作業時間, 残業時間, 社内原価 and 製造売上 are
 * recalculated on every save (TaskCosting, BR-PR-01..03) — the jobs add them up (JobProduction).
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property Carbon $worked_on
 * @property int $user_id
 * @property int|null $work_rate
 * @property int|null $overtime_rate
 * @property int|null $order_id
 * @property int|null $machine_id
 * @property int|null $cardboard_id
 * @property string|null $cardboard_used
 * @property int|null $partition_id
 * @property string|null $partition_used
 * @property string|null $glue
 * @property string|null $glue_cost
 * @property string|null $started_at
 * @property string|null $ended_at
 * @property string|null $break_time
 * @property string|null $changeover_minutes
 * @property string|null $changeover_count
 * @property string|null $produced
 * @property string|null $crew
 * @property string $day_type
 * @property string|null $overtime_started_at
 * @property string|null $overtime_ended_at
 * @property string|null $overtime_break
 * @property list<string>|null $quality_checks
 * @property bool $gloves
 * @property string|null $damaged_before
 * @property string|null $damaged_after
 * @property bool $update_status
 * @property string|null $new_status
 * @property string|null $difficulty
 * @property string|null $notes
 * @property string|null $cardboard_cost
 * @property string|null $partition_cost
 * @property string|null $processing_rate
 * @property string|null $changeover_rate
 * @property string|null $handwork_rate
 * @property string|null $machine_rate
 * @property string|null $printing_rate
 * @property string|null $plate_change_rate
 * @property string|null $ink_mixing_rate
 * @property string|null $work_hours
 * @property string|null $overtime_hours
 * @property int $internal_cost
 * @property int $manufacturing_sales
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read User $worker
 * @property-read Order|null $order
 * @property-read Machine|null $machine
 * @property-read Consumable|null $cardboard
 * @property-read Consumable|null $partition
 */
final class DailyReport extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistory {
        historyExcludedColumns as baseHistoryExcludedColumns;
    }

    /** The machine's rates copied with 機械名. */
    public const MACHINE_RATES = ['processing_rate', 'changeover_rate', 'handwork_rate', 'machine_rate', 'printing_rate', 'plate_change_rate', 'ink_mixing_rate'];

    protected $guarded = ['id', 'work_rate', 'overtime_rate', 'work_hours', 'overtime_hours', 'internal_cost', 'manufacturing_sales', 'created_by', 'updated_by',
        'created_at', 'updated_at'];

    /** 日種別 starts as 平日. */
    protected $attributes = ['day_type' => '平日'];

    protected function casts(): array
    {
        $decimals = array_fill_keys(['cardboard_used', 'partition_used', 'glue_cost', 'changeover_minutes', 'changeover_count', 'produced', 'crew',
            'damaged_before', 'damaged_after', 'cardboard_cost', 'partition_cost', ...self::MACHINE_RATES], 'decimal:4');

        return [
            ...$decimals,
            'legacy_id' => 'integer',
            'worked_on' => 'date',
            'work_rate' => 'integer',
            'overtime_rate' => 'integer',
            'quality_checks' => 'array',
            'gloves' => 'boolean',
            'update_status' => 'boolean',
            'work_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'internal_cost' => 'integer',
            'manufacturing_sales' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $report): void {
            // lookups 作業者 / 機械名 / ダンボール名称 / ダンボール名称(仕切): the master values when chosen
            if ($report->isDirty('user_id')) {
                $worker = User::query()->find($report->user_id);
                $report->work_rate = $worker?->work_rate;
                $report->overtime_rate = $worker?->overtime_rate;
            }
            if ($report->isDirty('machine_id')) {
                $machine = $report->machine_id === null ? null : Machine::query()->find($report->machine_id);
                foreach (self::MACHINE_RATES as $rate) {
                    $report->setAttribute($rate, $machine?->getAttribute($rate));
                }
            }
            if ($report->isDirty('cardboard_id')) {
                $report->cardboard_cost = $report->cardboard_id === null ? null : Consumable::query()->whereKey($report->cardboard_id)->value('unit_price');
            }
            if ($report->isDirty('partition_id')) {
                $report->partition_cost = $report->partition_id === null ? null : Consumable::query()->whereKey($report->partition_id)->value('unit_price');
            }

            $report->fillFigures();
        });
    }

    /**
     * The 変更履歴 keeps what was entered: the copied rates / prices (confidential) and the figures follow from it; the
     * boxes' 在庫数 of a list row is not the report's.
     *
     * @return list<string>
     */
    protected function historyExcludedColumns(): array
    {
        return [...$this->baseHistoryExcludedColumns(), 'work_rate', 'overtime_rate', 'cardboard_cost', 'partition_cost', ...self::MACHINE_RATES,
            'work_hours', 'overtime_hours', 'internal_cost', 'manufacturing_sales', 'cardboard_stock', 'partition_stock'];
    }

    /** 作業時間 / 残業時間 / 社内原価 / 製造売上 (BR-PR-01..03). */
    public function fillFigures(): void
    {
        $workRate = $this->work_rate === null ? null : (string) $this->work_rate;
        $overtimeRate = $this->overtime_rate === null ? null : (string) $this->overtime_rate;
        $hours = TaskCosting::workHours($this->started_at, $this->ended_at, $this->break_time);
        $overtime = TaskCosting::workHours($this->overtime_started_at, $this->overtime_ended_at, $this->overtime_break);

        $this->forceFill([
            'work_hours' => $hours,
            'overtime_hours' => $overtime,
            'internal_cost' => TaskCosting::internalCost($workRate, $this->machine_rate, $hours, $overtimeRate, $overtime, $this->glue_cost, $this->cardboard_cost,
                $this->partition_cost),
            'manufacturing_sales' => TaskCosting::manufacturingSales($workRate, $this->machine_rate, $hours, $this->produced),
        ]);
    }

    /** @return BelongsTo<User, $this> 作業者 */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Order, $this> 注番 */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Machine, $this> 機械名 */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return BelongsTo<Consumable, $this> ダンボール名称 */
    public function cardboard(): BelongsTo
    {
        return $this->belongsTo(Consumable::class, 'cardboard_id');
    }

    /** @return BelongsTo<Consumable, $this> ダンボール名称(仕切) */
    public function partition(): BelongsTo
    {
        return $this->belongsTo(Consumable::class, 'partition_id');
    }
}
