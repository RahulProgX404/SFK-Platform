<?php

declare(strict_types=1);

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Money\Yen;

/**
 * A row of 損害 (SYSTEM_BRAIN APP_14): 項目 (紙 / 印刷), 枚数, 原価, 数量; 小計 = 原価 × 数量 in whole yen (.5 away from
 * zero). Its report's 合計損害額 follows every change.
 *
 * @property int $id
 * @property int $accident_report_id
 * @property string|null $item
 * @property string|null $sheets
 * @property string|null $cost
 * @property string|null $quantity
 * @property-read AccidentReport $report
 */
final class AccidentDamage extends Model
{
    /** Columns of a row, as kept in the 変更履歴 of the report. */
    public const COLUMNS = ['item', 'sheets', 'cost', 'quantity'];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'sheets' => 'decimal:4',
            'cost' => 'decimal:4',
            'quantity' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        $refresh = fn (self $damage) => AccidentReport::query()->find($damage->accident_report_id)?->refreshTotals();
        self::saved($refresh);
        self::deleted($refresh);
    }

    /** 小計 */
    public function subtotal(): int
    {
        return self::subtotalOf($this->cost, $this->quantity);
    }

    public static function subtotalOf(mixed $cost, mixed $quantity): int
    {
        if (! is_numeric($cost) || ! is_numeric($quantity)) {
            return 0;
        }

        return Yen::round(Yen::multiply((string) $cost, (string) $quantity), RoundingMode::HalfUp);
    }

    /** @return BelongsTo<AccidentReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(AccidentReport::class, 'accident_report_id');
    }
}
