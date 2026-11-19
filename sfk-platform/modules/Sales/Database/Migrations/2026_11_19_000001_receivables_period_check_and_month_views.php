<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Cache\ReferenceCache;
use Modules\Sales\Filament\Resources\Receivables\Tables\ReceivablesTable;

/**
 * 売掛 (docs/receivables.md):
 *
 * - The period of `receivable_months` is a real type: `month_start` (DATE, the 1st) and the key `year_month` (ISO YYYY-MM)
 *   must name the same month — a CHECK on PostgreSQL (defect 6; the unique key (basis, customer_id, year_month) exists).
 * - The stored month lists of 23 / 23-2 become relative to the 基準月 instead of today (defect 7): a 年月日 condition
 *   今月 / 先月 turns into 対象月 当月 / 前月, and 先々月売掛一覧 (23, 23-2) and 当月 / 先月売掛一覧 (23-2) are added after the
 *   legacy ones when missing. Views that were never stored get them from the code (ReceivablesTable::defaultViews()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("update receivable_months set month_start = to_date(year_month || '-01', 'YYYY-MM-DD') where to_char(month_start, 'YYYY-MM-DD') <> year_month || '-01'");
            DB::statement('alter table receivable_months drop constraint if exists receivable_months_period_check');
            DB::statement("alter table receivable_months add constraint receivable_months_period_check check (year_month ~ '^[0-9]{4}-(0[1-9]|1[0-2])$' and to_char(month_start, 'YYYY-MM-DD') = year_month || '-01')");
        }

        $changed = false;
        foreach (['receivable' => ['当月売掛一覧' => ['先々月売掛一覧' => 2]], 'receivable-cycle' => ['売掛一覧' => ['当月売掛一覧' => 0, '先月売掛一覧' => 1, '先々月売掛一覧' => 2]]] as $code => $additions) {
            $appId = DB::table('business_apps')->where('code', $code)->value('id');
            if ($appId === null) {
                continue;
            }

            foreach (DB::table('app_list_views')->where('business_app_id', $appId)->get(['id', 'filter']) as $view) {
                $filter = json_decode((string) $view->filter, true);
                $converted = $this->relativeToBaseMonth(is_array($filter) ? $filter : []);
                if ($converted !== null) {
                    DB::table('app_list_views')->where('id', $view->id)->update(['filter' => json_encode($converted, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
                    $changed = true;
                }
            }

            foreach ($additions as $after => $views) {
                $missing = array_diff_key($views, array_flip(DB::table('app_list_views')->where('business_app_id', $appId)->pluck('name')->all()));
                if ($missing === []) {
                    continue;
                }
                $position = DB::table('app_list_views')->where('business_app_id', $appId)->where('name', $after)->value('sort_order')
                    ?? DB::table('app_list_views')->where('business_app_id', $appId)->max('sort_order') ?? 0;
                DB::table('app_list_views')->where('business_app_id', $appId)->where('sort_order', '>', $position)->increment('sort_order', count($missing));
                foreach (array_keys($missing) as $index => $name) {
                    DB::table('app_list_views')->insert([
                        'business_app_id' => $appId,
                        'name' => $name,
                        'type' => 'table',
                        'columns' => json_encode(ReceivablesTable::LIST_COLUMNS),
                        'filter' => json_encode(['match' => 'and', 'conditions' => [ReceivablesTable::offsetCondition($missing[$name])], 'sorts' => []]),
                        'sort_order' => (int) $position + $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $changed = true;
            }
        }

        if ($changed) {
            ReferenceCache::bump();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table receivable_months drop constraint if exists receivable_months_period_check');
        }
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>|null the filter with 年月日 今月 / 先月 as 対象月, null when it has neither
     */
    private function relativeToBaseMonth(array $filter): ?array
    {
        $offsets = ['this_month' => 0, 'last_month' => 1];
        $found = false;
        $conditions = [];
        foreach ((array) ($filter['conditions'] ?? []) as $condition) {
            if (is_array($condition) && ($condition['field'] ?? null) === 'month_start' && ($condition['operator'] ?? null) === 'eq' && isset($offsets[$condition['mode'] ?? ''])) {
                $conditions[] = ReceivablesTable::offsetCondition($offsets[$condition['mode']]);
                $found = true;

                continue;
            }
            $conditions[] = $condition;
        }

        return $found ? [...$filter, 'conditions' => $conditions] : null;
    }
};
