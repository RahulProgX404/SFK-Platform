<?php

/*
 * The screens of 08 日報入力, 12 製袋予定管理 and 14 事故報告管理 (SYSTEM_BRAIN APP_08 / APP_12 / APP_14): the apps
 * register with the switches of their legacy templates; a daily report (one per task) costs its task with the overtime
 * (BR-PR-01..04), requires the task inputs (BR-PR-05), moves the job's status (BR-PR-06), totals and box stock (BR-PR-07) on every save and delete without
 * counting twice, and hides the costs from employees; the schedule is drawn as Gantt charts (カスタマイズ形式 views); an
 * incident totals its damage.
 */

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Application\Views\ListViewService;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Domain\Views\ListViewType;
use Modules\Core\Models\AppListView;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;
use Modules\MasterData\Domain\Consumables\StockMovementReason;
use Modules\MasterData\Models\Consumable;
use Modules\MasterData\Models\Machine;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Filament\Resources\Orders\Pages\ViewOrder;
use Modules\Orders\Models\Order;
use Modules\Production\Filament\Resources\AccidentReports\AccidentReportResource;
use Modules\Production\Filament\Resources\AccidentReports\Pages\CreateAccidentReport;
use Modules\Production\Filament\Resources\AccidentReports\Pages\ListAccidentReports;
use Modules\Production\Filament\Resources\AccidentReports\Pages\ViewAccidentReport;
use Modules\Production\Filament\Resources\BagSchedules\BagScheduleResource;
use Modules\Production\Filament\Resources\BagSchedules\Pages\BagScheduleListViewSettings;
use Modules\Production\Filament\Resources\BagSchedules\Pages\CreateBagSchedule;
use Modules\Production\Filament\Resources\BagSchedules\Pages\ListBagSchedules;
use Modules\Production\Filament\Resources\DailyReports\DailyReportResource;
use Modules\Production\Filament\Resources\DailyReports\Pages\CreateDailyReport;
use Modules\Production\Filament\Resources\DailyReports\Pages\EditDailyReport;
use Modules\Production\Filament\Resources\DailyReports\Pages\ListDailyReports;
use Modules\Production\Filament\Resources\DailyReports\Pages\ViewDailyReport;
use Modules\Production\Models\AccidentReport;
use Modules\Production\Models\BagSchedule;
use Modules\Production\Models\DailyReport;

/** The three production apps, published in a space of 白石封筒 as アプリ設定 would add them (registry sync). */
function publishProductionApps(): void
{
    $resources = [DailyReportResource::class, BagScheduleResource::class, AccidentReportResource::class];
    $codes = [];
    $defaults = [];
    foreach ($resources as $resource) {
        $codes[$resource::appCode()] = $resource::appName();
        $defaults[$resource::appCode()] = $resource::appSettingDefaults();
    }

    app(AppLifecycle::class)->syncWithCode($codes, $defaults);
    $space = Space::query()->firstOrCreate(['panel_id' => 'erp', 'name' => '製造スペース']);
    BusinessApp::query()->whereIn('code', array_keys($codes))->get()
        ->each(function (BusinessApp $app) use ($space): void {
            $app->forceFill(['status' => AppStatus::Published])->save();
            $app->spaces()->syncWithoutDetaching([$space->id]);
        });
}

function productionView(string $code, string $name): string
{
    return (string) AppListView::query()->where('business_app_id', BusinessApp::query()->where('code', $code)->value('id'))->where('name', $name)->value('id');
}

function productionJob(string $product = '長3封筒'): Order
{
    $customer = Partner::query()->firstOrCreate(['name' => '株式会社得意先'], ['short_name' => '得意先', 'is_customer' => true]);

    return Order::query()->create(['customer_id' => $customer->id, 'product_name' => $product, 'quantity' => '10000', 'sales_amount' => 100000,
        'status' => '断裁作業中']);
}

/** A task of 08. 日報入力 as entered on the form. */
function taskForm(array $values = []): array
{
    return [
        'worked_on' => '2026-10-20', 'user_id' => null, 'order_id' => null, 'machine_id' => null, 'cardboard_id' => null, 'cardboard_used' => null,
        'partition_id' => null, 'partition_used' => null, 'glue' => null, 'glue_cost' => null, 'started_at' => '08:00', 'ended_at' => '17:00', 'break_time' => '01:00',
        'changeover_minutes' => null, 'changeover_count' => null, 'produced' => '3000', 'crew' => '2', 'day_type' => '平日', 'overtime_started_at' => '00:00',
        'overtime_ended_at' => '00:00', 'overtime_break' => '00:00', 'quality_checks' => ['前', '中', '後'], 'gloves' => ['着用'], 'damaged_before' => '0',
        'damaged_after' => '0', 'update_status' => [], 'new_status' => null, 'difficulty' => null, 'notes' => null, ...$values,
    ];
}

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->travelTo(CarbonImmutable::parse('2026-10-21 10:00'));
    publishProductionApps();
    $this->admin = User::factory()->superAdmin()->create(['name' => '管理者']);
    $this->worker = User::factory()->create(['name' => '製造 太郎', 'work_rate' => 1200, 'overtime_rate' => 1500, 'worker_code' => 'W01']);
    $this->machine = Machine::query()->create(['name' => '製袋機1号', 'code' => 'M01', 'machine_rate' => '3000', 'processing_rate' => '0.5']);
    $this->box = Consumable::query()->create(['name' => '段ボール A式', 'kind' => '段ボール', 'unit_price' => '50']);
    $this->box->stockMovements()->create(['moved_on' => '2026-10-01', 'reason' => StockMovementReason::Receipt, 'quantity' => '100']);
});

it('registers the three apps from the code, with 一括削除 off as in their legacy templates', function () {
    expect((new AppDiscovery([base_path('modules')]))->definitions())
        ->toHaveKey('daily-report', '08. 日報入力')
        ->toHaveKey('bag-schedule', '12. 製袋予定管理')
        ->toHaveKey('accident-report', '14. 事故報告管理');

    expect(BusinessApp::query()->whereIn('code', ['daily-report', 'bag-schedule', 'accident-report'])->pluck('bulk_delete_enabled')->unique()->all())->toBe([false]);
});

it('enters a daily report per task: costs it with the overtime and keeps the job and the box stock in line', function () {
    $job = productionJob();
    $other = productionJob('角2封筒');
    $this->actingAs($this->admin);

    Livewire::test(CreateDailyReport::class)
        ->fillForm(taskForm(['user_id' => $this->worker->id, 'order_id' => $job->id, 'machine_id' => $this->machine->id, 'cardboard_id' => $this->box->id,
            'cardboard_used' => '10', 'glue_cost' => '100', 'overtime_started_at' => '17:30', 'overtime_ended_at' => '19:00', 'update_status' => ['する'],
            'new_status' => '製袋作業中']))
        ->call('create')
        ->assertHasNoFormErrors();
    Livewire::test(CreateDailyReport::class)
        ->fillForm(taskForm(['user_id' => $this->worker->id, 'order_id' => $job->id, 'machine_id' => $this->machine->id, 'started_at' => '13:00', 'ended_at' => '14:30',
            'break_time' => '00:00', 'produced' => '500']))
        ->call('create')
        ->assertHasNoFormErrors();

    [$first, $second] = DailyReport::query()->orderBy('id')->get()->all();
    // 8 h + 残業 1.5 h: 1,200 × 8 + 1.5 × 1,500 + 3,000 × 8 + 糊代 100 + 段ボール 50 = 36,000;
    // 製造売上 (08) = 8 × 3,000 + 1,200 × 出来上 3,000 = 3,624,000
    expect($first->work_rate)->toBe(1200)
        ->and($first->overtime_rate)->toBe(1500)
        ->and($first->work_hours)->toBe('8.00')
        ->and($first->overtime_hours)->toBe('1.50')
        ->and($first->internal_cost)->toBe(36000)
        ->and($first->manufacturing_sales)->toBe(3624000)
        ->and($first->machine_rate)->toBe('3000.0000')
        ->and($first->day_type)->toBe('平日')
        ->and($second->overtime_hours)->toBe('0.00')
        ->and($second->internal_cost)->toBe(6300) // 1.5 h × 4,200
        ->and($second->manufacturing_sales)->toBe(604500);

    $job->refresh();
    expect($job->getAttribute('manufactured_quantity'))->toBe('3500.0000')
        ->and($job->internal_cost)->toBe(42300)
        ->and($job->manufacturing_sales)->toBe(4228500)
        ->and($job->status)->toBe('製袋作業中') // ステータスの更新 (BR-PR-06)
        ->and($this->box->stock())->toBe('90.0000'); // 使用数 10 (BR-PR-07)

    Livewire::test(ViewDailyReport::class, ['record' => $first->getRouteKey()])
        ->assertSeeInOrder(['作業日', '2026-10-20', '作業者', '製造 太郎', '製袋機1号', '平日', '注番情報', $job->order_number, '長3封筒', '作業時間・出来高',
            '08:00', '17:00', '01:00', '3,000', '1,500', '17:30', '19:00', '使用資材', '段ボール A式', '90', '10', '品質・ステータス', '前、中、後', '着用',
            '8時間', '¥36,000', '1.5時間']);

    // saved again with the task moved to another job: nothing is counted twice, the old job is recalculated
    Livewire::test(EditDailyReport::class, ['record' => $first->getRouteKey()])
        ->fillForm(['order_id' => $other->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->box->stock())->toBe('90.0000')
        ->and($job->fresh()->getAttribute('manufactured_quantity'))->toBe('500.0000')
        ->and($job->fresh()->internal_cost)->toBe(6300)
        ->and($other->fresh()->internal_cost)->toBe(36000);

    // deleting the report gives the boxes back and empties the job's totals
    $first->fresh()?->delete();
    expect($this->box->stock())->toBe('100.0000')->and($other->fresh()->manufacturing_sales)->toBe(0);
});

it('requires the inputs of a task (BR-PR-05)', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateDailyReport::class)
        ->assertSchemaStateSet(['started_at' => '00:00', 'ended_at' => '00:00', 'break_time' => '01:00', 'overtime_started_at' => '00:00', 'day_type' => '平日'])
        ->fillForm(taskForm(['user_id' => null, 'quality_checks' => [], 'crew' => '0', 'produced' => null, 'day_type' => null]))
        ->call('create')
        ->assertHasFormErrors(['user_id' => 'required', 'machine_id' => 'required', 'crew' => 'min', 'produced' => 'required', 'quality_checks' => 'required',
            'day_type' => 'required']);

    expect(DailyReport::query()->count())->toBe(0);
});

it('shows the daily reports in 日報一覧 and on the job, without the costs for employees', function () {
    $job = productionJob();
    $report = DailyReport::query()->create(['worked_on' => '2026-10-20', 'user_id' => $this->worker->id, 'order_id' => $job->id, 'machine_id' => $this->machine->id,
        'cardboard_id' => $this->box->id, 'cardboard_used' => '5', 'started_at' => '08:00', 'ended_at' => '12:00', 'break_time' => '00:00', 'produced' => '800',
        'crew' => '1', 'quality_checks' => ['前'], 'damaged_before' => '0', 'damaged_after' => '0']);
    $this->actingAs($this->admin);

    Livewire::test(ListDailyReports::class)
        ->assertSee('日報一覧')
        ->assertCanSeeTableRecords([$report])
        ->assertSeeInOrder(['2026-10-20', '製造 太郎', $job->order_number, '長3封筒', '製袋機1号', '段ボール A式', '5', '08:00', '12:00', '800'])
        ->set('listView', 'all')
        ->assertSee(['作業単価', '社内原価', '在庫数更新値', '残業単価'])
        ->sortTable('labor_cost')
        ->sortTable('per_person')
        ->sortTable('cardboard_stock')
        ->sortTable('order_id')
        ->sortTable('user_id');

    // インライン編集: a changed time costs the task again
    Livewire::test(ListDailyReports::class)
        ->callTableAction('inlineEdit', $report)
        ->set('inlineData.ended_at', '13:00')
        ->set('inlineData.gloves', true)
        ->callTableAction('inlineSave', $report)
        ->assertHasNoErrors();
    expect($report->fresh()?->work_hours)->toBe('5.00')->and($report->fresh()?->internal_cost)->toBe(21050)->and($report->fresh()?->gloves)->toBeTrue();

    // レコードを再利用する: the same task for today
    Livewire::withQueryParams(['duplicate' => $report->id])->test(CreateDailyReport::class)
        ->assertSchemaStateSet(['worked_on' => '2026-10-21', 'user_id' => $this->worker->id, 'machine_id' => $this->machine->id, 'produced' => '800.0000'])
        ->call('create')
        ->assertHasNoFormErrors();
    expect(DailyReport::query()->latest('id')->first()?->internal_cost)->toBe(21050);
    DailyReport::query()->latest('id')->first()?->delete();

    Livewire::test(ViewOrder::class, ['record' => $job->getRouteKey()])
        ->assertSeeInOrder(['日報一覧', '2026-10-20', '製造 太郎', '製袋機1号', '08:00', '13:00', '800', '5', '¥21,050']);

    $app = BusinessApp::query()->where('code', 'daily-report')->sole();
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true, AppAbility::Create->column() => true]);
    $employee = grantDashboards($this->worker, 'erp');
    $app->spaces->first()?->users()->attach($employee);
    $this->actingAs($employee);

    $this->get(DailyReportResource::getUrl('view', ['record' => $report]))->assertOk()->assertSee(['製袋機1号', '作業時間'])->assertDontSee('社内原価')
        ->assertDontSee('¥21,050');
    Livewire::test(ListDailyReports::class)->set('listView', 'all')->assertSee('作業時間')->assertDontSee('作業単価');
});

it('draws the schedule as Gantt charts by machine and lists it for the print', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 10:00')); // the lists open on today
    $job = productionJob();
    $second = Machine::query()->create(['name' => '窓貼り機', 'code' => 'M02']);
    $this->actingAs($this->admin);

    Livewire::test(CreateBagSchedule::class)
        ->fillForm(['starts_at' => '2026-10-21 09:00', 'ends_at' => '2026-10-21 15:00', 'machine_id' => $this->machine->id, 'usage_status' => '使用中',
            'entry_mode' => '受注から注番を取得', 'order_id' => $job->id, 'copies' => '5000', 'notes' => '急ぎ'])
        ->call('create')
        ->assertHasNoFormErrors();
    $free = BagSchedule::query()->create(['starts_at' => '2026-10-23 08:00', 'ends_at' => '2026-10-23 12:00', 'machine_id' => $second->id, 'usage_status' => '完了',
        'entry_mode' => 'フリー入力', 'order_id' => $job->id, 'free_order_number' => 'F-1', 'free_product_name' => '試作封筒']);
    $booked = BagSchedule::query()->where('usage_status', '使用中')->sole();
    expect($free->order_id)->toBeNull(); // only the inputs of the chosen 入力方法 are kept

    Livewire::test(ListBagSchedules::class)
        ->assertSee(['2026年10月21日', '製袋機1号', '窓貼り機', '注番：'.$job->order_number, '品名：長3封筒', '部数：5000', '備考：急ぎ', '使用中', '使用前', '使用完了'])
        ->assertDontSee('試作封筒') // another day
        ->set('listView', productionView('bag-schedule', '週次ガントチャート'))
        ->assertSee(['2026年10月18日 ～ 10月24日', '10/21(水)', '品名：試作封筒'])
        ->call('shiftGantt', 1)
        ->assertSee('2026年10月25日 ～ 10月31日')
        ->assertDontSee('品名：長3封筒')
        ->set('listView', productionView('bag-schedule', '帳票出力用(テスト)'))
        ->assertCanSeeTableRecords([$booked])
        ->assertCanNotSeeTableRecords([$free]);

    $views = AppListView::query()->where('business_app_id', BusinessApp::query()->where('code', 'bag-schedule')->value('id'))->orderBy('sort_order')->get();
    expect($views->pluck('name')->all())->toBe(['日次ガントチャート', '週次ガントチャート', '月次ガントチャート', '年次ガントチャート', '帳票出力用(テスト)'])
        ->and($views->first()?->type)->toBe(ListViewType::Custom);

    // a カスタマイズ形式 view keeps its screen when its name or conditions change
    $gantt = $views->first();
    app(ListViewService::class)->save(BusinessApp::query()->where('code', 'bag-schedule')->sole(), $gantt, ['name' => '日次ガントチャート（使用中）', 'type' => 'custom',
        'filter' => ['match' => 'and', 'conditions' => [], 'sorts' => []]], Livewire::test(ListBagSchedules::class)->instance()->listViewSchema(), $this->admin->id);
    expect($gantt?->fresh()?->type)->toBe(ListViewType::Custom)->and($gantt?->fresh()?->custom_view)->toBe('gantt-day');
    Livewire::withQueryParams(['edit' => (string) $gantt?->id])->test(BagScheduleListViewSettings::class)->assertSee('カスタマイズ形式');
});

it('reports an incident with its damage', function () {
    $job = productionJob();
    $sales = User::factory()->create(['name' => '営業 花子']);
    $this->actingAs($this->admin);
    $undoFake = Repeater::fake();

    Livewire::test(CreateAccidentReport::class)
        ->fillForm(['occurred_on' => '2026-10-20', 'reporter_id' => $this->worker->id, 'order_id' => $job->id, 'quantity' => '200', 'machine_id' => $this->machine->id,
            'maker1_id' => $this->worker->id, 'sales_users' => [$sales->id], 'description' => '糊がはみ出した', 'cause' => '糊量の設定ミス',
            'damages' => [
                ['item' => '紙', 'sheets' => '200', 'cost' => '12.5', 'quantity' => '200'],
                ['item' => '印刷', 'sheets' => null, 'cost' => '3000', 'quantity' => '1'],
            ]])
        ->call('create')
        ->assertHasNoFormErrors();
    $undoFake();

    $report = AccidentReport::query()->sole();
    expect($report->total_damage)->toBe(5500)->and($report->salesUsers->pluck('name')->all())->toBe(['営業 花子']);

    Livewire::test(ViewAccidentReport::class, ['record' => $report->getRouteKey()])
        ->assertSeeInOrder(['事故発生日', '2026-10-20', '報告者氏名', '製造 太郎', '注番', $job->order_number, '得意先名', '株式会社得意先', '品名', '長3封筒',
            '担当機械', '製袋機1号', '営業関係者', '営業 花子', '糊がはみ出した', '損害', '紙', '¥2,500', '印刷', '¥3,000', '合計損害額', '¥5,500']);

    Livewire::test(ListAccidentReports::class)
        ->assertSee('事故報告一覧')
        ->assertCanSeeTableRecords([$report])
        ->sortTable('customer')
        ->sortTable('total_damage');
});
