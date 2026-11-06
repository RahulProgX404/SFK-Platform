<?php

/*
 * The screens of 08 日報入力, 12 製袋予定管理 and 14 事故報告管理 (SYSTEM_BRAIN APP_08 / APP_12 / APP_14): the apps
 * register with the switches of their legacy templates; a daily report (one per task, entered in the quick-entry panel —
 * no create page) costs its task, a 残業 task as overtime (BR-PR-01..04), shows employees their own reports only, requires the task inputs (BR-PR-05), moves the job's status (BR-PR-06), totals and box stock (BR-PR-07) on every save and delete without
 * counting twice, and hides the costs from employees; the schedule is drawn as Gantt charts (カスタマイズ形式 views); an
 * incident totals its damage.
 */

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Application\Calendar\CompanyCalendar;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Domain\Calendar\CalendarDayType;
use Modules\Core\Domain\Views\ListViewType;
use Modules\Core\Filament\AppResource;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\AppSettingsResource;
use Modules\Core\Filament\Portal\PortalPage;
use Modules\Core\Models\AppListView;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\RecordVersion;
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
use Modules\Production\Filament\Resources\BagSchedules\Board\BagScheduleBoard;
use Modules\Production\Filament\Resources\BagSchedules\Pages\EditBagSchedule;
use Modules\Production\Filament\Resources\BagSchedules\Pages\ListBagSchedules;
use Modules\Production\Filament\Resources\BagSchedules\Pages\ViewBagSchedule;
use Modules\Production\Filament\Resources\DailyReports\DailyReportLayout;
use Modules\Production\Filament\Resources\DailyReports\DailyReportResource;
use Modules\Production\Filament\Resources\DailyReports\Pages\EditDailyReport;
use Modules\Production\Filament\Resources\DailyReports\Pages\ListDailyReports;
use Modules\Production\Filament\Resources\DailyReports\Pages\ViewDailyReport;
use Modules\Production\Filament\Resources\DailyReports\QuickEntry\QuickDailyReport;
use Modules\Production\Http\Controllers\ProductionScheduleScript;
use Modules\Production\Models\AccidentReport;
use Modules\Production\Models\BagSchedule;
use Modules\Production\Models\DailyReport;
use Modules\Sales\Models\Sale;

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

/** Whether the texts appear in the HTML in this order. */
function shownInOrder(string $html, array $texts): bool
{
    $at = 0;
    foreach ($texts as $text) {
        $found = mb_strpos($html, e($text), $at);
        if ($found === false) {
            return false;
        }
        $at = $found + mb_strlen(e($text));
    }

    return true;
}

/** A task entered in the quick-entry panel of 08. 日報入力 (QuickDailyReport) and saved with この内容で登録 / 登録. */
function quickTask(array $values): Testable
{
    $panel = Livewire::test(QuickDailyReport::class, ['open' => true]);
    foreach ([
        'worked_on' => '2026-10-20', 'started_at' => '08:00', 'ended_at' => '17:00', 'break_time' => '01:00', 'produced' => '3000', 'crew' => '2',
        'quality_checks' => ['前', '中', '後'], ...$values,
    ] as $field => $value) {
        $panel->set('data.'.$field, $value);
    }

    return $panel->call('register');
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

it('enters a daily report per task in the quick-entry panel: costs it, 残業 as overtime, and keeps the job and the box stock in line', function () {
    $job = productionJob();
    $other = productionJob('角2封筒');
    $this->actingAs($this->admin);

    // step by step: 注番 → 機械名 → 開始 / 終了 / 休憩 → 日種別・勤務種別 → 出来上 → 作業人数 → 回数 → 品質チェック → ケガ → 確認
    Livewire::test(QuickDailyReport::class)
        ->assertSet('open', false)
        ->call('start')
        ->assertSet('open', true)
        ->assertSet('screen', 'order')
        ->assertSee([$job->order_number, '長3封筒'])
        ->set('orderSearch', '角2')
        ->assertDontSee('長3封筒')
        ->set('orderSearch', '')
        ->call('submit')
        ->assertHasErrors(['data.order_id' => 'required'])
        ->assertSet('screen', 'order')
        ->call('choose', 'order_id', (string) $job->id)
        ->assertSet('screen', 'machine')
        ->call('submit')
        ->assertHasErrors(['data.machine_id' => 'required'])
        ->call('choose', 'machine_id', (string) $this->machine->id)
        ->assertSet('screen', 'start')
        ->assertSet('data.started_at', '09:00')
        ->call('choose', 'started_at', '08:00')
        ->assertSet('screen', 'end')
        ->assertSet('data.ended_at', '17:00')
        ->call('submit')
        ->assertSet('data.break_time', '00:00')
        ->call('choose', 'break_time', '01:00')
        ->assertSet('screen', 'types')
        ->assertSet('data.day_type', '平日')
        ->assertSet('data.work_type', '通常')
        ->call('submit')
        ->assertSet('screen', 'produced')
        ->set('data.produced', '3000')
        ->call('submit')
        ->assertSet('data.crew', '1')
        ->call('choose', 'crew', '2')
        ->assertSet('screen', 'count')
        ->call('submit')
        ->call('submit')
        ->assertHasErrors(['data.quality_checks' => 'required'])
        ->call('checkAllQuality')
        ->call('submit')
        ->assertSet('screen', 'damage')
        ->assertSet('data.damaged_before', '0')
        ->call('submit')
        ->assertSet('screen', 'review')
        ->tap(fn (Testable $panel) => expect(shownInOrder($panel->html(), ['注番', $job->order_number, '機械名', '製袋機1号', '08:00', '17:00', '01:00', '平日・通常',
            '3,000枚', '2人', '前、中、後', '0枚 / 0枚', '作業時間', '8時間', '作業日', '2026-10-21', '作業者', '管理者', '追加情報を入力', 'この内容で登録']))->toBeTrue())
        ->call('goTo', 'crew')
        ->assertSet('screen', 'crew')
        ->call('choose', 'crew', '2')
        ->call('goTo', 'review')
        // 追加情報を入力: the optional fields, then 登録
        ->call('showDetails')
        ->assertSet('screen', 'details')
        ->set('data.worked_on', '2026-10-20')
        ->set('data.user_id', $this->worker->id)
        ->set('data.cardboard_id', $this->box->id)
        ->set('data.cardboard_used', '10')
        ->set('data.glue_cost', '100')
        ->set('data.gloves', true)
        ->set('data.update_status', true)
        ->set('data.new_status', '製袋作業中')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(DailyReportResource::getUrl('index', ['created' => DailyReport::query()->max('id')]));

    // 勤務種別 残業: the task is overtime from 開始 to 終了
    quickTask(['user_id' => $this->worker->id, 'order_id' => $job->id, 'machine_id' => $this->machine->id, 'started_at' => '17:30', 'ended_at' => '19:00',
        'break_time' => '00:00', 'produced' => '500', 'work_type' => '残業'])->assertHasNoErrors();

    [$first, $second] = DailyReport::query()->orderBy('id')->get()->all();
    // 8 h: 1,200 × 8 + 3,000 × 8 + 糊代 100 + 段ボール 50 = 33,750; 製造売上 (08) = 8 × 3,000 + 1,200 × 出来上 3,000 = 3,624,000
    expect($first->work_rate)->toBe(1200)
        ->and($first->overtime_rate)->toBe(1500)
        ->and($first->work_hours)->toBe('8.00')
        ->and($first->overtime_hours)->toBeNull()
        ->and($first->internal_cost)->toBe(33750)
        ->and($first->manufacturing_sales)->toBe(3624000)
        ->and($first->machine_rate)->toBe('3000.0000')
        ->and($first->day_type)->toBe('平日')
        ->and($first->work_type)->toBe('通常')
        ->and($first->gloves)->toBeTrue()
        ->and($first->created_by)->toBe($this->admin->id)
        ->and(RecordVersion::query()->where('record_type', $first->getMorphClass())->where('record_id', $first->id)->count())->toBe(1)
        // 残業 1.5 h: 1.5 × 残業単価 1,500 + 機械 3,000 × 1.5 = 6,750; 製造売上 = 1.5 × 3,000 + 1,200 × 500 = 604,500
        ->and($second->work_type)->toBe('残業')
        ->and($second->work_hours)->toBe('0.00')
        ->and($second->overtime_hours)->toBe('1.50')
        ->and($second->internal_cost)->toBe(6750)
        ->and($second->manufacturing_sales)->toBe(604500);

    $job->refresh();
    expect($job->getAttribute('manufactured_quantity'))->toBe('3500.0000')
        ->and($job->internal_cost)->toBe(40500)
        ->and($job->manufacturing_sales)->toBe(4228500)
        ->and($job->status)->toBe('製袋作業中') // ステータスの更新 (BR-PR-06)
        ->and($this->box->stock())->toBe('90.0000'); // 使用数 10 (BR-PR-07)

    Livewire::test(ViewDailyReport::class, ['record' => $first->getRouteKey()])
        ->assertSeeInOrder(['作業日', '2026-10-20', '作業者', '製造 太郎', '製袋機1号', '平日', '通常', '注番情報', $job->order_number, '長3封筒', '作業時間・出来高',
            '08:00', '17:00', '01:00', '3,000', '1,500', '使用資材', '段ボール A式', '90', '10', '品質・ステータス', '前、中、後', '着用', '8時間', '¥33,750'])
        ->assertDontSee(['残業開始時間', '残業終了時間', '休憩時間(残業)']);
    Livewire::test(ViewDailyReport::class, ['record' => $second->getRouteKey()])
        ->assertSeeInOrder(['残業', '17:30', '19:00', '0時間', '¥6,750', '1.5時間']);

    // the edit form: 勤務種別 switched to 通常 costs the times as normal work at once and on save
    Livewire::test(EditDailyReport::class, ['record' => $second->getRouteKey()])
        ->assertDontSee('残業開始時間')
        ->fillForm(['work_type' => '通常'])
        ->assertSee(['1.5時間', '¥6,300'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($second->fresh()?->internal_cost)->toBe(6300)->and($second->fresh()?->overtime_hours)->toBeNull(); // 1.5 h × 4,200

    // saved again with the task moved to another job: nothing is counted twice, the old job is recalculated
    Livewire::test(EditDailyReport::class, ['record' => $first->getRouteKey()])
        ->fillForm(['order_id' => $other->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->box->stock())->toBe('90.0000')
        ->and($job->fresh()->getAttribute('manufactured_quantity'))->toBe('500.0000')
        ->and($job->fresh()->internal_cost)->toBe(6300)
        ->and($other->fresh()->internal_cost)->toBe(33750);

    // deleting the report gives the boxes back and empties the job's totals
    $first->fresh()?->delete();
    expect($this->box->stock())->toBe('100.0000')->and($other->fresh()->manufacturing_sales)->toBe(0);
});

it('keeps the figures of the earlier reports entered with a separate overtime span', function () {
    // as imported: a 通常 task 08:00–17:00 with 残業 17:00–19:30 (the legacy 残業開始 / 残業終了 / 休憩時間(残業))
    $report = DailyReport::query()->create(['worked_on' => '2026-10-01', 'user_id' => $this->worker->id, 'order_id' => productionJob('長3封筒')->id, 'machine_id' => $this->machine->id, 'started_at' => '08:00',
        'ended_at' => '17:00', 'break_time' => '01:00', 'overtime_started_at' => '17:00', 'overtime_ended_at' => '19:30', 'overtime_break' => '00:00',
        'produced' => '100', 'crew' => '1', 'quality_checks' => ['前'], 'damaged_before' => '0', 'damaged_after' => '0']);
    // 1,200 × 8 + 2.5 × 1,500 + 3,000 × 8 = 37,350 (the machine on the 作業時間, as in the legacy formula)
    expect($report->work_type)->toBe('通常')->and($report->overtime_hours)->toBe('2.50')->and($report->internal_cost)->toBe(37350);

    $this->actingAs($this->admin);
    Livewire::test(EditDailyReport::class, ['record' => $report->getRouteKey()])
        ->assertSee(['2.5時間', '¥37,350'])
        ->fillForm(['notes' => '確認済'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($report->fresh()?->overtime_hours)->toBe('2.50')->and($report->fresh()?->internal_cost)->toBe(37350)
        ->and($report->fresh()?->overtime_ended_at)->toBe('19:30');
});

it('requires the inputs of a task (BR-PR-05) and shows the step of the first error', function () {
    $this->actingAs($this->admin);

    Livewire::test(QuickDailyReport::class, ['open' => true])
        ->assertSet('data.started_at', '09:00')
        ->assertSet('data.ended_at', '17:00')
        ->assertSet('data.break_time', '00:00')
        ->assertSet('data.day_type', '平日')
        ->assertSet('data.work_type', '通常')
        ->assertSet('data.crew', '1')
        ->assertSet('data.worked_on', '2026-10-21')
        ->assertSet('data.user_id', $this->admin->id)
        ->set('data.user_id', null)
        ->set('data.crew', '0')
        ->set('data.ended_at', '08:30')
        ->call('register')
        ->assertHasErrors(['data.order_id' => 'required', 'data.machine_id' => 'required', 'data.produced' => 'required', 'data.quality_checks' => 'required', 'data.crew' => 'min',
            'data.ended_at', 'data.user_id' => 'required'])
        ->assertSet('screen', 'order')
        ->tap(fn (Testable $panel) => expect($panel->errors()->first('data.ended_at'))->toBe('終了時間(24H)は開始時間(24H)より後の時刻を入力してください。'));

    expect(DailyReport::query()->count())->toBe(0);

    // the edit form takes no empty 注番 / 機械名 either
    $report = DailyReport::query()->create(['worked_on' => '2026-10-20', 'user_id' => $this->worker->id, 'order_id' => productionJob('長3封筒')->id,
        'machine_id' => $this->machine->id, 'started_at' => '08:00', 'ended_at' => '17:00', 'produced' => '100', 'crew' => '1', 'quality_checks' => ['前'],
        'damaged_before' => '0', 'damaged_after' => '0']);
    Livewire::test(EditDailyReport::class, ['record' => $report->getRouteKey()])
        ->fillForm(['order_id' => null, 'machine_id' => null])
        ->call('save')
        ->assertHasFormErrors(['order_id' => 'required', 'machine_id' => 'required']);
    expect($report->fresh()?->order_id)->not->toBeNull();
});

it('puts the machines of the job at the top of 機械名 once the 注番 is chosen, choosing none', function () {
    $job = productionJob();
    $cutter = Machine::query()->create(['name' => '断裁機', 'code' => 'M02']);
    $press = Machine::query()->create(['name' => 'エキセン(大)', 'code' => 'M03']);
    $bagger = Machine::query()->create(['name' => '3号機', 'code' => 'M04']);
    Machine::query()->create(['name' => 'テープ/HM', 'code' => 'M05']);
    $job->forceFill(['machine_id' => $bagger->id])->save(); // 受注入力 機械 (製袋予定)
    $task = ['worked_on' => '2026-10-20', 'user_id' => $this->worker->id, 'order_id' => $job->id, 'started_at' => '08:00', 'ended_at' => '12:00',
        'break_time' => '00:00', 'produced' => '800', 'crew' => '1', 'quality_checks' => ['前'], 'damaged_before' => '0', 'damaged_after' => '0'];
    DailyReport::query()->create([...$task, 'machine_id' => $press->id]);
    DailyReport::query()->create([...$task, 'machine_id' => $cutter->id]);
    DailyReport::query()->create([...$task, 'machine_id' => $press->id, 'order_id' => productionJob('角2封筒')->id]);
    $this->actingAs($this->admin);

    // latest task's machine first, then the earlier one, then the order's 機械, then the rest newest first
    expect(DailyReportLayout::machinesOfOrder($job->id))->toBe([$cutter->id, $press->id, $bagger->id])
        ->and(array_values(DailyReportLayout::machineOptions($job->id)))->toBe(['断裁機', 'エキセン(大)', '3号機', 'テープ/HM', '製袋機1号'])
        ->and(array_values(DailyReportLayout::machineOptions(null)))->toBe(['テープ/HM', '3号機', 'エキセン(大)', '断裁機', '製袋機1号']);

    Livewire::test(QuickDailyReport::class, ['open' => true])
        ->call('choose', 'order_id', (string) $job->id)
        ->assertSet('screen', 'machine')
        ->assertSet('data.machine_id', null)
        ->tap(fn (Testable $panel) => expect(shownInOrder($panel->html(), ['断裁機', 'エキセン(大)', '3号機', 'テープ/HM', '製袋機1号']))->toBeTrue());

    // the record form's drop-down in the same order
    $choices = fn (array $expected): Closure => fn (Select $field): bool => array_slice(array_values($field->getOptions()), 0, count($expected)) === $expected;
    Livewire::test(EditDailyReport::class, ['record' => DailyReport::query()->value('id')])
        ->assertFormFieldExists('machine_id', 'form', $choices(['断裁機', 'エキセン(大)', '3号機', 'テープ/HM']));
});

it('adds records only in the quick-entry panel: 新規 of the list, ?new=1, 再利用 and 新規作成 of the dashboard, then marks the new one', function () {
    $report = DailyReport::query()->create(['worked_on' => '2026-10-20', 'user_id' => $this->worker->id, 'order_id' => productionJob()->id, 'machine_id' => $this->machine->id, 'started_at' => '08:00',
        'ended_at' => '12:00', 'break_time' => '00:00', 'produced' => '800', 'crew' => '1', 'quality_checks' => ['前'], 'damaged_before' => '0', 'damaged_after' => '0']);
    $this->actingAs($this->admin);

    expect(DailyReportResource::hasPage('create'))->toBeFalse()
        ->and(DailyReportResource::newRecordUrl())->toBe(DailyReportResource::getUrl('index', ['new' => 1]))
        ->and(AppResource::createUrlForApp('daily-report', Filament::getPanel('erp')))->toBe(DailyReportResource::getUrl('index', ['new' => 1]));

    // 新規 opens the panel on the list (no page change)
    $list = Livewire::test(ListDailyReports::class)
        ->assertSeeLivewire(QuickDailyReport::class)
        ->assertSeeHtml(AppResource::QUICK_CREATE_EVENT);
    expect($list->instance()->quickCreate()['params'])->toBe(['app' => 'daily-report', 'open' => false, 'duplicate' => null]);
    expect(Livewire::withQueryParams(['new' => '1'])->test(ListDailyReports::class)->instance()->quickCreate()['params']['open'])->toBeTrue();

    // レコードを再利用する: the list with the copy in the panel, at the check
    Livewire::test(ViewDailyReport::class, ['record' => $report->getRouteKey()])
        ->assertActionHasUrl('duplicate', DailyReportResource::getUrl('index', ['new' => 1, 'duplicate' => $report->id]));
    Livewire::test(QuickDailyReport::class, ['open' => true, 'duplicate' => (string) $report->id])
        ->assertSet('screen', 'review')
        ->assertSet('data.worked_on', '2026-10-21')
        ->assertSet('data.user_id', $this->worker->id)
        ->assertSet('data.machine_id', $this->machine->id)
        ->assertSet('data.started_at', '08:00')
        ->assertSet('data.produced', '800')
        ->call('register')
        ->assertHasNoErrors();
    $copy = DailyReport::query()->latest('id')->firstOrFail();
    expect($copy->id)->not->toBe($report->id)->and($copy->worked_on->toDateString())->toBe('2026-10-21')->and($copy->work_hours)->toBe('4.00');

    // after the save the list marks the new record
    Livewire::withQueryParams(['created' => (string) $copy->id])->test(ListDailyReports::class)->assertSeeHtml('sfk-row-created');

    // 新規作成 of the dashboard opens the same panel over the dashboard
    $portal = Livewire::test(PortalPage::class)->assertSeeLivewire(QuickDailyReport::class);
    expect($portal->instance()->createItems()['日報入力'])->toBe(['url' => DailyReportResource::getUrl('index', ['new' => 1]), 'app' => 'daily-report'])
        ->and($portal->instance()->quickCreateComponents())->toBe(['daily-report' => QuickDailyReport::class]);
});

it('shows employees only their own reports, everyone\'s with 財務分析を表示', function () {
    $colleague = User::factory()->create(['name' => '製造 花子']);
    $task = ['worked_on' => '2026-10-20', 'machine_id' => $this->machine->id, 'started_at' => '08:00', 'ended_at' => '12:00', 'break_time' => '00:00',
        'produced' => '800', 'crew' => '1', 'quality_checks' => ['前'], 'damaged_before' => '0', 'damaged_after' => '0'];
    $mine = DailyReport::query()->create([...$task, 'user_id' => $this->worker->id]);
    $theirs = DailyReport::query()->create([...$task, 'user_id' => $colleague->id]);
    $app = BusinessApp::query()->where('code', 'daily-report')->sole();
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true, AppAbility::Create->column() => true]);
    $employee = grantDashboards($this->worker, 'erp');
    $app->spaces->first()?->users()->attach($employee);
    $this->actingAs($employee);

    Livewire::test(ListDailyReports::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs]);
    $this->get(DailyReportResource::getUrl('view', ['record' => $theirs]))->assertNotFound();
    // the quick-entry panel: 作業者 is the employee
    Livewire::test(QuickDailyReport::class, ['open' => true])->assertSet('data.user_id', $employee->id);

    $employee->forceFill(['show_financial_analysis' => true])->save();
    Livewire::test(ListDailyReports::class)->assertCanSeeTableRecords([$mine, $theirs]);
    $this->get(DailyReportResource::getUrl('view', ['record' => $theirs]))->assertOk();
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
        ->call('startInlineEdit', (string) $report->getKey())
        ->set('inlineData.ended_at', '13:00')
        ->set('inlineData.gloves', true)
        ->callTableAction('inlineSave', $report)
        ->assertHasNoErrors();
    expect($report->fresh()?->work_hours)->toBe('5.00')->and($report->fresh()?->internal_cost)->toBe(21050)->and($report->fresh()?->gloves)->toBeTrue();

    // レコードを再利用する: the same task for today
    Livewire::test(QuickDailyReport::class, ['open' => true, 'duplicate' => (string) $report->id])
        ->assertSet('data.worked_on', '2026-10-21')
        ->assertSet('data.user_id', $this->worker->id)
        ->assertSet('data.machine_id', $this->machine->id)
        ->assertSet('data.produced', '800')
        ->call('register')
        ->assertHasNoErrors();
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

/** The lines of the 製袋予定表 the tests use (machines of 03 by their 機械コード) and a sale of the job in 06. 売上入力. */
function boardLines(): array
{
    return [
        'K00001' => Machine::query()->create(['name' => '1号機', 'code' => 'K00001']),
        'K00002' => Machine::query()->create(['name' => '2号機', 'code' => 'K00002']),
        'K00006' => Machine::query()->create(['name' => '6号機(工場長要相談)', 'code' => 'K00006']),
    ];
}

function jobSale(Order $job, array $attributes = []): Sale
{
    $sale = Sale::query()->create(['customer_id' => $job->customer_id, 'sold_on' => '2026-10-20', 'delivery_due_on' => '2026-10-20', 'order_id' => $job->id,
        'product_name' => '長3封筒 クラフト', 'quantity' => '5000', 'amount' => 50000, ...$attributes]);
    $sale->assignees()->attach(User::factory()->create(['name' => '営業 花子']));

    return $sale;
}

it('shows 12. 製袋予定管理 as the 製袋予定表 board of production-schedule.js', function () {
    boardLines();
    $this->actingAs($this->admin);

    // the board is the app's one screen: no 一覧 tabs, no 絞り込む, no 新規 / create page; ⚙ in its place with アプリの権限 only
    $printView = productionView('bag-schedule', '帳票出力用(テスト)');
    Livewire::withQueryParams(['view' => $printView])->test(ListBagSchedules::class)
        ->assertSeeLivewire('production.bag-schedule-board')
        ->assertDontSeeHtml(['sfk-app-tabs', 'sfk-app-filter-button', 'sfk-app-list-bar', 'sfk-app-new'])
        ->assertSeeHtml('title="アプリの設定"')
        ->assertSee('アプリの権限')
        ->assertDontSee(['この一覧の設定を開く', 'その他の設定', '帳票出力']);
    expect(BagScheduleResource::hasPage('create'))->toBeFalse()
        ->and(BagScheduleResource::newRecordUrl())->toBeNull()
        ->and(Livewire::test(ListBagSchedules::class)->instance()->appChrome()['settings_menu'])
        ->toBe([['label' => 'アプリの権限', 'url' => AppSettingsResource::getUrl('edit', ['record' => BusinessApp::query()->where('code', 'bag-schedule')->sole()])]]);
    $this->get('/erp/bag-schedules/create')->assertNotFound();
    $this->get('/erp/daily-reports/create')->assertNotFound(); // (08 has no create page either)
    // the views are kept (the board first); the Gantt views are gone
    $views = AppListView::query()->where('business_app_id', BusinessApp::query()->where('code', 'bag-schedule')->value('id'))->orderBy('sort_order')->get();
    expect($views->pluck('name')->all())->toBe(['製袋予定表', '帳票出力用(テスト)'])
        ->and($views->first()?->type)->toBe(ListViewType::Custom)
        ->and($views->first()?->custom_view)->toBe('board');

    // the script of the board, its lines (machines of 03 by 機械コード, in the board's order) and the user's rights
    Livewire::test(BagScheduleBoard::class)
        ->assertViewHas('script', ProductionScheduleScript::url())
        ->assertSee(['window.ProductionSchedule.mount(', 'adapters.livewire($wire)'], false)
        ->assertViewHas('options', fn (array $options): bool => array_column($options['lines'], 'key') === ['K00001', 'K00002', 'K00006']
            && $options['lines'][2]['requiresManagerCheck'] === true && $options['lines'][2]['managerCheckMessage'] === '予定を入れる際は必ず工場長に相談して下さい'
            && ! isset($options['lines'][0]['machine'])
            && $options['permissions'] === ['canCreate' => true, 'canEdit' => true, 'canDelete' => true]
            && $options['urlParams'] === ['date' => 'date', 'view' => 'span']);

    // the script itself: the board as it was, its data from the server, no word of the legacy system
    $script = $this->get(ProductionScheduleScript::url())->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    expect($script->getContent())->toContain('製袋予定表', 'コピーしますか、移動しますか？', '06. 売上入力', 'scheduleItems', 'scheduleCopy')
        ->and(mb_strtolower((string) $script->getContent()))->not->toContain('kin'.'tone')
        // on the page itself: no masthead, backdrop or footer
        ->and($script->getContent())->not->toContain('ps-masthead', 'radial-gradient', '工場長確認済みデータをもとに自動生成', 'Bag & Envelope Production Schedule');
});

it('adds, edits, moves, copies and deletes schedules on the board, a 注番 filled from 06. 売上入力', function () {
    $lines = boardLines();
    $job = productionJob();
    jobSale($job);
    jobSale($job, ['kind' => 'マイナス処理', 'quantity' => '-100']); // a credit is not the job's latest sale
    $legacy = BagSchedule::query()->create(['starts_at' => '2026-10-23 08:00', 'ends_at' => '2026-10-23 12:00', 'machine_id' => $lines['K00002']->id,
        'usage_status' => '使用中', 'entry_mode' => 'フリー入力', 'free_product_name' => '試作封筒', 'copies' => '300', 'notes' => 'X6767　トッパン']);
    app(CompanyCalendar::class)->setDay(CarbonImmutable::parse('2026-10-12'), CalendarDayType::NationalHoliday);
    $this->actingAs($this->admin);
    $board = Livewire::test(BagScheduleBoard::class)->instance();

    // 注番: 担当者 / 品名 / 部数 of the latest sale (not the credit)
    expect($board->scheduleLookup($job->order_number))->toBe(['ok' => true, 'match' => ['personInitial' => '営業 花子', 'productName' => '長3封筒 クラフト', 'quantity' => '5,000']])
        ->and($board->scheduleLookup('sfk-0009999'))->toBe(['ok' => true, 'match' => null])
        ->and($board->scheduleLookup('123'))->toBe(['ok' => false, 'error' => '注番は数字のみで入力してください。']);

    // 予定を追加: with a 注番 the server takes 担当者 / 品名 / 部数 from 06 itself; without one they are typed
    $added = $board->scheduleCreate(['date' => '2026-10-21', 'line' => 'K00001', 'orderNumber' => $job->order_number, 'personInitial' => 'X', 'productName' => '別の品名',
        'quantity' => '1', 'notes' => ['ハート', ' 198×293 ', '']]);
    expect($added['ok'])->toBeTrue()
        ->and($added['item'])->toMatchArray(['date' => '2026-10-21', 'line' => 'K00001', 'personInitial' => '営業 花子', 'orderNumber' => $job->order_number,
            'productName' => '長3封筒 クラフト', 'quantity' => '5,000', 'notes' => ['ハート', '198×293'], 'highlightColor' => null]);
    $manual = $board->scheduleCreate(['date' => '2026-10-22', 'line' => 'K00002', 'orderNumber' => '', 'personInitial' => 'M', 'productName' => 'テスト封筒', 'quantity' => '1,000', 'notes' => []]);
    expect($manual['item'])->toMatchArray(['orderNumber' => '', 'personInitial' => 'M', 'productName' => 'テスト封筒', 'quantity' => '1,000']);

    $schedule = BagSchedule::query()->findOrFail((int) $added['item']['id']);
    expect($schedule->order_id)->toBe($job->id)
        ->and($schedule->entry_mode)->toBe('受注から注番を取得')
        ->and($schedule->copies)->toBe('5000.0000')
        ->and($schedule->notes)->toBe("ハート\n198×293")
        ->and($schedule->created_by)->toBe($this->admin->id)
        ->and(RecordVersion::query()->where('record_type', $schedule->getMorphClass())->where('record_id', $schedule->id)->count())->toBe(1)
        ->and(BagSchedule::query()->findOrFail((int) $manual['item']['id'])->entry_mode)->toBe('フリー入力');

    // the board's rules
    $item = ['date' => '2026-10-21', 'line' => 'K00001', 'orderNumber' => '', 'personInitial' => '', 'productName' => '封筒', 'quantity' => '10', 'notes' => []];
    foreach ([
        '注番が未入力の場合は品名を入力してください' => ['productName' => ''],
        '注番が未入力の場合は部数を入力してください' => ['quantity' => ''],
        '部数は数字で入力してください' => ['quantity' => '千枚'],
        'そのラインは存在しません。' => ['line' => 'K99999'],
        '日付の形式が正しくありません。' => ['date' => '2026-02-30'],
        '注番は数字のみ、7桁以内で入力してください' => ['orderNumber' => '12345678'],
        '注番「sfk-0009999」に一致するレコードが06. 売上入力に見つかりません。' => ['orderNumber' => 'sfk-0009999'],
        '備考は10件までです' => ['notes' => array_fill(0, 11, '注')],
        '備考は1件40文字以内で入力してください' => ['notes' => [str_repeat('長', 41)]],
        '担当者は40文字以内で入力してください' => ['personInitial' => str_repeat('担', 41)],
        '品名は120文字以内で入力してください' => ['productName' => str_repeat('品', 121)],
    ] as $message => $change) {
        expect($board->scheduleCreate([...$item, ...$change]))->toBe(['ok' => false, 'error' => $message]);
    }

    // the days of the board: the legacy schedule on the day of its 開始日時, with its free 品名 and its 備考 as one note
    $week = $board->scheduleItems('2026-10-19', '2026-10-25');
    expect(array_column($week['items'], 'productName'))->toBe(['長3封筒 クラフト', 'テスト封筒', '試作封筒'])
        ->and($week['items'][2])->toMatchArray(['id' => (string) $legacy->id, 'date' => '2026-10-23', 'line' => 'K00002', 'quantity' => '300', 'notes' => ['X6767　トッパン']])
        ->and($board->scheduleItems('2026-10-26', '2026-10-31')['items'])->toBe([])
        ->and($board->scheduleItems('2026-01-01', '2026-12-31'))->toBe(['ok' => false, 'error' => '日付の形式が正しくありません。'])
        // the 会社カレンダー's marked days (the board closes Sundays itself)
        ->and($board->scheduleCalendar('2026-10-01', '2026-10-31'))->toBe(['ok' => true, 'days' => ['2026-10-12' => 'NATIONAL_HOLIDAY']]);

    // 変更を保存, a move to another day and line, ハイライト
    $id = $added['item']['id'];
    expect($board->scheduleUpdate($id, ['date' => '2026-10-24', 'line' => 'K00002'])['item'])->toMatchArray(['date' => '2026-10-24', 'line' => 'K00002', 'productName' => '長3封筒 クラフト'])
        ->and($board->scheduleUpdate($id, ['highlightColor' => 'rose'])['item']['highlightColor'])->toBe('rose')
        ->and($board->scheduleUpdate($id, ['highlightColor' => 'pink']))->toBe(['ok' => false, 'error' => '無効な色です。'])
        ->and($board->scheduleUpdate((string) $legacy->id, ['date' => '2026-10-23', 'line' => 'K00002', 'orderNumber' => '', 'personInitial' => '', 'productName' => '試作封筒 改',
            'quantity' => '300', 'notes' => ['X6767']])['item'])->toMatchArray(['productName' => '試作封筒 改', 'notes' => ['X6767']])
        ->and($legacy->fresh()?->starts_at?->format('H:i'))->toBe('08:00'); // the legacy times stay

    // コピー: the same schedule, highlight and all, on another day and line
    $copy = $board->scheduleCopy($id, '2026-10-26', 'K00001');
    expect($copy['item'])->toMatchArray(['date' => '2026-10-26', 'line' => 'K00001', 'orderNumber' => $job->order_number, 'personInitial' => '営業 花子',
        'quantity' => '5,000', 'highlightColor' => 'rose', 'notes' => ['ハート', '198×293']])
        ->and($copy['item']['id'])->not->toBe($id);

    // 削除, then it is gone
    expect($board->scheduleRemove($copy['item']['id']))->toBe(['ok' => true])
        ->and($board->scheduleRemove($copy['item']['id']))->toBe(['ok' => false, 'error' => 'この予定は既に存在しません。'])
        ->and($board->scheduleUpdate('abc', ['date' => '2026-10-24']))->toBe(['ok' => false, 'error' => 'この予定は既に存在しません。']);
});

it('lets the board change only what the app allows the user', function () {
    $lines = boardLines();
    $schedule = BagSchedule::query()->create(['scheduled_on' => '2026-10-21', 'machine_id' => $lines['K00001']->id, 'product_name' => '封筒', 'copies' => '10']);
    $app = BusinessApp::query()->where('code', 'bag-schedule')->sole();
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
    $employee = grantDashboards($this->worker, 'erp');
    $app->spaces->first()?->users()->attach($employee);
    $this->actingAs($employee);

    $component = Livewire::test(BagScheduleBoard::class)
        ->assertViewHas('options', fn (array $options): bool => $options['permissions'] === ['canCreate' => false, 'canEdit' => false, 'canDelete' => false]);
    $board = $component->instance();
    $denied = ['ok' => false, 'error' => 'この操作を行う権限がありません。'];

    expect($board->scheduleItems('2026-10-19', '2026-10-25')['items'])->toHaveCount(1)
        ->and($board->scheduleCreate(['date' => '2026-10-21', 'line' => 'K00001', 'productName' => '封筒', 'quantity' => '1']))->toBe($denied)
        ->and($board->scheduleUpdate((string) $schedule->id, ['date' => '2026-10-22']))->toBe($denied)
        ->and($board->scheduleCopy((string) $schedule->id, '2026-10-22', 'K00001'))->toBe($denied)
        ->and($board->scheduleRemove((string) $schedule->id))->toBe($denied)
        ->and($board->scheduleLookup('sfk-0000001'))->toBe($denied)
        ->and($schedule->fresh()?->scheduled_on?->toDateString())->toBe('2026-10-21');
});

it('edits a schedule in the form as the board does', function () {
    $lines = boardLines();
    $job = productionJob();
    jobSale($job);
    $unsold = productionJob('角2封筒');
    $schedule = BagSchedule::query()->create(['scheduled_on' => '2026-10-21', 'machine_id' => $lines['K00002']->id, 'product_name' => 'テスト封筒', 'copies' => '10']);
    $this->actingAs($this->admin);

    // 注番 chosen: 担当者 / 品名 / 部数 of 06. 売上入力 are shown and saved
    Livewire::test(EditBagSchedule::class, ['record' => $schedule->getRouteKey()])
        ->fillForm(['machine_id' => $lines['K00001']->id, 'order_id' => $job->id, 'highlight_color' => 'green', 'notes' => "急ぎ\n\nハート"])
        ->assertSchemaStateSet(['person_initial' => '営業 花子', 'product_name' => '長3封筒 クラフト', 'copies' => '5000.0000'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($schedule->fresh()?->only(['order_id', 'person_initial', 'product_name', 'copies', 'notes', 'highlight_color', 'entry_mode']))->toBe([
        'order_id' => $job->id, 'person_initial' => '営業 花子', 'product_name' => '長3封筒 クラフト', 'copies' => '5000.0000', 'notes' => "急ぎ\nハート",
        'highlight_color' => 'green', 'entry_mode' => '受注から注番を取得']);

    Livewire::test(ViewBagSchedule::class, ['record' => $schedule->getRouteKey()])
        ->assertSeeInOrder(['日付', '2026-10-21', '機械名', '1号機', 'ハイライト', '緑', '注番', $job->order_number, '担当者', '営業 花子', '部数', '5,000', '品名', '長3封筒 クラフト',
            '備考', '急ぎ、ハート'])
        ->assertDontSee('レコードを再利用する'); // no create page: nothing to copy into

    // a job with no sale is not saved; without a 注番, 品名 and 部数 are required
    Livewire::test(EditBagSchedule::class, ['record' => $schedule->getRouteKey()])
        ->fillForm(['order_id' => $unsold->id])
        ->call('save')
        ->assertHasErrors(['data.order_id']);
    Livewire::test(EditBagSchedule::class, ['record' => $schedule->getRouteKey()])
        ->fillForm(['order_id' => null, 'product_name' => '', 'copies' => null, 'notes' => implode("\n", array_fill(0, 11, '注'))])
        ->call('save')
        ->assertHasFormErrors(['product_name' => 'required', 'copies' => 'required', 'notes']);
    expect($schedule->fresh()?->order_id)->toBe($job->id);

    // a schedule of the legacy app: its free 品名 in the form, its times kept
    $legacy = BagSchedule::query()->create(['starts_at' => '2026-10-23 08:00', 'ends_at' => '2026-10-23 12:00', 'machine_id' => $lines['K00002']->id, 'usage_status' => '完了',
        'free_product_name' => '試作封筒', 'copies' => '300']);
    Livewire::test(EditBagSchedule::class, ['record' => $legacy->getRouteKey()])
        ->assertSchemaStateSet(['scheduled_on' => '2026-10-23', 'product_name' => '試作封筒'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($legacy->fresh()?->only(['product_name', 'usage_status']))->toBe(['product_name' => '試作封筒', 'usage_status' => '完了']);
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

it('notifies the administrators and 営業関係者 of a new incident', function () {
    $job = productionJob();
    $sales = User::factory()->create(['name' => '営業 花子']);
    $outsider = User::factory()->create(['name' => '他部署']);
    $manager = User::factory()->admin()->create(['name' => '工場長']);
    openAppTo('accident-report', $sales);
    $this->actingAs($this->admin);

    Livewire::test(CreateAccidentReport::class)
        ->fillForm(['occurred_on' => '2026-10-20', 'reporter_id' => $this->worker->id, 'order_id' => $job->id, 'machine_id' => $this->machine->id, 'sales_users' => [$sales->id, $outsider->id],
            'description' => '糊がはみ出した'])
        ->call('create')
        ->assertHasNoFormErrors();

    $report = AccidentReport::query()->sole();
    $notice = $sales->notifications()->sole();
    expect($notice->data['title'])->toBe(AccidentReportResource::appName().': '.AccidentReportResource::titleOf($report))
        ->and($notice->data['body'])->toBe('事故発生日: 2026-10-20 / 報告者: 製造 太郎 / 長3封筒')
        ->and($manager->notifications()->count())->toBe(1)
        // the writer knows; who may not open the app is not told
        ->and($this->admin->notifications()->count())->toBe(0)
        ->and($outsider->notifications()->count())->toBe(0);
});
