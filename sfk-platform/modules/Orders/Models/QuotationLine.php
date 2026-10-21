<?php

declare(strict_types=1);

namespace Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Money\Yen;

/**
 * A quantity tier of 見積内容 (SYSTEM_BRAIN APP_09): 枚数, 単価; 金額 = 単価 × 枚数 in whole yen (.5 away from zero). Its
 * quotation's 見積金額(税抜) follows every change.
 *
 * @property int $id
 * @property int $quotation_id
 * @property string|null $sheets
 * @property string|null $unit_price
 * @property-read Quotation $quotation
 */
final class QuotationLine extends Model
{
    /** Columns of a row, as kept in the 変更履歴 of the quotation. */
    public const COLUMNS = ['sheets', 'unit_price'];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'sheets' => 'decimal:4',
            'unit_price' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        $refresh = fn (self $line) => Quotation::query()->find($line->quotation_id)?->refreshTotals();
        self::saved($refresh);
        self::deleted($refresh);
    }

    /** 金額 */
    public function amount(): int
    {
        return self::amountOf($this->unit_price, $this->sheets);
    }

    public static function amountOf(mixed $unitPrice, mixed $sheets): int
    {
        if (! is_numeric($unitPrice) || ! is_numeric($sheets)) {
            return 0;
        }

        return Yen::round(Yen::multiply((string) $unitPrice, (string) $sheets), RoundingMode::HalfUp);
    }

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
