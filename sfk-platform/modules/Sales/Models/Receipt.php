<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistory;
use Modules\Core\Models\Concerns\TracksAuthors;
use Modules\MasterData\Models\Partner;

/**
 * 入金 of an invoice (SYSTEM_BRAIN APP_21 売入金管理（売上）): created with the invoice (BR-SA-13) or by hand for an
 * invoice not yet having one. 請求日 / 請求金額 (= 合計御買上金額) / 今回締日 / 担当者 are copied from the invoice when it
 * is chosen (legacy lookup); 回収予定月 / 回収予定日 / 締日(マスタ) are shown from the customer master.
 * 入金済 = Σ 入金額小計 of the rows (BR-AR-04), 入金不足 / 過入金 the difference to 請求金額 — refreshTotals() after the
 * rows changed. Every change recalculates the customer's receivables (ReceivableLedger, BR-AR-06).
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property int|null $invoice_id
 * @property int $customer_id
 * @property Carbon|null $billed_on
 * @property int $billed_amount
 * @property string|null $closing_day
 * @property int $settled_amount
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Invoice|null $invoice
 * @property-read Partner $customer
 * @property-read Collection<int, ReceiptLine> $lines
 * @property-read Collection<int, User> $assignees
 */
final class Receipt extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistory {
        historySnapshot as private columnSnapshot;
        historyPreview as private columnPreview;
        restoreHistorySnapshot as private restoreColumns;
    }

    protected $guarded = ['id', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'billed_on' => 'date',
            'billed_amount' => 'integer',
            'settled_amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $receipt): void {
            if ($receipt->isDirty('invoice_id') && $receipt->invoice_id !== null) {
                $invoice = Invoice::query()->find($receipt->invoice_id);
                if ($invoice !== null) {
                    $receipt->customer_id = (int) $invoice->customer_id;
                    $receipt->billed_on = $invoice->invoiced_on;
                    $receipt->billed_amount = $invoice->purchaseTotal();
                    $receipt->closing_day = $invoice->closing_day;
                }
                $receipt->unsetRelation('invoice');
            }
        });
    }

    /** Recalculates 入金済 from the saved rows (saved without events when it changed). */
    public function refreshTotals(): void
    {
        $this->unsetRelation('lines');
        $this->settled_amount = (int) $this->lines()->get()->sum(fn (ReceiptLine $line): int => $line->settled());
        if ($this->isDirty()) {
            $this->saveQuietly();
        }
    }

    /** 入金不足 (null while fully paid) */
    public function shortfall(): ?int
    {
        return $this->billed_amount > $this->settled_amount ? $this->billed_amount - $this->settled_amount : null;
    }

    /** 過入金 (null unless more was received) */
    public function overpayment(): ?int
    {
        return $this->billed_amount < $this->settled_amount ? $this->settled_amount - $this->billed_amount : null;
    }

    /**
     * Invoices a receipt can be entered for (legacy lookup: 入金判定 「不足」 → here: invoices without a receipt yet).
     *
     * @return Builder<Invoice>
     */
    public static function openInvoices(?int $current = null): Builder
    {
        return Invoice::query()
            ->where(fn (Builder $query): Builder => $query->whereDoesntHave('receipt')->when($current !== null, fn (Builder $q): Builder => $q->orWhereKey($current)))
            ->orderByDesc('id');
    }

    /**
     * 変更履歴: the columns, 担当者 (user ids) and the 入金明細 rows.
     *
     * @return array<string, mixed>
     */
    public function historySnapshot(): array
    {
        $snapshot = $this->columnSnapshot();
        $snapshot['assignees'] = $this->assignees()->pluck('users.id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
        $snapshot['lines'] = $this->lines()->get()
            ->map(fn (ReceiptLine $row): array => array_intersect_key($row->attributesToArray(), array_flip(ReceiptLine::COLUMNS)))
            ->all();
        ksort($snapshot);

        return $snapshot;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function historyPreview(array $snapshot): Model
    {
        $copy = $this->columnPreview($snapshot);
        $copy->setRelation('assignees', User::query()->whereIn('id', (array) ($snapshot['assignees'] ?? []))->orderBy('name')->get());
        $copy->setRelation('lines', new Collection(array_map(
            fn (mixed $row): ReceiptLine => (new ReceiptLine)->forceFill(array_intersect_key((array) $row, array_flip(ReceiptLine::COLUMNS))),
            (array) ($snapshot['lines'] ?? []),
        )));

        return $copy;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function restoreHistorySnapshot(array $snapshot): void
    {
        DB::transaction(function () use ($snapshot): void {
            $this->restoreColumns($snapshot);
            $this->assignees()->sync(User::query()->whereIn('id', (array) ($snapshot['assignees'] ?? []))->pluck('id')->all());
            $this->lines()->delete();
            foreach (array_values((array) ($snapshot['lines'] ?? [])) as $sort => $row) {
                $this->lines()->create([...array_intersect_key((array) $row, array_flip(ReceiptLine::COLUMNS)), 'sort' => $sort]);
            }
            $this->unsetRelation('assignees');
            $this->refreshTotals();
        });
    }

    /** @return BelongsTo<Invoice, $this> 請求書番号 */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Partner, $this> 得意先名 */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id');
    }

    /** @return HasMany<ReceiptLine, $this> 入金明細 */
    public function lines(): HasMany
    {
        return $this->hasMany(ReceiptLine::class)->orderBy('sort')->orderBy('id');
    }

    /** @return BelongsToMany<User, $this> 担当者 */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->orderBy('name');
    }
}
