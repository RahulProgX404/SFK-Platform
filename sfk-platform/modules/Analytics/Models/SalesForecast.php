<?php

declare(strict_types=1);

namespace Modules\Analytics\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Core\Models\Concerns\TracksAuthors;

/**
 * 予想 and 仕掛 of a 担当者 for a business year (May–April) on 分析 → 売上分析 (legacy SalesAnalysis.bundle.js, the
 * `_<月>予想` / `_<月>仕掛` fields of its app): typed in on the screen, one amount per month.
 *
 * @property int $id
 * @property int $user_id
 * @property int $fiscal_year
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read User $user
 */
final class SalesForecast extends Model
{
    use TracksAuthors;

    /** forecast = 予想, wip = 仕掛 */
    public const KINDS = ['forecast' => '予想', 'wip' => '仕掛'];

    protected $guarded = ['id', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'fiscal_year' => 'integer',
            ...array_fill_keys([...FiscalYear::columns('forecast_'), ...FiscalYear::columns('wip_')], 'integer'),
        ];
    }

    /** @return array<int, int> calendar month => amount of 予想 (forecast) or 仕掛 (wip) */
    public function months(string $kind): array
    {
        $months = [];
        foreach (FiscalYear::MONTHS as $month) {
            $months[$month] = (int) $this->getAttribute($kind.'_'.FiscalYear::column($month));
        }

        return $months;
    }

    /** @return BelongsTo<User, $this> 担当者 */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
