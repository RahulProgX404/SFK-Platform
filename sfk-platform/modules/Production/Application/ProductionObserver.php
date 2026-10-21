<?php

declare(strict_types=1);

namespace Modules\Production\Application;

use Illuminate\Database\Eloquent\Model;
use Modules\Production\Application\DailyReports\BoxUsage;
use Modules\Production\Application\DailyReports\JobProduction;
use Modules\Production\Models\DailyReport;

/**
 * Keeps what follows from the daily reports in line after every save and delete (registered in
 * ProductionServiceProvider): the job's production totals and status (JobProduction) — for the old job too when the report
 * moved — and the box stock (BoxUsage).
 */
final class ProductionObserver
{
    public function __construct(private readonly JobProduction $jobs, private readonly BoxUsage $boxes) {}

    public function saved(Model $model): void
    {
        if (! $model instanceof DailyReport) {
            return;
        }

        $this->boxes->sync($model);
        $this->jobs->applyStatus($model);
        $this->refreshJobs($model);
    }

    public function deleted(Model $model): void
    {
        if (! $model instanceof DailyReport) {
            return;
        }

        $this->boxes->clear($model);
        $this->refreshJobs($model);
    }

    private function refreshJobs(DailyReport $report): void
    {
        foreach (array_unique(array_filter([$report->order_id, $report->getOriginal('order_id')])) as $orderId) {
            $this->jobs->refresh((int) $orderId);
        }
    }
}
