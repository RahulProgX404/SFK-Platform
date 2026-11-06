<?php

declare(strict_types=1);

namespace Modules\Production\Providers;

use App\Modules\ModuleServiceProvider;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Notifications\NotificationKind;
use Modules\Core\Application\Notifications\NotificationKinds;
use Modules\Core\Filament\Records\RelatedLists;
use Modules\Orders\Models\Order;
use Modules\Production\Application\AccidentReports\AccidentAlert;
use Modules\Production\Application\Legacy\LegacyAccidentReportImport;
use Modules\Production\Application\Legacy\LegacyBagScheduleImport;
use Modules\Production\Application\Legacy\LegacyDailyReportImport;
use Modules\Production\Application\Legacy\LegacyNewDailyReportImport;
use Modules\Production\Application\ProductionObserver;
use Modules\Production\Filament\Resources\BagSchedules\Board\BagScheduleBoard;
use Modules\Production\Filament\Resources\DailyReports\DailyReportRows;
use Modules\Production\Filament\Resources\DailyReports\QuickEntry\QuickDailyReport;
use Modules\Production\Models\DailyReport;

final class ProductionServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'production';

    protected function registerModule(): void
    {
        // 移行 (legacy:import): 12 製袋予定管理, 08 日報入力 (+ 08N 【新】日報入力), 14 事故報告管理
        $this->app->tag([
            LegacyBagScheduleImport::class,
            LegacyDailyReportImport::class,
            LegacyNewDailyReportImport::class,
            LegacyAccidentReportImport::class,
        ], LegacyImporter::TAG);
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

        // 新規 of 08. 日報入力: the quick-entry panel over the list and the dashboard
        Livewire::component('production.quick-daily-report', QuickDailyReport::class);

        // 12. 製袋予定管理: the 製袋予定表 board (view 製袋予定表 of the list)
        Livewire::component('production.bag-schedule-board', BagScheduleBoard::class);

        // 日報一覧 on a 04. 受注入力 record
        $this->app->make(RelatedLists::class)->provide('orders.daily_reports', fn (Model $order): array => $order instanceof Order
            ? DailyReportRows::ofOrder($order)
            : ['columns' => [], 'numeric' => [], 'rows' => [], 'urls' => []]);
    }
}
