<?php

declare(strict_types=1);

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistory;
use Modules\Core\Models\Concerns\TracksAuthors;
use Modules\MasterData\Models\Partner;

/**
 * A sales plan of 25. 販売先別売上計画 (SYSTEM_BRAIN APP_25): the customer (its 得意先コード, グループ名, 分類 from the master),
 * 中分類, 日付 (the business year it falls in, May–April), 計画値, the monthly plans and actuals; 年間合計 = Σ actuals, kept
 * on save; 計画比進捗率 = ROUNDUP(年間合計 / 計画値 × 100, 1). The monthly plans are not summed into 計画値 (independent
 * like the legacy field).
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property int|null $customer_id
 * @property string|null $sub_category
 * @property Carbon|null $planned_on
 * @property int|null $plan_total
 * @property int $actual_total
 * @property string|null $notes
 * @property-read Partner|null $customer
 */
final class SalesPlan extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistory;

    protected $guarded = ['id', 'actual_total', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'planned_on' => 'date',
            'plan_total' => 'integer',
            'actual_total' => 'integer',
            ...array_fill_keys([...FiscalYear::columns('plan_'), ...FiscalYear::columns('actual_')], 'integer'),
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $plan): void {
            $plan->forceFill(['actual_total' => PastSale::sumOf($plan->only(FiscalYear::columns('actual_')))]);
        });
    }

    /** 計画比進捗率 (%, 1 decimal) */
    public function progress(): ?string
    {
        return FiscalYear::progress($this->actual_total, $this->plan_total);
    }

    /** 年 「2025年05月」 of 日付 */
    public function yearLabel(): ?string
    {
        return $this->planned_on?->format('Y年m月');
    }

    /** @return BelongsTo<Partner, $this> 得意先 */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id');
    }
}
