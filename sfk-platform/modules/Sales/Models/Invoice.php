<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Domain\Billing\BillingCycle;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\TracksAuthors;
use Modules\Sales\Domain\Documents\TaxBreakdown;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Models\Concerns\IsSalesDocument;

/**
 * 請求書 (SYSTEM_BRAIN APP_11): a statement invoice of a customer for one closing cycle, created from delivered sales
 * (BR-SA-09, InvoiceIssuer), numbered from the invoice sequence (BR-SA-14). Statement arithmetic (BR-SA-11):
 * 繰越金額 = 前月御請求額 − ご入金額; 合計御買上金額 = 今回御買上額(税抜) + 今回消費税額; 御請求額 = 繰越金額 +
 * 合計御買上金額 — recalculated on every save. 請求月度 (`billing_month`) = 月度 of 請求日付 by 今回締日 (BR-AR-01): the
 * cycle receivable it belongs to (締日売掛連携キー).
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property string $send_status
 * @property string $kind
 * @property string|null $registration_number
 * @property Carbon|null $invoiced_on
 * @property string|null $closing_day
 * @property string|null $billing_month
 * @property string|null $postal_code
 * @property string|null $address
 * @property int $previous_amount
 * @property int $received_amount
 * @property int $carried_amount
 * @property int $billed_total
 * @property string|null $notes
 * @property list<string>|null $pdf_files
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Collection<int, InvoiceLine> $lines
 * @property-read Collection<int, Sale> $sales
 * @property-read Receipt|null $receipt
 */
final class Invoice extends Model implements HasRecordComments, HasRecordHistory
{
    use IsSalesDocument {
        fillTotals as private fillDocumentTotals;
    }
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }

    protected $guarded = ['id', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'send_status' => '未',
        'kind' => '通常請求',
        'registration_number' => SaleChoices::REGISTRATION_NUMBER,
    ];

    public static function numberSequence(): string
    {
        return 'invoice';
    }

    public static function lineModel(): string
    {
        return InvoiceLine::class;
    }

    protected static function booted(): void
    {
        self::saving(function (self $invoice): void {
            $invoice->carried_amount = (int) $invoice->previous_amount - (int) $invoice->received_amount;
            $invoice->billed_total = $invoice->carried_amount + (int) $invoice->subtotal + (int) $invoice->tax_total;
            $invoice->billing_month = $invoice->invoiced_on === null
                ? null
                : BillingCycle::monthFor($invoice->invoiced_on, ClosingDay::tryFrom((string) $invoice->closing_day))->key();
        });
    }

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'invoiced_on' => 'date',
            'previous_amount' => 'integer',
            'received_amount' => 'integer',
            'carried_amount' => 'integer',
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'billed_total' => 'integer',
            'pdf_files' => 'array',
        ];
    }

    /** 合計御買上金額 */
    public function purchaseTotal(): int
    {
        return (int) $this->subtotal + (int) $this->tax_total;
    }

    protected function fillTotals(TaxBreakdown $breakdown): void
    {
        $this->fillDocumentTotals($breakdown);
        $this->carried_amount = (int) $this->previous_amount - (int) $this->received_amount;
        $this->billed_total = $this->carried_amount + (int) $this->subtotal + (int) $this->tax_total;
    }

    /** @return HasMany<InvoiceLine, $this> 請求内容詳細 */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<Sale, $this> the sales on this invoice (請求書作成 済) */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** @return HasOne<Receipt, $this> its record in 21. 売入金管理 (BR-SA-13) */
    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    /** @return BelongsToMany<User, $this> 担当者 */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->orderBy('name');
    }
}
