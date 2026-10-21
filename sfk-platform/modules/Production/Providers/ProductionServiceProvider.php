<?php

declare(strict_types=1);

namespace Modules\Production\Providers;

use App\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Records\RelatedLists;
use Modules\Orders\Models\Order;
use Modules\Production\Application\ProductionObserver;
use Modules\Production\Filament\Resources\DailyReports\DailyReportRows;
use Modules\Production\Models\DailyReport;

final class ProductionServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'production';

    protected function registerModule(): void
    {
        // Bind module services / repositories here.
    }

    protected function bootModule(): void
    {
        // the jobs' production totals and the box stock follow the daily reports
        DailyReport::observe(ProductionObserver::class);

        // 日報一覧 on a 04. 受注入力 record
        $this->app->make(RelatedLists::class)->provide('orders.daily_reports', fn (Model $order): array => $order instanceof Order
            ? DailyReportRows::ofOrder($order)
            : ['columns' => [], 'numeric' => [], 'rows' => [], 'urls' => []]);
    }
}
