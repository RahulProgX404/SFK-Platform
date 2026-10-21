<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Domain\Billing\BillingCycle;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Tax\TaxRate;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistory;
use Modules\Core\Models\Concerns\TracksAuthors;
use Modules\MasterData\Domain\Partners\DocumentRecipient;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Order;

/**
 * 売上 (SYSTEM_BRAIN APP_06): one sale line of a job (注番) for a customer (売上先).
 *
 * - 売上No = 売上入力ID as 7 digits, set when created (legacy 06_SalesEntry.bundle.js looks sales up that way); kept when
 *   given (legacy data).
 * - 締日 (請求締日), 端数区分, 納品書送付先 and 請求書送付先 of the customer are copied when the customer is chosen
 *   (legacy lookup). 消費税 = round(売上金額 × 税率) by that 端数区分 (BR-TX-05); 売上金額(税込) = 売上金額 + 消費税.
 * - 月度 (`billing_month`, BR-AR-01/02) by the effective closing day: 請求締日変更 (only 随時請求) if set, else 請求締日.
 *   請求日付 is kept only while that is 随時請求 (BR-SA-06; required then, checked by the form).
 * - The manual address (郵便番号(手動) / 納品先住所(手動)) is kept only while 「その他の送付先を入力 する」 (BR-SA-07).
 * - The job's 売上済数量 / 現在売上済金額 follow its sales (SaleOrderTotals, legacy Customine page 25/26); the
 *   customer's receivables are recalculated (ReceivableLedger) — both after every save and delete (SaleObserver).
 * - Deleting a sale takes its rows off the delivery notes and invoices (legacy delete flow of 06_SalesEntry.bundle.js).
 * - 編集 「不可」 (`locked`) keeps employees from changing it (BR-SA-16).
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property string|null $sales_number
 * @property string $kind
 * @property Carbon $sold_on
 * @property int $customer_id
 * @property string|null $closing_day
 * @property string|null $closing_day_override
 * @property Carbon|null $billing_date
 * @property Carbon $delivery_due_on
 * @property string $billing_month
 * @property string|null $customer_rounding
 * @property string|null $delivery_note_recipient
 * @property string|null $invoice_recipient
 * @property bool $other_address
 * @property string|null $manual_postal_code
 * @property string|null $manual_address
 * @property int|null $order_id
 * @property string|null $product_name
 * @property string|null $quantity
 * @property string|null $unit
 * @property string|null $unit_price
 * @property int $amount
 * @property int|null $estimated_cost
 * @property int|null $estimated_expense
 * @property string $tax_rate
 * @property int $tax
 * @property string|null $summary
 * @property list<string>|null $needs
 * @property list<string>|null $handover
 * @property bool $locked
 * @property int|null $delivery_note_id
 * @property int|null $invoice_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Partner $customer
 * @property-read Order|null $order
 * @property-read DeliveryNote|null $deliveryNote
 * @property-read Invoice|null $invoice
 * @property-read Collection<int, User> $assignees
 * @property-read Collection<int, SaleAllocation> $allocations
 */
final class Sale extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistory {
        historySnapshot as private columnSnapshot;
        historyPreview as private columnPreview;
        restoreHistorySnapshot as private restoreColumns;
    }

    /** Digits of 売上No (the record number, zero-padded). */
    public const NUMBER_DIGITS = 7;

    protected $guarded = ['id', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'kind' => '通常売上',
        'tax_rate' => '10%',
        'unit' => '枚',
        'locked' => false,
        'other_address' => false,
    ];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'sold_on' => 'date',
            'billing_date' => 'date',
            'delivery_due_on' => 'date',
            'other_address' => 'boolean',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'amount' => 'integer',
            'estimated_cost' => 'integer',
            'estimated_expense' => 'integer',
            'tax' => 'integer',
            'needs' => 'array',
            'handover' => 'array',
            'locked' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $sale): void {
            if ($sale->isDirty('customer_id')) {
                $customer = Partner::query()->find($sale->customer_id);
                $sale->closing_day = $customer?->closing_day?->value;
                $sale->customer_rounding = $customer?->rounding?->value;
                $sale->delivery_note_recipient = $customer?->delivery_note_recipient->value;
                $sale->invoice_recipient = $customer?->invoice_recipient->value;
                $sale->unsetRelation('customer');
            }
            if (! $sale->other_address) {
                $sale->manual_postal_code = null;
                $sale->manual_address = null;
            }
            if (! $sale->isAdHoc()) {
                $sale->billing_date = null;
            }

            $sale->amount = (int) $sale->amount;
            $sale->tax = self::taxRateOf($sale->tax_rate)->taxOn($sale->amount, $sale->roundingMode());
            $sale->billing_month = BillingCycle::monthFor($sale->sold_on, $sale->effectiveClosingDay())->key();
        });

        // 売上No: the record number, 7 digits
        self::created(function (self $sale): void {
            if (blank($sale->sales_number)) {
                $sale->sales_number = str_pad((string) $sale->id, self::NUMBER_DIGITS, '0', STR_PAD_LEFT);
                $sale->saveQuietly();
            }
        });

        // the rows copied to delivery notes and invoices go with the sale
        self::deleting(function (self $sale): void {
            DB::transaction(function () use ($sale): void {
                foreach ([DeliveryNoteLine::class => DeliveryNote::class, InvoiceLine::class => Invoice::class] as $line => $document) {
                    $documentIds = $line::query()->where('sale_id', $sale->id)->pluck(str($document)->classBasename()->snake()->append('_id')->toString())->unique()->all();
                    $line::query()->where('sale_id', $sale->id)->delete();
                    $document::query()->whereKey($documentIds)->get()->each(fn (Model $doc) => $doc->refreshTotals());
                }
            });
        });
    }

    /** 端数区分 of the customer as of the sale (blank ⇒ 切捨, BR-TX-01). */
    public function roundingMode(): RoundingMode
    {
        return RoundingMode::fromLabel($this->customer_rounding);
    }

    /** 締日判定: 請求締日変更 if set, else 請求締日 (BR-AR-02). */
    public function effectiveClosingDay(): ?ClosingDay
    {
        return BillingCycle::effectiveClosingDay(ClosingDay::tryFrom((string) $this->closing_day), ClosingDay::tryFrom((string) $this->closing_day_override));
    }

    /** 随時判定: billed on its own 請求日付, not by the closing cycle (BR-SA-06, BR-SA-09). */
    public function isAdHoc(): bool
    {
        return $this->effectiveClosingDay() === ClosingDay::AdHoc;
    }

    /** 売上金額(税込) */
    public function amountWithTax(): int
    {
        return (int) $this->amount + (int) $this->tax;
    }

    /**
     * 郵便番号(P) / 住所(P): where its 納品書 goes — the manual address if 「その他の送付先を入力」, else by 納品書送付先 the
     * parent company or the customer (BR-SA-07).
     *
     * @return array{postal_code: string|null, address: string|null}
     */
    public function deliveryAddress(): array
    {
        if ($this->other_address) {
            return ['postal_code' => $this->manual_postal_code, 'address' => $this->manual_address];
        }

        $customer = $this->customer;
        if (DocumentRecipient::tryFrom((string) $this->delivery_note_recipient) === DocumentRecipient::ParentCompany) {
            return ['postal_code' => $customer->getAttribute('parent_postal_code'), 'address' => $customer->getAttribute('parent_address')];
        }

        return ['postal_code' => $customer->getAttribute('postal_code'), 'address' => $customer->getAttribute('address')];
    }

    public static function taxRateOf(?string $label): TaxRate
    {
        return TaxRate::tryFrom((string) $label) ?? TaxRate::Standard;
    }

    /**
     * 変更履歴: the columns, 担当者 (user ids) and the 按分 rows.
     *
     * @return array<string, mixed>
     */
    public function historySnapshot(): array
    {
        $snapshot = $this->columnSnapshot();
        $snapshot['assignees'] = $this->assignees()->pluck('users.id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
        $snapshot['allocations'] = $this->allocations()->get()
            ->map(fn (SaleAllocation $row): array => array_intersect_key($row->attributesToArray(), array_flip(SaleAllocation::COLUMNS)))
            ->all();
        ksort($snapshot);

        return $snapshot;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function historyPreview(array $snapshot): Model
    {
        $copy = $this->columnPreview($snapshot);
        $copy->setRelation('assignees', User::query()->whereIn('id', (array) ($snapshot['assignees'] ?? []))->orderBy('name')->get());
        $copy->setRelation('allocations', new Collection(array_map(
            fn (mixed $row): SaleAllocation => (new SaleAllocation)->forceFill(array_intersect_key((array) $row, array_flip(SaleAllocation::COLUMNS))),
            (array) ($snapshot['allocations'] ?? []),
        )));

        return $copy;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function restoreHistorySnapshot(array $snapshot): void
    {
        DB::transaction(function () use ($snapshot): void {
            $this->restoreColumns($snapshot);
            $this->assignees()->sync(User::query()->whereIn('id', (array) ($snapshot['assignees'] ?? []))->pluck('id')->all());
            $this->allocations()->delete();
            foreach (array_values((array) ($snapshot['allocations'] ?? [])) as $sort => $row) {
                $this->allocations()->create([...array_intersect_key((array) $row, array_flip(SaleAllocation::COLUMNS)), 'sort' => $sort]);
            }
            $this->unsetRelation('assignees');
        });
    }

    /**
     * Not part of a version: the document links and the derived columns (they follow the others).
     *
     * @return list<string>
     */
    protected function historyExcludedColumns(): array
    {
        return [$this->getKeyName(), 'created_by', 'updated_by', $this->getCreatedAtColumn(), $this->getUpdatedAtColumn(), 'delivery_note_id', 'invoice_id'];
    }

    /** @return BelongsTo<Partner, $this> 売上先 */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id');
    }

    /** @return BelongsTo<Order, $this> 注番 */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<DeliveryNote, $this> 納品書番号 */
    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    /** @return BelongsTo<Invoice, $this> 請求書番号 */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsToMany<User, $this> 担当者 */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->orderBy('name');
    }

    /** @return HasMany<SaleAllocation, $this> 按分 */
    public function allocations(): HasMany
    {
        return $this->hasMany(SaleAllocation::class)->orderBy('sort')->orderBy('id');
    }
}
