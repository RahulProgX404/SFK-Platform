<?php

declare(strict_types=1);

namespace Modules\Orders\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Application\Numbering\DocumentNumberGenerator;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistory;
use Modules\MasterData\Models\Machine;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Domain\Orders\JobCosting;

/**
 * 受注 — the job (注番) of SYSTEM_BRAIN APP_04: what to make for which customer, its estimate (用紙 / 刷版 / 印刷 /
 * トムソン… / 製袋 / ケース代 / 送料), the planned 売上金額 and the actual costs and progress. Purchases (05), sales (06),
 * daily reports (08N), the bag-making schedule (12) and accident reports (14) will reference `id`.
 *
 * - 注番 is numbered when the order is created (BR-OR-01, `sfk-` + 7 digits, Core DocumentNumberGenerator; numbers
 *   taken from the legacy data are skipped), not on the edit screen as in the legacy app (D-04-1).
 * - 締日 and 端数区分 of the customer are copied when the customer is chosen (legacy lookup); every amount rounds with
 *   that 端数区分 (BR-TX-01).
 * - The calculated columns are rewritten on every save (JobCosting::totals()); the pages call refreshTotals() after
 *   the tables have been saved.
 * - 変更履歴 keeps the columns, 担当者 and the rows of the three tables. コメント notify 作成者 and 更新者 (legacy app
 *   notification, APP_04 permissions.md).
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property string $order_number
 * @property string|null $status
 * @property int|null $customer_id
 * @property string|null $closing_day
 * @property string|null $customer_rounding
 * @property string|null $product_name
 * @property bool $is_fsc
 * @property string|null $quantity
 * @property string|null $unit
 * @property string|null $due_time
 * @property bool $bag_manual
 * @property int|null $bag_manual_amount
 * @property int $sales_amount
 * @property int $estimated_cost_total
 * @property int $estimated_profit
 * @property int $purchase_cost_total
 * @property int $actual_cost_total
 * @property int $actual_sales_amount
 * @property int $internal_cost
 * @property int $profit
 * @property string $profit_rate
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Partner|null $customer
 * @property-read Collection<int, User> $assignees
 * @property-read Collection<int, OrderPaper> $papers
 * @property-read Collection<int, OrderPrinting> $printings
 * @property-read Collection<int, OrderFinishing> $finishings
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
final class Order extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments;
    use RecordsHistory {
        historySnapshot as private columnSnapshot;
        historyPreview as private columnPreview;
        restoreHistorySnapshot as private restoreColumns;
    }

    /** core.sequences key of 注番. */
    public const NUMBER_SEQUENCE = 'order';

    /** The tables of the record: relation => row model. */
    public const TABLES = [
        'papers' => OrderPaper::class,
        'printings' => OrderPrinting::class,
        'finishings' => OrderFinishing::class,
    ];

    protected $guarded = ['id', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    /** Columns that are 0 when left blank (amounts and quantities the legacy app defaulted to 0). */
    public const ZERO_WHEN_BLANK = [
        'manufactured_quantity', 'sold_quantity', 'design_fee', 'sales_amount', 'internal_cost', 'purchase_amount', 'purchase_cutting_amount',
        'purchase_plate_amount', 'purchase_stored_plate_amount', 'purchase_spot_color_amount', 'sold_amount',
    ];

    protected $attributes = [
        'unit' => '枚',
        'plate_storage' => '不要',
    ];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'is_fsc' => 'boolean',
            'quantity' => 'decimal:4',
            'sample' => 'decimal:4',
            'spare' => 'decimal:4',
            'due_date' => 'date',
            'order_date' => 'date',
            'expected_hours' => 'decimal:4',
            'manufactured_quantity' => 'decimal:4',
            'manufacturing_remaining' => 'decimal:4',
            'sold_quantity' => 'decimal:4',
            'sales_remaining' => 'decimal:4',
            'paper_subtotal' => 'integer',
            'plate_last_used_on' => 'date',
            'plate_count' => 'decimal:4',
            'plate_unit_price' => 'decimal:4',
            'plate_amount' => 'integer',
            'design_fee' => 'integer',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'flap' => 'decimal:2',
            'bottom' => 'decimal:2',
            'unfolded_width' => 'decimal:2',
            'unfolded_height' => 'decimal:2',
            'as_per_artwork' => 'boolean',
            'window_width' => 'decimal:2',
            'window_height' => 'decimal:2',
            'window_from_left' => 'decimal:2',
            'window_from_bottom' => 'decimal:2',
            'per_case' => 'decimal:4',
            'per_case_specified' => 'boolean',
            'partition_case' => 'boolean',
            'band' => 'boolean',
            'band_sheets' => 'decimal:4',
            'has_label' => 'boolean',
            'bag_unit_price' => 'decimal:4',
            'bag_manual' => 'boolean',
            'bag_manual_amount' => 'integer',
            'bag_amount' => 'integer',
            'bag_subtotal' => 'integer',
            'case_cost' => 'integer',
            'shipping_cost' => 'integer',
            'sales_amount' => 'integer',
            'estimated_cost_total' => 'integer',
            'estimated_profit' => 'integer',
            'profit' => 'integer',
            'profit_rate' => 'decimal:2',
            'printed_on' => 'date',
            'delivered_on' => 'date',
            'completed_on' => 'date',
            'internal_cost' => 'integer',
            'work_sheet_order_files' => 'array',
            'work_sheet_sales_files' => 'array',
            'work_sheet_draft_files' => 'array',
            'purchase_amount' => 'integer',
            'purchase_cutting_amount' => 'integer',
            'purchase_plate_amount' => 'integer',
            'purchase_stored_plate_amount' => 'integer',
            'purchase_spot_color_amount' => 'integer',
            'purchase_cost_total' => 'integer',
            'actual_cost_total' => 'integer',
            'actual_sales_amount' => 'integer',
            'sold_amount' => 'integer',
            'previous_order_date' => 'date',
            'previous_order_quantity' => 'decimal:4',
            'manufacturing_sales' => 'integer',
            'legacy_size_no' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // 注番: next free number of the sequence (BR-OR-01 unique)
        self::creating(function (self $order): void {
            if (blank($order->order_number)) {
                $numbers = app(DocumentNumberGenerator::class);
                do {
                    $number = $numbers->next(self::NUMBER_SEQUENCE);
                } while (self::query()->where('order_number', $number)->exists());
                $order->order_number = $number;
            }
        });

        self::saving(function (self $order): void {
            if ($order->isDirty('customer_id')) {
                $customer = $order->customer_id === null ? null : Partner::query()->find($order->customer_id);
                $order->closing_day = $customer?->closing_day?->value;
                $order->customer_rounding = $customer?->rounding?->value;
                $order->unsetRelation('customer');
            }
            foreach (self::ZERO_WHEN_BLANK as $column) {
                if ($order->getAttribute($column) === null) {
                    $order->setAttribute($column, 0);
                }
            }
            if (! $order->bag_manual) {
                $order->bag_manual_amount = null;
            }

            $order->fillTotals();

            $userId = auth()->id();
            if ($userId !== null) {
                $order->updated_by = (int) $userId;
                if (! $order->exists) {
                    $order->created_by = (int) $userId;
                }
            }
        });
    }

    /** 端数区分 of the customer as of the order (blank ⇒ 切捨, BR-TX-01). */
    public function roundingMode(): RoundingMode
    {
        return RoundingMode::fromLabel($this->customer_rounding);
    }

    /** Recalculates the calculated columns after the tables changed (saved without events when something changed). */
    public function refreshTotals(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            $this->unsetRelation($table);
        }

        $this->fillTotals();
        if ($this->isDirty()) {
            $this->saveQuietly();
        }
    }

    /** Writes JobCosting::totals() of the record and its saved table rows into the calculated columns. */
    public function fillTotals(): void
    {
        $rows = fn (string $table): array => $this->exists
            ? $this->{$table}()->get()->map(fn (Model $row): array => $row->attributesToArray())->all()
            : [];

        $this->forceFill(JobCosting::totals($this->attributesToArray(), $rows('papers'), $rows('printings'), $rows('finishings'), $this->roundingMode()));
    }

    /** AM/PM of 時間 (BR-OR-13). */
    public function amPm(): string
    {
        return JobCosting::amPm($this->due_time);
    }

    /** FSC該当判定 (BR-OR-12). */
    public function fscLabel(): string
    {
        return $this->is_fsc ? 'FSC商品' : '';
    }

    /**
     * Orders a sale can still be booked on (06 lookup filter 売上残数量 >= 1, APP_04 "MUST be preserved").
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnsold(Builder $query): Builder
    {
        return $query->where('sales_remaining', '>=', 1);
    }

    /**
     * 変更履歴: the columns, 担当者 (user ids) and the rows of 用紙情報 / 印刷 / トムソン・NO・シルク・箔.
     *
     * @return array<string, mixed>
     */
    public function historySnapshot(): array
    {
        $snapshot = $this->columnSnapshot();
        $snapshot['assignees'] = $this->assignees()->pluck('users.id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
        foreach (self::TABLES as $table => $model) {
            $snapshot[$table] = $this->{$table}()->get()
                ->map(fn (Model $row): array => array_intersect_key($row->attributesToArray(), array_flip($model::COLUMNS)))
                ->all();
        }
        ksort($snapshot);

        return $snapshot;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function historyPreview(array $snapshot): Model
    {
        $copy = $this->columnPreview($snapshot);
        $copy->setRelation('assignees', User::query()->whereIn('id', (array) ($snapshot['assignees'] ?? []))->orderBy('name')->get());
        foreach (self::TABLES as $table => $model) {
            $copy->setRelation($table, new Collection(array_map(
                fn (mixed $row): Model => (new $model)->forceFill(array_intersect_key((array) $row, array_flip($model::COLUMNS))),
                (array) ($snapshot[$table] ?? []),
            )));
        }

        return $copy;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function restoreHistorySnapshot(array $snapshot): void
    {
        DB::transaction(function () use ($snapshot): void {
            $this->restoreColumns($snapshot);
            $this->assignees()->sync(User::query()->whereIn('id', (array) ($snapshot['assignees'] ?? []))->pluck('id')->all());
            foreach (self::TABLES as $table => $model) {
                $this->{$table}()->delete();
                foreach (array_values((array) ($snapshot[$table] ?? [])) as $sort => $row) {
                    $this->{$table}()->create([...array_intersect_key((array) $row, array_flip($model::COLUMNS)), 'sort' => $sort]);
                }
            }
            $this->unsetRelation('assignees');
            $this->refreshTotals();
        });
    }

    /** @return list<User|null> 作成者 and 更新者 */
    public function commentRecipients(): iterable
    {
        return [$this->creator, $this->updater];
    }

    /** @return BelongsTo<Partner, $this> 得意先(受注先) */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id');
    }

    /** @return BelongsTo<Partner, $this> 仕入先(刷版) */
    public function plateSupplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'plate_supplier_id');
    }

    /** @return BelongsTo<Partner, $this> 仕入先(製造場所) */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'manufacturer_id');
    }

    /** @return BelongsTo<Partner, $this> 仕入先(データ支給) */
    public function dataSupplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'data_supplier_id');
    }

    /** @return BelongsTo<Partner, $this> 仕入先(フィルム支給) */
    public function filmSupplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'film_supplier_id');
    }

    /** @return BelongsTo<User, $this> デザイナー */
    public function designer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designer_id');
    }

    /** @return BelongsTo<Machine, $this> 機械 (bag-making schedule) */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * 担当者 (legacy user selection, several allowed).
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'order_assignees')->withTimestamps();
    }

    /** @return HasMany<OrderPaper, $this> 用紙情報 */
    public function papers(): HasMany
    {
        return $this->hasMany(OrderPaper::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<OrderPrinting, $this> 印刷 */
    public function printings(): HasMany
    {
        return $this->hasMany(OrderPrinting::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<OrderFinishing, $this> トムソン・NO・シルク・箔 */
    public function finishings(): HasMany
    {
        return $this->hasMany(OrderFinishing::class)->orderBy('sort')->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
