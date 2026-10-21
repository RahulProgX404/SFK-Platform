<?php

declare(strict_types=1);

namespace Modules\Analytics\Application;

use Illuminate\Support\Facades\DB;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Analytics\Models\SalesPlan;
use Modules\Sales\Models\Sale;

/**
 * 売上実績を反映 of 25. 販売先別売上計画: the monthly actuals of a plan = the customer's 売上金額 (税抜) of 06. 売上入力 in each
 * month (売上日) of the plan's business year. No legacy automation filled them (entered by hand, SYSTEM_BRAIN APP_25);
 * the actuals stay editable.
 */
final class SalesPlanActuals
{
    /**
     * @param  iterable<SalesPlan>  $plans
     * @return int the plans updated
     */
    public function fill(iterable $plans): int
    {
        $updated = 0;
        DB::transaction(function () use ($plans, &$updated): void {
            foreach ($plans as $plan) {
                if ($plan->customer_id === null || $plan->planned_on === null) {
                    continue;
                }

                foreach (FiscalYear::months($plan->planned_on) as $column => $month) {
                    $plan->setAttribute('actual_'.$column, (int) Sale::query()->where('customer_id', $plan->customer_id)
                        ->whereBetween('sold_on', [$month->firstDay()->toDateString(), $month->lastDay()->toDateString()])
                        ->sum('amount'));
                }
                $plan->save();
                $updated++;
            }
        });

        return $updated;
    }
}
