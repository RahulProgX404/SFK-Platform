<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistory;
use Modules\Core\Models\Concerns\TracksAuthors;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Domain\Receivables\ReceivableBasis;

/**
 * 売掛 of a customer for one month (SYSTEM_BRAIN APP_23 売掛管理 = calendar basis, APP_23-2 売掛管理(締日別) = cycle
 * basis). Every figure is recalculated from the sales and receipts by ReceivableLedger (ReceivableRoll, BR-AR-03) — only
 * 消費税端数調整 (`tax_adjustment`) is entered by hand, which recalculates the month and the months after it. 年月 is
 * `year_month` (YYYY-MM; shown 「YYYY年MM月」), 締日 the customer's closing day as of the recalculation (cycle basis).
 *
 * @property int $id
 * @property ReceivableBasis $basis
 * @property int $customer_id
 * @property string $year_month
 * @property Carbon $month_start 年月日 (first day of the month)
 * @property string|null $closing_day
 * @property int $previous_balance
 * @property int $received
 * @property int $net_balance
 * @property int $sales_amount
 * @property int $sales_standard
 * @property int $sales_reduced
 * @property int $sales_exempt
 * @property int $sales_out_of_scope
 * @property int $tax_standard
 * @property int $tax_reduced
 * @property int $tax_amount
 * @property int $tax_adjustment
 * @property int $month_total
 * @property int $closing_balance
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Partner $customer
 */
final class ReceivableMonth extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistory;

    /** The figures written by the ledger. */
    public const FIGURES = [
        'previous_balance', 'received', 'net_balance', 'sales_amount', 'sales_standard', 'sales_reduced', 'sales_exempt', 'sales_out_of_scope',
        'tax_standard', 'tax_reduced', 'tax_amount', 'tax_adjustment', 'month_total', 'closing_balance',
    ];

    protected $guarded = ['id', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'basis' => ReceivableBasis::class,
            'month_start' => 'date',
            ...array_fill_keys(self::FIGURES, 'integer'),
        ];
    }

    /** 年月 as shown: 「2026年10月」 */
    public function monthLabel(): string
    {
        return YearMonth::parse($this->year_month)->label();
    }

    /** 次月分判定: 「要作成」 while the balance is open (BR-AR-05). */
    public function carriesForward(): string
    {
        return $this->closing_balance > 0 ? '要作成' : '';
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOfBasis(Builder $query, ReceivableBasis $basis): Builder
    {
        return $query->where('receivable_months.basis', $basis->value);
    }

    /** @return BelongsTo<Partner, $this> 得意先名 */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id');
    }
}
