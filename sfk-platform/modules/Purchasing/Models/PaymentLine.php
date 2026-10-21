<?php

declare(strict_types=1);

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\MasterData\Models\Partner;
use Modules\Purchasing\Domain\PurchaseChoices;

/**
 * A row of 支払内容詳細 (SYSTEM_BRAIN APP_07): 仕入先 (仕入先 only), 金種名, 支払額, 出金日 (= the run's 支払日, BR-PU-08) and
 * 備考. It counts in the supplier's payable of the month of 支払日 (支払年月).
 *
 * @property int $id
 * @property int $payment_id
 * @property int|null $supplier_id
 * @property string $method
 * @property int $amount
 * @property Carbon|null $withdrawn_on
 * @property string|null $notes
 * @property-read Payment $payment
 * @property-read Partner|null $supplier
 */
final class PaymentLine extends Model
{
    /** Columns of a row, as kept in the 変更履歴 of the payment. */
    public const COLUMNS = ['supplier_id', 'method', 'amount', 'notes'];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** @var array<string, mixed> */
    protected $attributes = ['method' => PurchaseChoices::DEFAULT_PAYMENT_METHOD];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'withdrawn_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $line): void {
            $line->amount = (int) $line->getAttribute('amount');
            $line->method = filled($line->getAttribute('method')) ? $line->method : PurchaseChoices::DEFAULT_PAYMENT_METHOD;
            $paidOn = $line->payment()->value('paid_on');
            $line->withdrawn_on = $paidOn === null ? null : Carbon::parse((string) $paidOn);
        });
    }

    /** 支払年月 */
    public function month(): ?YearMonth
    {
        return $this->withdrawn_on === null ? null : YearMonth::fromDate($this->withdrawn_on);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Partner, $this> 仕入先 */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }
}
