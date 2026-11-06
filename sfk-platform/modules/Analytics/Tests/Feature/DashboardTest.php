<?php

/*
 * The dashboard (/erp; bug.md 2026-11-05, design_ref/Dashboard.png): ダッシュボード with the period and 新規作成, then
 * - rows 1–2 for 財務分析を表示: 売上合計 (目標 of 25. 販売先別売上計画), 売上概要 (入金済 / 入金待ち / 期限超過 / 未請求),
 *   入出金, 上位の売上先, 担当者別売上, 売れ筋 TOP3;
 * - rows 3–4 for everyone: 作業者ランキング (amount and hours of 08. 日報入力), 休暇・勤怠予定 (今日 / 明日 of 16. 申請管理),
 *   クイックアクション (one click), 最近のアクティビティ (only what concerns the user);
 * and アプリ of the top bar in place of the search box.
 */

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Analytics\Application\ActivityFeed;
use Modules\Analytics\Application\PersonalOverview;
use Modules\Analytics\Application\SalesDashboard;
use Modules\Analytics\Application\TeamDashboard;
use Modules\Analytics\Domain\Period;
use Modules\Analytics\Filament\Users\UserOverviewCard;
use Modules\Analytics\Filament\Widgets\AbsencesCard;
use Modules\Analytics\Filament\Widgets\PersonalOverviewCard;
use Modules\Analytics\Filament\Widgets\QuickActionsCard;
use Modules\Analytics\Filament\Widgets\RevenueCard;
use Modules\Analytics\Filament\Widgets\StaffSalesCard;
use Modules\Analytics\Filament\Widgets\TopProductsCard;
use Modules\Analytics\Filament\Widgets\TopWorkersCard;
use Modules\Analytics\Models\SalesForecast;
use Modules\Analytics\Models\SalesPlan;
use Modules\Core\Application\Apps\AppUsageLog;
use Modules\Core\Application\Notifications\SystemNotification;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Filament\Portal\PortalPage;
use Modules\Core\Filament\Users\UserDetailWidgets;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\RecordVersion;
use Modules\Core\Models\Space;
use Modules\HumanResources\Domain\LeaveRequests\LeaveRequestStatus;
use Modules\HumanResources\Models\LeaveRequest;
use Modules\MasterData\Filament\Resources\Employees\EmployeeResource;
use Modules\MasterData\Models\Partner;
use Modules\Production\Models\DailyReport;
use Modules\Purchasing\Models\Payment;
use Modules\Sales\Filament\Resources\Receipts\ReceiptResource;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\Receipt;
use Modules\Sales\Models\Sale;
use Modules\Settings\Filament\Resources\Users\Pages\EditUser;
use Modules\Settings\Filament\Resources\Users\Pages\ViewUser;
use Modules\Settings\Filament\Resources\Users\UserResource;

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00'));
    $this->admin = User::factory()->superAdmin()->create(['name' => '管理者']);
    $this->staff = User::factory()->create(['name' => '営業 一郎']);
    $this->other = User::factory()->create(['name' => '営業 二郎']);
    $this->employee = grantDashboards(User::factory()->create(['name' => '製造 花子']), 'erp');

    // 翌月末日 and 当月末日 collection
    $this->alpha = Partner::query()->create(['name' => '株式会社アルファ', 'short_name' => 'アルファ', 'is_customer' => true, 'code' => 'C001', 'collection_month' => '翌月', 'collection_day' => '末日']);
    $this->beta = Partner::query()->create(['name' => '株式会社ベータ', 'short_name' => 'ベータ', 'is_customer' => true, 'code' => 'C002', 'collection_month' => '当月', 'collection_day' => '末日']);
    $sale = function (Partner $customer, string $soldOn, int $amount, ?User $staff, string $product, ?Invoice $invoice = null): Sale {
        $sale = Sale::query()->create(['customer_id' => $customer->id, 'sold_on' => $soldOn, 'delivery_due_on' => $soldOn, 'product_name' => $product, 'amount' => $amount]);
        if ($staff !== null) {
            $sale->assignees()->attach($staff->id);
        }
        if ($invoice !== null) {
            DB::table('sales')->where('id', $sale->id)->update(['invoice_id' => $invoice->id]);
        }

        return $sale;
    };
    $invoice = fn (Partner $customer, string $on): Invoice => Invoice::query()->create(['customer_id' => $customer->id, 'invoiced_on' => $on, 'closing_day' => '末日']);

    $paidInvoice = $invoice($this->alpha, '2026-10-20');
    $sale($this->alpha, '2026-10-10', 100000, $this->staff, '長3封筒', $paidInvoice); // 入金済
    $sale($this->alpha, '2026-10-12', 50000, $this->staff, '角2封筒', $invoice($this->alpha, '2026-10-31')); // due 11/30: 入金待ち
    $sale($this->beta, '2026-10-15', 30000, $this->other, '長3封筒', $invoice($this->beta, '2026-10-20')); // due 10/31: 期限超過
    $sale($this->beta, '2026-10-20', 20000, null, '洋2封筒'); // 未請求
    $sale($this->alpha, '2026-09-15', 80000, $this->staff, '長3封筒'); // the month before
    $sale($this->alpha, '2025-10-10', 160000, $this->staff, '長3封筒'); // a year before

    $receipt = Receipt::query()->create(['customer_id' => $this->alpha->id, 'invoice_id' => $paidInvoice->id, 'closing_day' => '末日']);
    $receipt->lines()->create(['received_on' => '2026-10-25', 'amount' => 110000]);
    DB::table('receipts')->where('id', $receipt->id)->update(['billed_amount' => 110000, 'settled_amount' => 110000]);
    $payment = Payment::query()->create(['paid_on' => '2026-10-28']);
    DB::table('payments')->where('id', $payment->id)->update(['total' => 40000]);
    $plan = SalesPlan::query()->create(['customer_id' => $this->alpha->id, 'planned_on' => '2026-05-01', 'plan_m10' => 400000]);

    $this->period = Period::between('2026-10-01', '2026-10-31', CarbonImmutable::today()->toDateTimeImmutable());
});

it('sums the money of a period: totals, statuses, target, cash and the tops', function () {
    $this->actingAs($this->admin);
    $service = app(SalesDashboard::class);

    $overview = $service->overview($this->period, CarbonImmutable::today()->toDateTimeImmutable());
    expect($overview['amount'])->toBe(200000)
        ->and($overview['count'])->toBe(4)
        ->and(array_map(fn (array $status): int => $status['count'], $overview['statuses']))->toBe(['paid' => 1, 'pending' => 1, 'overdue' => 1, 'unbilled' => 1])
        ->and($overview['statuses']['paid']['amount'])->toBe(100000)
        ->and(array_sum($overview['parts']))->toBe(4)
        ->and($service->totals($this->period->previous()))->toBe(['amount' => 80000, 'count' => 1])
        ->and($service->totals($this->period->lastYear()))->toBe(['amount' => 160000, 'count' => 1])
        ->and($service->target($this->period))->toBe(400000)
        ->and($service->cashFlow($this->period))->toBe(['inflow' => 110000, 'outflow' => 40000, 'net' => 70000])
        ->and(array_column($service->topCustomers($this->period), 'name'))->toBe(['株式会社アルファ', '株式会社ベータ'])
        ->and(array_column($service->topCustomers($this->period), 'amount'))->toBe([150000, 50000])
        ->and($service->topStaff($this->period))->toBe([
            ['id' => $this->staff->id, 'name' => '営業 一郎', 'count' => 2, 'amount' => 150000],
            ['id' => $this->other->id, 'name' => '営業 二郎', 'count' => 1, 'amount' => 30000],
        ])
        ->and(array_column($service->topProducts($this->period), 'name'))->toBe(['長3封筒', '角2封筒', '洋2封筒'])
        ->and(SalesDashboard::dueOn(new DateTimeImmutable('2026-01-15'), '翌々月', '25日')->format('Y-m-d'))->toBe('2026-03-25')
        ->and(SalesDashboard::dueOn(new DateTimeImmutable('2026-01-15'), '翌月', '末日')->format('Y-m-d'))->toBe('2026-02-28');
});

it('shows the money cards with 財務分析を表示 only, the team cards to everyone', function () {
    $this->actingAs($this->admin);
    Livewire::withQueryParams(['filters' => ['from' => '2026-10-01', 'to' => '2026-10-31']])->test(PortalPage::class)
        ->assertOk()
        ->assertSee(['ダッシュボード', '2026/10/01 〜 2026/10/31', '新規作成', '受注入力', '売上入力', '日報入力'])
        ->assertSee(['売上合計', '¥200,000', '¥400,000', '50% 達成', '売上概要', '入金済', '入金待ち', '期限超過', '未請求', '入出金', '¥11万', '上位の売上先',
            '株式会社アルファ', '担当者別売上', '営業 一郎', '売れ筋 TOP3', '長3封筒'])
        ->assertSee(['作業者ランキング', '休暇・勤怠予定', 'クイックアクション', '最近のアクティビティ']);

    // an employee: no money, no new records (no app may get one here), the team cards
    $this->actingAs($this->employee);
    Livewire::test(PortalPage::class)
        ->assertDontSee(['売上合計', '売上概要', '入出金', '上位の売上先', '担当者別売上', '売れ筋 TOP3', '株式会社アルファ'])
        ->assertSee(['作業者ランキング', '休暇・勤怠予定', 'クイックアクション', '最近のアクティビティ'])
        ->assertSee('title="追加できるアプリがありません"', escape: false);

    $this->employee->forceFill(['show_financial_analysis' => true])->save();
    expect($this->employee->seesFinancialAnalysis())->toBeTrue()
        ->and(User::factory()->superAdmin()->make()->seesFinancialAnalysis())->toBeTrue();
});

it('changes the period with the presets or two dates', function () {
    $this->actingAs($this->admin);

    Livewire::test(PortalPage::class)
        ->assertSet('filters', ['from' => '2026-10-07', 'to' => '2026-11-05', 'scope' => 'overall'])
        ->call('setPeriod', 'lastMonth')
        ->assertSet('filters', ['from' => '2026-10-01', 'to' => '2026-10-31', 'scope' => 'overall'])
        ->call('setPeriod', 'month')
        ->assertSet('filters', ['from' => '2026-11-01', 'to' => '2026-11-05', 'scope' => 'overall'])
        ->call('applyPeriod', '2026-09-30', '2026-09-01')
        ->assertSet('filters', ['from' => '2026-09-01', 'to' => '2026-09-30', 'scope' => 'overall'])
        ->call('applyPeriod', '2026-02-31', 'x')
        ->assertSet('filters', ['from' => '2026-09-01', 'to' => '2026-09-30', 'scope' => 'overall']);
});

it('lists who is off today and tomorrow, and approves a request with one click', function () {
    $request = fn (User $user, string $on, LeaveRequestStatus $status, string $category = '有給休暇') => tap(
        LeaveRequest::query()->create(['request_type' => '休暇申請', 'applicant_id' => $user->id, 'category' => $category, 'target_on' => $on, 'reason' => '私用']),
        fn (LeaveRequest $leave) => $leave->forceFill(['status' => $status])->save(),
    );
    $pending = $request($this->staff, '2026-11-05', LeaveRequestStatus::Pending);
    $request($this->other, '2026-11-06', LeaveRequestStatus::Approved, '半有給(午前有給)');
    $request($this->other, '2026-11-05', LeaveRequestStatus::Draft); // not asked for yet
    $request($this->staff, '2026-11-07', LeaveRequestStatus::Approved); // the day after tomorrow
    $this->actingAs($this->admin);

    $days = app(TeamDashboard::class)->absences(CarbonImmutable::today()->toDateTimeImmutable());
    expect(array_column($days['today'], 'name'))->toBe(['営業 一郎'])
        ->and(array_column($days['tomorrow'], 'category'))->toBe(['半有給(午前有給)']);

    $actions = app(TeamDashboard::class)->pendingActions($this->admin);
    expect(array_column($actions, 'title'))->toContain('休暇申請を承認')
        ->and(collect($actions)->firstWhere('key', 'leave:'.$pending->id)['action'])->toBe('承認');

    Livewire::test(QuickActionsCard::class)
        ->assertSee(['休暇申請を承認', '営業 一郎・11/5 有給休暇', '承認'])
        ->call('runAction', 'leave:'.$pending->id)
        ->assertNotified('「承認済」にしました');
    expect($pending->fresh()->status)->toBe(LeaveRequestStatus::Approved)
        ->and(collect(app(TeamDashboard::class)->pendingActions($this->admin))->pluck('key'))->not->toContain('leave:'.$pending->id);

    // the applicant (16. 申請管理 open to everyone): their own draft waits for 申請する; another's request is not theirs
    $app = new BusinessApp(['code' => 'leave-request', 'name' => '16. 申請管理']);
    $app->forceFill(['status' => AppStatus::Published])->save();
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '総務スペース']);
    $app->spaces()->attach($space);
    $space->users()->attach(grantDashboards($this->other, 'erp'));
    $this->actingAs($this->other);
    $mine = app(TeamDashboard::class)->pendingActions($this->other);
    expect(array_column($mine, 'action'))->toBe(['申請する']);
    Livewire::test(QuickActionsCard::class)->call('runAction', 'leave:'.$pending->id)->assertNotified('この操作を行う権限がありません。');
});

it('shows only the activity that concerns the user', function () {
    $mine = LeaveRequest::query()->create(['request_type' => '休暇申請', 'applicant_id' => $this->admin->id, 'category' => '有給休暇', 'target_on' => '2026-11-10', 'reason' => '私用']);
    $theirs = LeaveRequest::query()->create(['request_type' => '休暇申請', 'applicant_id' => $this->other->id, 'category' => '慶弔休暇', 'target_on' => '2026-11-11', 'reason' => '私用']);
    $version = fn (LeaveRequest $record, User $user, string $event) => RecordVersion::query()->forceCreate([
        'record_type' => $record->getMorphClass(), 'record_id' => $record->id, 'version' => 1, 'event' => $event, 'snapshot' => [], 'changed' => [], 'user_id' => $user->id,
    ]);
    $version($mine, $this->admin, 'created');
    $version($theirs, $this->other, 'updated'); // someone else's change of someone else's record
    $this->admin->notifyNow(new SystemNotification(['title' => '16. 申請管理: 2026-11-11 慶弔休暇', 'body' => '営業 二郎: 申請する', 'format' => 'filament', 'actions' => []]), ['database']);
    $this->actingAs($this->admin);

    $feed = app(ActivityFeed::class)->for($this->admin);
    expect(array_column($feed, 'kind'))->toEqualCanonicalizing(['notice', 'created'])
        ->and(collect($feed)->firstWhere('kind', 'created')['body'])->toContain('有給休暇')
        ->and(collect($feed)->firstWhere('kind', 'notice')['title'])->toBe('16. 申請管理: 2026-11-11 慶弔休暇');
});

it('puts アプリ in the top bar of the dashboard and 財務分析を表示 on the user settings', function () {
    $this->actingAs($this->admin)->get('/erp')->assertOk()->assertSee('sfk-apps-trigger', false)->assertSee('アプリ名で検索');
    $this->get('/erp/analysis/daily-reports')->assertOk()->assertDontSee('sfk-apps-trigger', false);

    Filament::setCurrentPanel('settings');
    Livewire::test(EditUser::class, ['record' => $this->staff->getRouteKey()])->assertFormFieldExists('show_financial_analysis');
});

it('shows only the 担当者 own money in 個人: sales, 予想, their 入金 and what is still to come in', function () {
    $this->actingAs($this->admin);
    SalesForecast::query()->create(['user_id' => $this->staff->id, 'fiscal_year' => 2026, 'forecast_m10' => 120000]);
    Receipt::query()->firstOrFail()->assignees()->attach($this->staff->id);
    $today = CarbonImmutable::today()->toDateTimeImmutable();

    $mine = app(SalesDashboard::class)->forStaff($this->staff->id);
    expect($mine->isPersonal())->toBeTrue()
        ->and(app(SalesDashboard::class)->isPersonal())->toBeFalse()
        ->and($mine->totals($this->period))->toBe(['amount' => 150000, 'count' => 2])
        ->and($mine->overview($this->period, $today)['statuses']['pending']['amount'])->toBe(50000)
        ->and($mine->target($this->period))->toBe(120000)
        ->and($mine->cashFlow($this->period))->toBe(['inflow' => 110000, 'outflow' => 0, 'net' => 110000])
        ->and(array_column($mine->topCustomers($this->period), 'amount'))->toBe([150000])
        ->and(array_column($mine->topProducts($this->period), 'name'))->toBe(['長3封筒', '角2封筒'])
        ->and(array_column($mine->latestSales($this->period), 'product'))->toBe(['角2封筒', '長3封筒'])
        ->and(app(SalesDashboard::class)->forStaff($this->other->id)->totals($this->period))->toBe(['amount' => 30000, 'count' => 1])
        ->and(app(SalesDashboard::class)->forStaff($this->other->id)->target($this->period))->toBeNull();
});

it('lets 全体 users switch the money to their own; 個人のみ users always see their own', function () {
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業スペース']);
    $space->users()->attach($this->staff);
    foreach (['sales-entry' => '06. 売上入力', ReceiptResource::appCode() => '21. 売入金管理'] as $code => $name) {
        $app = new BusinessApp(['code' => $code, 'name' => $name]);
        $app->forceFill(['status' => AppStatus::Published])->save();
        $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
        $app->spaces()->attach($space);
    }
    $period = ['from' => '2026-10-01', 'to' => '2026-10-31'];

    // 全体 (a super admin): the switch before the date button; 個人 shows only their own sales (none here)
    $this->actingAs($this->admin);
    Livewire::withQueryParams(['filters' => $period])->test(PortalPage::class)
        ->assertSet('filters.scope', 'overall')
        ->assertSeeInOrder(['role="radiogroup"', '全体', '個人', '2026/10/01 〜 2026/10/31'], escape: false)
        ->assertSee(['¥200,000', '担当者別売上', '売上目標'])
        ->call('setScope', 'personal')
        ->assertSet('filters.scope', 'personal')
        ->assertSet('filters.from', '2026-10-01')
        ->call('setPeriod', 'month')
        ->assertSet('filters.scope', 'personal')
        ->call('setScope', 'nonsense')
        ->assertSet('filters.scope', 'overall');
    // the cards follow the page filter
    $personal = [...$period, 'scope' => 'personal'];
    Livewire::test(StaffSalesCard::class, ['pageFilters' => $personal])->assertSee(['担当した売上', 'この期間の売上はありません。'])->assertDontSee('担当者別売上');
    Livewire::test(RevenueCard::class, ['pageFilters' => $personal])->assertSee(['売上予想', '¥0'])->assertDontSee('¥200,000');
    Livewire::test(RevenueCard::class, ['pageFilters' => [...$period, 'scope' => 'overall']])->assertSee(['売上目標', '¥200,000']);

    // 個人のみ: no switch, only their own sales, whatever the filters say
    grantDashboards($this->staff, 'erp')->forceFill(['show_financial_analysis' => true, 'financial_scope' => User::FINANCE_PERSONAL])->save();
    expect($this->staff->seesFinancialAnalysis())->toBeTrue()->and($this->staff->seesOverallFinance())->toBeFalse();
    $this->actingAs($this->staff);
    Livewire::withQueryParams(['filters' => [...$period, 'scope' => 'overall']])->test(PortalPage::class)
        ->assertSet('filters.scope', 'personal')
        ->assertDontSee('role="radiogroup"', escape: false)
        ->assertSee(['¥150,000', '担当した売上', '角2封筒', '入金待ち'])
        ->assertDontSee(['¥200,000', '担当者別売上', '株式会社ベータ', '差引'])
        ->call('setScope', 'overall')
        ->assertSet('filters.scope', 'personal');
});

it('sets 財務分析の範囲 next to 財務分析を表示 in ユーザー管理', function () {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('settings');

    Livewire::test(EditUser::class, ['record' => $this->staff->getRouteKey()])
        ->assertFormFieldHidden('financial_scope')
        ->fillForm(['show_financial_analysis' => true])
        ->assertFormFieldVisible('financial_scope')
        ->fillForm(['financial_scope' => 'everything'])
        ->call('save')
        ->assertHasFormErrors(['financial_scope'])
        ->fillForm(['financial_scope' => User::FINANCE_PERSONAL])
        ->call('save')
        ->assertHasNoFormErrors(['financial_scope', 'show_financial_analysis']);

    $this->staff->forceFill(['show_financial_analysis' => true, 'financial_scope' => User::FINANCE_PERSONAL])->save();
    expect($this->staff->seesOverallFinance())->toBeFalse()
        ->and($this->admin->seesOverallFinance())->toBeTrue()
        ->and(User::factory()->make()->financial_scope)->toBe(User::FINANCE_OVERALL);
    Livewire::test(ViewUser::class, ['record' => $this->staff->getRouteKey()])->assertSee(['財務分析の範囲', '個人のみ']);
});

it('shows 個人の概要 to users without 財務分析を表示, and on the page of a user to themselves and administrators', function () {
    $worker = $this->employee;
    $colleague = grantDashboards(User::factory()->create(), 'erp');
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '製造スペース']);
    $space->users()->attach([$worker->id, $colleague->id]);
    foreach (['daily-report' => '08. 日報入力', 'leave-request' => '16. 申請管理', 'employee-master' => '02. 従業員マスタ'] as $code => $name) {
        $app = new BusinessApp(['code' => $code, 'name' => $name]);
        $app->forceFill(['status' => AppStatus::Published])->save();
        $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
        $app->spaces()->attach($space);
    }
    $worker->forceFill(['work_rate' => 1200, 'overtime_rate' => 1500])->save();
    // 4 h + 1.5 h overtime: 4 × 1,200 + 1.5 × 1,500 = 7,050; and 2 h: 2,400 — 7.5 hours, ¥9,450
    DailyReport::query()->create(['worked_on' => '2026-10-05', 'user_id' => $worker->id, 'started_at' => '08:00', 'ended_at' => '12:00', 'break_time' => '00:00',
        'overtime_started_at' => '17:00', 'overtime_ended_at' => '18:30', 'overtime_break' => '00:00']);
    DailyReport::query()->create(['worked_on' => '2026-10-06', 'user_id' => $worker->id, 'started_at' => '13:00', 'ended_at' => '15:00', 'break_time' => '00:00']);
    DailyReport::query()->create(['worked_on' => '2026-09-30', 'user_id' => $worker->id, 'started_at' => '08:00', 'ended_at' => '17:00', 'break_time' => '01:00']); // before
    DailyReport::query()->create(['worked_on' => '2026-10-07', 'user_id' => $this->other->id, 'started_at' => '08:00', 'ended_at' => '17:00', 'break_time' => '01:00']); // someone else

    $leave = function (User $applicant, string $on, string $category, LeaveRequestStatus $status = LeaveRequestStatus::Approved, ?User $worker = null): void {
        $request = LeaveRequest::query()->create(['request_type' => '休暇申請', 'applicant_id' => $applicant->id, 'worker_id' => $worker?->id, 'category' => $category, 'target_on' => $on, 'reason' => '私用']);
        $request->forceFill(['status' => $status])->save();
    };
    $leave($worker, '2026-10-09', '有給休暇');
    $leave($worker, '2026-10-13', '半休(午前欠勤)');
    $leave($worker, '2026-10-14', '遅刻(時間欠勤)'); // hours, not a day
    $leave($worker, '2026-10-15', '慶弔休暇', LeaveRequestStatus::Pending); // not approved
    $leave($this->other, '2026-10-16', '有給休暇'); // someone else
    $leave($this->admin, '2026-10-20', '半有給(午後有給)', worker: $worker); // entered for them by an administrator

    $this->actingAs($this->admin);
    expect(app(PersonalOverview::class)->for($worker, $this->period))
        ->toBe(['hours' => 7.5, 'income' => 9450, 'reports' => 2, 'leave_days' => 2.0, 'paid_leave_days' => 1.5]);

    // the employee on the dashboard: first row, their own figures (the requests they may see: their own)
    $this->actingAs($worker);
    Livewire::withQueryParams(['filters' => ['from' => '2026-10-01', 'to' => '2026-10-31']])->test(PortalPage::class)
        ->assertSeeInOrder(['個人の概要', '2026/10/01 〜 2026/10/31', '勤務時間', '7.5', '時間', '収入', '¥9,450', '休暇日数', '1.5', '日', '有給休暇', '1', '日', '作業者ランキング']);
    expect(PersonalOverviewCard::canView())->toBeTrue();
    $this->actingAs($this->admin);
    expect(PersonalOverviewCard::canView())->toBeFalse(); // 財務分析を表示: the money instead

    // the page of the user: administrators and the person; not other employees (作業単価)
    $this->travelTo(CarbonImmutable::parse('2026-10-31 10:00'));
    expect(app(UserDetailWidgets::class)->for($worker))->toBe([UserOverviewCard::class]);
    Livewire::test(UserOverviewCard::class, ['record' => $worker])
        ->assertSee(['個人の概要', '今月', '2026/10/01 〜 2026/10/31', '¥9,450', '1.5'])
        ->set('range', 'lastMonth')
        ->assertSee(['2026/09/01 〜 2026/09/30', '¥9,600'])
        ->set('range', 'nonsense')
        ->assertSet('range', 'month');
    Filament::setCurrentPanel('settings');
    $this->get(UserResource::getUrl('view', ['record' => $worker]))->assertOk()->assertSee(['個人の概要', '¥9,450']);
    Filament::setCurrentPanel('erp');
    $this->get(EmployeeResource::getUrl('view', ['record' => $worker]))->assertOk()->assertSee('個人の概要');

    $this->actingAs($worker);
    expect(app(UserDetailWidgets::class)->for($worker))->toBe([UserOverviewCard::class]);
    $this->get(EmployeeResource::getUrl('view', ['record' => $worker]))->assertOk()->assertSee('個人の概要');
    $this->actingAs($colleague);
    expect(app(UserDetailWidgets::class)->for($worker))->toBe([]);
    Livewire::test(UserOverviewCard::class, ['record' => $worker])->assertDontSee(['個人の概要', '¥9,450']);
    $this->get(EmployeeResource::getUrl('view', ['record' => $worker]))->assertOk()->assertDontSee('個人の概要');
});

it('ranks the 作業者 with their share of the period, and writes the 品名 of 売れ筋 TOP3 across, on top of the bars', function () {
    $this->actingAs($this->admin);
    DailyReport::query()->create(['worked_on' => '2026-10-05', 'user_id' => $this->staff->id, 'produced' => '1000', 'processing_rate' => '2']);
    DailyReport::query()->create(['worked_on' => '2026-10-06', 'user_id' => $this->other->id, 'produced' => '1000', 'processing_rate' => '1']);
    $filters = ['from' => '2026-10-01', 'to' => '2026-10-31'];

    Livewire::test(TopWorkersCard::class, ['pageFilters' => $filters])
        ->assertSeeInOrder(['営業 一郎', '67%', '¥2,000', '営業 二郎', '33%', '¥1,000']);
    Livewire::test(TopProductsCard::class, ['pageFilters' => $filters])
        ->assertSeeInOrder(['sfk-products-name', '長3封筒', '¥13万', 'sfk-products-name', '角2封筒'], escape: false)
        ->assertDontSee('writing-mode', escape: false);
});

it('lists the apps the user opened last, each with its last record, when no クイックアクション waits', function () {
    $this->actingAs($this->admin);
    $sale = Sale::query()->orderBy('id')->firstOrFail();
    $log = app(AppUsageLog::class);
    // opening a record page notes the app and the record (HasAppChrome)
    $this->get(SaleResource::getUrl('view', ['record' => $sale]))->assertOk();
    $this->travel(1)->minutes();
    $log->touch($this->admin, 'receipt');
    $log->touch($this->admin, 'no-such-app'); // not an app here: left out

    $recent = QuickActionsCard::recentRecords($this->admin);
    expect(array_column($recent, 'code'))->toBe(['receipt', 'sales-entry'])
        ->and($recent[1]['record'])->toBe($sale->id)
        ->and($recent[1]['url'])->toBe(SaleResource::getUrl('view', ['record' => $sale]))
        ->and($recent[0]['record'])->toBeNull();

    Livewire::test(QuickActionsCard::class)
        ->assertSee(['#'.$sale->id, SaleResource::getUrl('view', ['record' => $sale])])
        ->assertDontSee('対応が必要な作業はありません。');

    // someone else's use is not shown
    expect(QuickActionsCard::recentRecords($this->other))->toBe([]);
});

it('calls the card 最近表示した項目 while it lists recently viewed apps, クイックアクション when something waits', function () {
    $this->actingAs($this->admin);
    Livewire::test(QuickActionsCard::class)->assertSee(['クイックアクション', '対応が必要な作業はありません。'])->assertDontSee('最近表示した項目');

    app(AppUsageLog::class)->touch($this->admin, 'sales-entry', Sale::query()->firstOrFail());
    Livewire::test(QuickActionsCard::class)->assertSee('最近表示した項目')->assertDontSee('クイックアクション');

    $request = LeaveRequest::query()->create(['request_type' => '休暇申請', 'applicant_id' => $this->staff->id, 'category' => '有給休暇', 'target_on' => '2026-11-10', 'reason' => '私用']);
    $request->forceFill(['status' => LeaveRequestStatus::Pending])->save();
    Livewire::test(QuickActionsCard::class)->assertSee(['クイックアクション', '休暇申請を承認'])->assertDontSee('最近表示した項目');
});

it('frames the user\'s own row in 作業者ランキング', function () {
    DailyReport::query()->create(['worked_on' => '2026-10-05', 'user_id' => $this->staff->id, 'produced' => '1000', 'processing_rate' => '2']);
    DailyReport::query()->create(['worked_on' => '2026-10-06', 'user_id' => $this->admin->id, 'produced' => '1000', 'processing_rate' => '1']);
    $filters = ['pageFilters' => ['from' => '2026-10-01', 'to' => '2026-10-31']];

    // not among the 作業者: nothing framed
    $super = User::factory()->superAdmin()->create();
    $this->actingAs($super);
    Livewire::test(TopWorkersCard::class, $filters)->assertSee(['営業 一郎', '管理者'])->assertDontSee('aria-current="true"', escape: false);

    $this->actingAs($this->admin);
    Livewire::test(TopWorkersCard::class, $filters)
        ->assertSeeInOrder(['sfk-workers-row is-1"', '営業 一郎', 'sfk-workers-row is-2 is-me"', 'aria-current="true"', '管理者'], escape: false);
});

it('puts クイックアクション first and 作業者ランキング last in row 3 for users without 財務分析を表示', function () {
    $row3 = fn (): array => array_values(array_filter(
        (new PortalPage)->dashboardWidgets(),
        fn (string $widget): bool => in_array($widget, [TopWorkersCard::class, AbsencesCard::class, QuickActionsCard::class], true),
    ));

    $this->actingAs($this->admin);
    expect($row3())->toBe([TopWorkersCard::class, AbsencesCard::class, QuickActionsCard::class]);

    $this->actingAs($this->employee);
    expect($row3())->toBe([QuickActionsCard::class, AbsencesCard::class, TopWorkersCard::class])
        ->and((new PortalPage)->dashboardWidgets()[0])->toBe(PersonalOverviewCard::class);
    Livewire::test(PortalPage::class)->assertSeeInOrder(['個人の概要', 'sfk-quick-title', '休暇・勤怠予定', '作業者ランキング'], escape: false);
});
