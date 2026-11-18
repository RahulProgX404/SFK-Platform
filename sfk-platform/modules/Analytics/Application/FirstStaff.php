<?php

declare(strict_types=1);

namespace Modules\Analytics\Application;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The first 担当者 of a sale (its lowest sale_user row) — whom 売上分析 and the dashboard count a sale for. Found for all
 * sales at once (grouped), not with a subquery per sale: PostgreSQL ran `id = (select min(id) … where sale_id = sales.id)`
 * twice for every sale. Measured on 6,000 sales (2026-11-18, same results for every 担当者): 売上分析 31 → 12 ms,
 * the 担当者 ranking 16 → 7 ms, a 個人 filter 10 → 6 ms (docs/performance.md).
 */
final class FirstStaff
{
    /** sale_id, id (the first sale_user row of every sale) — join it as a derived table */
    public static function rows(): Builder
    {
        return DB::table('sale_user')->select('sale_id')->selectRaw('min(id) as id')->groupBy('sale_id');
    }

    /** sale_id of the sales whose first 担当者 is the user — for whereIn('sales.id', …) */
    public static function salesOf(int $userId): Builder
    {
        return DB::table('sale_user as mine')
            ->select('mine.sale_id')
            ->where('mine.user_id', $userId)
            ->whereIn('mine.id', DB::table('sale_user')->selectRaw('min(id)')->groupBy('sale_id'));
    }
}
