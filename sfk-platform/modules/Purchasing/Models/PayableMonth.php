<?php

declare(strict_types=1);

namespace Modules\Purchasing\Models;

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
use Modules\Purchasing\Domain\Payables\PayableCategories;
use Modules\Purchasing\Domain\Payables\PayableRoll;

/**
 * 買掛 of a supplier for one calendar month (SYSTEM_BRAIN APP_23B 23. 買掛管理). Every figure is recalculated from the
 * purchases (05) and payments (07) by PayableLedger (PayableRoll) — only 消費税端数調整 (`tax_adjustment`) is entered by
 * hand, which recalculates the month and the months after it. 仕入カテゴリ別内訳 is in the `cat_*` columns.
 *
 * @property int $id
 * @property int $supplier_id
 * @property string $year_month
 * @property Carbon $month_start 年月日 (first day of the month)
 * @property int $previous_balance
 * @property int $paid
 * @property int $net_balance
 * @property int $purchase_amount
 * @property int $cutting_amount
 * @property int $plate_amount
 * @property int $stored_plate_amount
 * @property int $spot_color_amount
 * @property int $tax_amount
 * @property int $tax_adjustment
 * @property int $month_total
 * @property int $closing_balance
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Partner $supplier
 */
final class PayableMonth extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistory;

    /** The figures written by the ledger. */
    public const FIGURES = ['previous_balance', 'paid', 'net_balance', ...PayableRoll::PURCHASE_FIGURES, 'tax_amount', 'tax_adjustment', 'month_total', 'closing_balance'];

    protected $guarded = ['id', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'month_start' => 'date',
            ...array_fill_keys([...self::FIGURES, ...PayableCategories::columns()], 'integer'),
        ];
    }

    /** 年月 as shown: 「2026年10月」 */
    public function monthLabel(): string
    {
        return YearMonth::parse($this->year_month)->label();
    }

    /** 材料+商品仕入 */
    public function materialsAndGoods(): int
    {
        return (int) $this->getAttribute('cat_materials') + (int) $this->getAttribute('cat_goods');
    }

    /** @return BelongsTo<Partner, $this> 得意先名 (the supplier) */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }
}
