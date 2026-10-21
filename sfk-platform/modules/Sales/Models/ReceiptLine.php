<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A row of 入金明細 (SYSTEM_BRAIN APP_21): 入金日, 入金金額, 手数料, 歩引き, 入金方法, 銀行. 入金額小計 = 入金金額 + 手数料 +
 * 歩引き (fee and discount settle the invoice too, BR-AR-04). The month it counts in: the calendar month of 入金日 (23)
 * and its 月度 by the invoice's 今回締日 (23-2) — BR-AR-06.
 *
 * @property int $id
 * @property int $receipt_id
 * @property Carbon|null $received_on
 * @property int $amount
 * @property int $fee
 * @property int $discount
 * @property string|null $method
 * @property string|null $bank
 * @property-read Receipt $receipt
 */
final class ReceiptLine extends Model
{
    /** Columns of a row, as kept in the 変更履歴 of the receipt. */
    public const COLUMNS = ['received_on', 'amount', 'fee', 'discount', 'method', 'bank'];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected static function booted(): void
    {
        // amounts left blank on the form are ¥0
        self::saving(function (self $line): void {
            foreach (['amount', 'fee', 'discount'] as $column) {
                $line->setAttribute($column, (int) $line->getAttribute($column));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'amount' => 'integer',
            'fee' => 'integer',
            'discount' => 'integer',
        ];
    }

    /** 入金額小計 */
    public function settled(): int
    {
        return (int) $this->amount + (int) $this->fee + (int) $this->discount;
    }

    /** @return BelongsTo<Receipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}
