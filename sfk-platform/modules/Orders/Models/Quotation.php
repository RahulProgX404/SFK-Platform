<?php

declare(strict_types=1);

namespace Modules\Orders\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistoryWithRelations;
use Modules\Core\Models\Concerns\TracksAuthors;
use Modules\MasterData\Models\Partner;

/**
 * A quotation of 09. 見積書管理 (SYSTEM_BRAIN APP_09): 見積日付, 得意先, 担当者, 見積書番号, 品名 and the specification
 * (サイズ, 紙, 印刷, 窓, 口糊, three free lines), the quantity tiers 見積内容 and 備考 / 見積書PDF. 見積金額(税抜) = Σ 金額 —
 * refreshTotals() after the tiers changed.
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property Carbon $quoted_on
 * @property int|null $customer_id
 * @property string|null $quote_number
 * @property string|null $product_name
 * @property string|null $size
 * @property string|null $paper
 * @property string|null $printing
 * @property string|null $window
 * @property string|null $flap_glue
 * @property string|null $free_text_1
 * @property string|null $free_text_2
 * @property string|null $free_text_3
 * @property int $total
 * @property string|null $notes
 * @property list<string>|null $pdf_files
 * @property-read Partner|null $customer
 * @property-read Collection<int, User> $assignees
 * @property-read Collection<int, QuotationLine> $lines
 */
final class Quotation extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistoryWithRelations;

    public const DEFAULT_SIZE = '左右　× 天地';

    protected $guarded = ['id', 'total', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'quoted_on' => 'date',
            'total' => 'integer',
            'pdf_files' => 'array',
        ];
    }

    /** Recalculates 見積金額(税抜) from the saved tiers (saved without events when it changed). */
    public function refreshTotals(): void
    {
        $this->unsetRelation('lines');
        $this->forceFill(['total' => (int) $this->lines()->get()->sum(fn (QuotationLine $line): int => $line->amount())]);
        if ($this->isDirty()) {
            $this->saveQuietly();
        }
    }

    /** ファイル保存名: 「見積書番号_得意先名」 */
    public function fileName(): string
    {
        return $this->quote_number.'_'.$this->customer?->name;
    }

    /** @return list<string> */
    protected function historyUsers(): array
    {
        return ['assignees'];
    }

    /** @return array<string, list<string>> */
    protected function historyTables(): array
    {
        return ['lines' => QuotationLine::COLUMNS];
    }

    protected function afterHistoryRestore(): void
    {
        $this->refreshTotals();
    }

    /** @return BelongsTo<Partner, $this> 得意先名 */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id');
    }

    /** @return BelongsToMany<User, $this> 担当者 */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->orderBy('name');
    }

    /** @return HasMany<QuotationLine, $this> 見積内容 */
    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('sort')->orderBy('id');
    }
}
