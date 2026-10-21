<?php

declare(strict_types=1);

namespace Modules\Purchasing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Application\Numbering\DocumentNumberGenerator;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistoryWithRelations;
use Modules\Core\Models\Concerns\TracksAuthors;
use Modules\MasterData\Domain\Partners\PurchaseCategory;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Order;

/**
 * A supplier delivery (納品(仕入)) of 05. 納品(仕入)入力 (SYSTEM_BRAIN APP_05): numbered from the purchase sequence
 * (BR-PU-01, numbers already used are skipped), 仕入金額合計 = 金額 + 断裁 + 版代 + 置き版代 + 特色 on every save
 * (BR-PU-03). 仕入カテゴリ判定 = the line's 仕入カテゴリ, else the supplier's (BR-PU-04). It counts in the supplier's
 * payable of the calendar month of 納品(仕入)日 and in its job's 仕入 costs (PurchasingObserver).
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property string|null $purchase_number
 * @property Carbon $purchased_on
 * @property int $supplier_id
 * @property PurchaseCategory|null $category
 * @property int|null $order_id
 * @property string|null $product_name
 * @property string|null $quantity
 * @property string|null $unit_price
 * @property int $amount
 * @property int $cutting_amount
 * @property int $plate_amount
 * @property int $stored_plate_amount
 * @property int $spot_color_amount
 * @property int $total
 * @property bool $is_fsc
 * @property bool $period_end
 * @property list<string>|null $overruns
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Partner $supplier
 * @property-read Order|null $order
 * @property-read Collection<int, User> $notifiedUsers
 */
final class Purchase extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistoryWithRelations;

    public const NUMBER_SEQUENCE = 'purchase';

    /** 金額 and the cost components, in the order of 仕入金額合計. */
    public const AMOUNTS = ['amount', 'cutting_amount', 'plate_amount', 'stored_plate_amount', 'spot_color_amount'];

    protected $guarded = ['id', 'total', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'purchased_on' => 'date',
            'category' => PurchaseCategory::class,
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            ...array_fill_keys([...self::AMOUNTS, 'total'], 'integer'),
            'is_fsc' => 'boolean',
            'period_end' => 'boolean',
            'overruns' => 'array',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $purchase): void {
            if (blank($purchase->purchase_number)) {
                $numbers = app(DocumentNumberGenerator::class);
                do {
                    $number = $numbers->next(self::NUMBER_SEQUENCE);
                } while (self::query()->where('purchase_number', $number)->exists());
                $purchase->purchase_number = $number;
            }
        });

        self::saving(function (self $purchase): void {
            foreach (self::AMOUNTS as $column) {
                $purchase->setAttribute($column, (int) $purchase->getAttribute($column));
            }
            $purchase->forceFill(['total' => array_sum(array_map(fn (string $column): int => (int) $purchase->getAttribute($column), self::AMOUNTS))]);
        });
    }

    /** 仕入カテゴリ判定 (BR-PU-04) */
    public function effectiveCategory(): ?PurchaseCategory
    {
        return $this->category ?? $this->supplier->purchase_category;
    }

    /** 端数区分(仕入) of the supplier (blank ⇒ 切捨, BR-TX-02). */
    public function roundingMode(): RoundingMode
    {
        return RoundingMode::fromLabel($this->supplier->supplier_rounding?->value);
    }

    /** 仕入年月: the payable month it counts in. */
    public function month(): YearMonth
    {
        return YearMonth::fromDate($this->purchased_on);
    }

    /** @return list<string> */
    protected function historyUsers(): array
    {
        return ['notifiedUsers'];
    }

    /** @return BelongsTo<Partner, $this> 仕入先 */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }

    /** @return BelongsTo<Order, $this> 受注No */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsToMany<User, $this> 通知先 of 仕入原価超過 */
    public function notifiedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->orderBy('name');
    }
}
