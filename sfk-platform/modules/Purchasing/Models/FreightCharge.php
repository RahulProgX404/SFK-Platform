<?php

declare(strict_types=1);

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistory;
use Modules\Core\Models\Concerns\TracksAuthors;
use Modules\Orders\Models\Order;

/**
 * Carrier billing data of 15. 運送請求データ取込 (SYSTEM_BRAIN APP_15): 日付, 注番, 金額, 運送会社番号 and the purchase-style
 * figures, linked to the 05 purchase it became (納品(仕入)入力No) once entered. The legacy 日付 was text (CSV import); it is
 * a date here.
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property Carbon|null $charged_on
 * @property int|null $order_id
 * @property int|null $amount
 * @property string|null $carrier_number
 * @property int|null $purchase_id
 * @property string|null $unit_price
 * @property string|null $quantity
 * @property int|null $cutting_amount
 * @property int|null $plate_amount
 * @property int|null $stored_plate_amount
 * @property int|null $spot_color_amount
 * @property string|null $notes
 * @property-read Order|null $order
 * @property-read Purchase|null $purchase
 */
final class FreightCharge extends Model implements HasRecordComments, HasRecordHistory
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
            'charged_on' => 'date',
            'amount' => 'integer',
            'unit_price' => 'decimal:4',
            'quantity' => 'decimal:4',
            'cutting_amount' => 'integer',
            'plate_amount' => 'integer',
            'stored_plate_amount' => 'integer',
            'spot_color_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> 注番 */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Purchase, $this> 納品(仕入)入力No */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
