<?php

declare(strict_types=1);

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistory;
use Modules\Core\Models\Concerns\TracksAuthors;

/**
 * Historical sales of 22. 過去の売上データ (SYSTEM_BRAIN APP_22): 担当者, 異常値フラグ (正常 / 除外), 決算最終月初 and the twelve
 * monthly amounts May–April; 小計 / 年間合計 = Σ months (the legacy fields were identical), kept on save for lists and
 * charts. 決算年度 = 「YYYY年」 of 決算最終月初.
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property string|null $salesperson
 * @property string $flag
 * @property Carbon|null $fiscal_month_start
 * @property int $total
 * @property string|null $record_key
 */
final class PastSale extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments, TracksAuthors {
        TracksAuthors::commentRecipients insteadof RecordsComments;
    }
    use RecordsHistory;

    public const FLAGS = ['正常', '除外'];

    protected $guarded = ['id', 'total', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    /** @var array<string, mixed> */
    protected $attributes = ['flag' => '正常'];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'fiscal_month_start' => 'date',
            'total' => 'integer',
            ...array_fill_keys(FiscalYear::columns(), 'integer'),
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $sale): void {
            $sale->forceFill(['total' => self::sumOf($sale->only(FiscalYear::columns()))]);
        });
    }

    /** @param  array<mixed>  $months  monthly amounts (blank = 0) */
    public static function sumOf(array $months): int
    {
        return array_sum(array_map(fn (mixed $amount): int => is_numeric($amount) ? (int) $amount : 0, $months));
    }

    /** 決算年度 「2024年」 */
    public function fiscalYear(): ?string
    {
        return $this->fiscal_month_start?->format('Y年');
    }
}
