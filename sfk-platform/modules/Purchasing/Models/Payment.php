<?php

declare(strict_types=1);

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistoryWithRelations;
use Modules\Core\Models\Concerns\TracksAuthors;

/**
 * A payment run of 07. 支払入力 (SYSTEM_BRAIN APP_07): 支払日 and one row per supplier (仕入先, 金種名, 支払額, 備考).
 * Like the legacy app (auto-fill-payment-date.js, BR-PU-08), every row's 出金日 is the 支払日 and the run counts in the
 * payables of that month; 支払金額合計 = Σ 支払額 — refreshTotals() after the rows changed.
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property Carbon $paid_on
 * @property int $total
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Collection<int, PaymentLine> $lines
 */
final class Payment extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistoryWithRelations;

    protected $guarded = ['id', 'total', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'paid_on' => 'date',
            'total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // row by row, so the payables of their suppliers are recalculated
        self::deleting(function (self $payment): void {
            $payment->lines()->get()->each(fn (PaymentLine $line) => $line->delete());
        });

        self::saved(function (self $payment): void {
            if ($payment->wasChanged('paid_on')) {
                foreach ($payment->lines()->get() as $line) {
                    $line->withdrawn_on = $payment->paid_on;
                    $line->save();
                }
            }
        });
    }

    /** Recalculates 支払金額合計 from the saved rows (saved without events when it changed). */
    public function refreshTotals(): void
    {
        $this->unsetRelation('lines');
        $this->forceFill(['total' => (int) $this->lines()->sum('amount')]);
        if ($this->isDirty()) {
            $this->saveQuietly();
        }
    }

    /** @return array<string, list<string>> */
    protected function historyTables(): array
    {
        return ['lines' => PaymentLine::COLUMNS];
    }

    protected function afterHistoryRestore(): void
    {
        $this->refreshTotals();
    }

    /** @return HasMany<PaymentLine, $this> 支払内容詳細 */
    public function lines(): HasMany
    {
        return $this->hasMany(PaymentLine::class)->orderBy('sort')->orderBy('id');
    }
}
