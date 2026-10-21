<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\TracksAuthors;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Models\Concerns\IsSalesDocument;

/**
 * 納品書 (SYSTEM_BRAIN APP_10): created from sales per customer (BR-SA-08, DeliveryNoteIssuer), numbered from the
 * delivery-note sequence (BR-SA-14). 郵便番号 / 納品先 are the address it goes to (manual > parent company > customer,
 * BR-SA-07, taken from the first sale), 登録番号 the company's T-number (BR-TX-08). The issuance categories of the
 * customer (請求書発行区分 / 納品書発行区分) are always shown from the master (legacy JS copied them after save).
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property string $send_status
 * @property string|null $registration_number
 * @property Carbon|null $sold_on
 * @property Carbon|null $billing_due_on
 * @property string|null $postal_code
 * @property string|null $address
 * @property string|null $closing_day
 * @property string|null $notes
 * @property list<string>|null $pdf_files
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Collection<int, DeliveryNoteLine> $lines
 * @property-read Collection<int, Sale> $sales
 */
final class DeliveryNote extends Model implements HasRecordComments, HasRecordHistory
{
    use IsSalesDocument;
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }

    protected $guarded = ['id', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'send_status' => '未',
        'registration_number' => SaleChoices::REGISTRATION_NUMBER,
    ];

    public static function numberSequence(): string
    {
        return 'delivery_note';
    }

    public static function lineModel(): string
    {
        return DeliveryNoteLine::class;
    }

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'sold_on' => 'date',
            'billing_due_on' => 'date',
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'pdf_files' => 'array',
        ];
    }

    /** @return HasMany<DeliveryNoteLine, $this> 納品内容詳細 */
    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryNoteLine::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<Sale, $this> the sales on this note (納品書作成 済) */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** @return BelongsToMany<User, $this> 担当者 */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->orderBy('name');
    }
}
