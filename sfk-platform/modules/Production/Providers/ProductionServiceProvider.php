<?php

declare(strict_types=1);

namespace Modules\Production\Providers;

use App\Modules\ModuleServiceProvider;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Notifications\NotificationKind;
use Modules\Core\Application\Notifications\NotificationKinds;
use Modules\Core\Filament\Records\RelatedLists;
use Modules\Orders\Models\Order;
use Modules\Production\Application\AccidentReports\AccidentAlert;
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

        // a new 14. 事故報告管理 record → administrators and 営業関係者 (CreateAccidentReport)
        $this->app->make(NotificationKinds::class)->register(new NotificationKind(
            key: AccidentAlert::NOTIFICATION,
            label: '事故報告',
            appCode: 'accident-report',
            icon: Heroicon::OutlinedExclamationTriangle,
            color: 'danger',
        ));

        // 日報一覧 on a 04. 受注入力 record
        $this->app->make(RelatedLists::class)->provide('orders.daily_reports', fn (Model $order): array => $order instanceof Order
            ? DailyReportRows::ofOrder($order)
            : ['columns' => [], 'numeric' => [], 'rows' => [], 'urls' => []]);
    }
}
