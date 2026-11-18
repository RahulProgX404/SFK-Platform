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
use Modules\Core\Application\Records\RecordCards;
use Modules\Core\Filament\Records\RelatedLists;
use Modules\Orders\Models\Order;
use Modules\Production\Application\AccidentReports\AccidentAlert;
use Modules\Production\Application\Legacy\LegacyAccidentReportImport;
use Modules\Production\Application\Legacy\LegacyBagScheduleImport;
use Modules\Production\Application\Legacy\LegacyDailyReportImport;
use Modules\Production\Application\Legacy\LegacyNewDailyReportImport;
use Modules\Production\Application\ProductionObserver;
use Modules\Production\Filament\Resources\AccidentReports\QuickEntry\QuickAccidentReport;
use Modules\Production\Filament\Resources\BagSchedules\Board\BagScheduleBoard;
use Modules\Production\Filament\Resources\DailyReports\DailyReportRows;
use Modules\Production\Filament\Resources\DailyReports\QuickEntry\QuickDailyReport;
use Modules\Production\Models\BagSchedule;
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
        // 社内メッセージ: records shared in a message are drawn as cards (Core RecordCards, the viewer's rights apply)
        RecordCards::register('daily-report', fn (DailyReport $report): array => [
            ['field' => 'worked_on', 'label' => '作業日', 'value' => $report->worked_on->format('Y/m/d')],
            ['field' => 'user_id', 'label' => '作業者', 'value' => $report->worker->name],
            ['field' => 'order_id', 'label' => '注番', 'value' => $report->order?->order_number],
            ['field' => 'machine_id', 'label' => '機械', 'value' => $report->machine?->name],
        ]);
        RecordCards::register('bag-schedule', fn (BagSchedule $schedule): array => [
            ['field' => 'scheduled_on', 'label' => '予定日', 'value' => $schedule->scheduled_on?->format('Y/m/d')],
            ['field' => 'order_id', 'label' => '注番', 'value' => $schedule->orderNumber()],
            ['field' => 'product_name', 'label' => '品名', 'value' => $schedule->productName()],
            ['field' => 'machine_id', 'label' => '機械', 'value' => $schedule->machine?->name],
        ]);

        // the jobs' production totals and the box stock follow the daily reports
        DailyReport::observe(ProductionObserver::class);

        // a new 14. 事故報告管理 record → administrators and 営業関係者 (QuickAccidentReport)
        $this->app->make(NotificationKinds::class)->register(new NotificationKind(
            key: AccidentAlert::NOTIFICATION,
            label: '事故報告',
            appCode: 'accident-report',
            icon: Heroicon::OutlinedExclamationTriangle,
            color: 'danger',
        ));

        // 新規 of 08. 日報入力: the quick-entry panel over the list and the dashboard
        Livewire::component('production.quick-daily-report', QuickDailyReport::class);

        // 新規 of 14. 事故報告管理: its quick entry over the list (Core QuickEntry)
        Livewire::component('production.quick-accident-report', QuickAccidentReport::class);

        // 12. 製袋予定管理: the 製袋予定表 board (view 製袋予定表 of the list)
        Livewire::component('production.bag-schedule-board', BagScheduleBoard::class);

        // 日報一覧 on a 04. 受注入力 record
        $this->app->make(RelatedLists::class)->provide('orders.daily_reports', fn (Model $order): array => $order instanceof Order
            ? DailyReportRows::ofOrder($order)
            : ['columns' => [], 'numeric' => [], 'rows' => [], 'urls' => []]);
    }
}
