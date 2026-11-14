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
use Modules\Analytics\Domain\SalesCoverage;
use Modules\Analytics\Filament\Dashboard\DashboardCard;
use Modules\Analytics\Filament\Dashboard\DashboardTips;
use Modules\Analytics\Filament\Dashboard\TrendChart;
use Modules\Analytics\Filament\Users\UserOverviewCard;
use Modules\Analytics\Filament\Widgets\AbsencesCard;
use Modules\Analytics\Filament\Widgets\CashFlowCard;
use Modules\Analytics\Filament\Widgets\PersonalOverviewCard;
use Modules\Analytics\Filament\Widgets\QuickActionsCard;
use Modules\Analytics\Filament\Widgets\RecentActivityCard;
use Modules\Analytics\Filament\Widgets\RevenueCard;
use Modules\Analytics\Filament\Widgets\SalesOverviewCard;
use Modules\Analytics\Filament\Widgets\SalesTrendCard;
use Modules\Analytics\Filament\Widgets\StaffSalesCard;
use Modules\Analytics\Filament\Widgets\TopCustomersCard;
use Modules\Analytics\Filament\Widgets\TopProductsCard;
use Modules\Analytics\Filament\Widgets\TopWorkersCard;
use Modules\Analytics\Filament\Widgets\WorkTrendCard;
use Modules\Analytics\Models\SalesForecast;
use Modules\Analytics\Models\SalesPlan;
use Modules\Core\Application\Apps\AppUsageLog;
use Modules\Core\Application\Notifications\SystemNotification;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Filament\Portal\PortalPage;
use Modules\Core\Filament\Support\Tip;
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

it('shows the money cards with 財務分析を表示 only, the team cards to everyone (作業者ランキング: 全体 only)', function () {
    $this->actingAs($this->admin);
    Livewire::withQueryParams(['filters' => ['from' => '2026-10-01', 'to' => '2026-10-31']])->test(PortalPage::class)
        ->assertOk()
        ->assertSee(['ダッシュボード', '2026/10/01 〜 2026/10/31', '新規作成', '受注入力', '売上入力', '日報入力'])
        ->assertSee(['売上合計', '¥200,000', '¥400,000', '50% 達成', '売上概要', '入金済', '入金待ち', '期限超過', '未請求', '入出金', '+¥110,000', '−¥40,000', '¥70,000', '上位の売上先',
            '株式会社アルファ', '担当者別売上', '営業 一郎', '売れ筋 TOP3', '長3封筒'])
        ->assertSee(['作業者ランキング', '休暇・勤怠予定', 'クイックアクション', '最近のアクティビティ']);

    // an employee: no money, no new records (no app may get one here), the team cards
    $this->actingAs($this->employee);
    Livewire::test(PortalPage::class)
        ->assertDontSee(['売上合計', '売上概要', '入出金', '上位の売上先', '担当者別売上', '売れ筋 TOP3', '株式会社アルファ', '作業者ランキング', '売上の推移'])
        ->assertSee(['勤務時間・収入の推移', '休暇・勤怠予定', 'クイックアクション', '最近のアクティビティ'])
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
        ->assertSee(['¥150,000', '入金待ち', '売上の推移', '2026年', '2025年'])
        ->assertDontSee(['¥200,000', '担当者別売上', '担当した売上', '上位の売上先', '売れ筋 TOP3', '作業者ランキング', '株式会社ベータ', '差引'])
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
        ->toBe(['hours' => 7.5, 'income' => 9450, 'reports' => 2, 'work_days' => 2, 'leave_days' => 2.0, 'paid_leave_days' => 1.5]);

    // the employee on the dashboard: first row, their own figures (the requests they may see: their own)
    $this->actingAs($worker);
    Livewire::withQueryParams(['filters' => ['from' => '2026-10-01', 'to' => '2026-10-31']])->test(PortalPage::class)
        ->assertSeeInOrder(['個人の概要', '2026/10/01 〜 2026/10/31', '勤務時間', '7.5', '時間', '出勤日数', '2', '日', '収入', '¥9,450', '休暇日数', '1.5', '日', '有給休暇', '1', '日', '勤務時間・収入の推移'])
        ->assertDontSee('作業者ランキング');
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
        ->assertSeeInOrder(['sfk-products-name', '長3封筒', '¥130,000', 'sfk-products-name', '角2封筒'], escape: false)
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

it('puts 最近表示した項目, 休暇・勤怠予定 and 最近のアクティビティ in one row for users without 財務分析を表示 (2026-11-14)', function () {
    $row3 = fn (): array => array_values(array_filter(
        (new PortalPage)->dashboardWidgets(),
        fn (string $widget): bool => in_array($widget, [TopWorkersCard::class, AbsencesCard::class, QuickActionsCard::class, RecentActivityCard::class], true),
    ));

    $this->actingAs($this->admin);
    expect($row3())->toBe([TopWorkersCard::class, QuickActionsCard::class, AbsencesCard::class, RecentActivityCard::class]);
    // 全体: 作業者ランキング · 最近表示した項目 a third each; 休暇・勤怠予定 · 最近のアクティビティ half each — unchanged
    Livewire::test(QuickActionsCard::class)->assertSee('sfk-dash-span-4');
    Livewire::test(AbsencesCard::class)->assertSee('sfk-dash-span-6');
    Livewire::test(RecentActivityCard::class)->assertSee(['sfk-dash-span-6', 'is-single'])->assertDontSee('sfk-dash-t-full');

    $this->actingAs($this->employee);
    expect($row3())->toBe([QuickActionsCard::class, AbsencesCard::class, RecentActivityCard::class])
        ->and(array_slice((new PortalPage)->dashboardWidgets(), 0, 2))->toBe([PersonalOverviewCard::class, WorkTrendCard::class]);
    Livewire::test(PortalPage::class)->assertSeeInOrder(['個人の概要', '勤務時間・収入の推移', 'sfk-quick-title', '休暇・勤怠予定', '最近のアクティビティ'], escape: false);
    // one row, a third each; the activity in one column (on tablets its own row under the two others)
    Livewire::test(QuickActionsCard::class)->assertSee('sfk-dash-span-4');
    Livewire::test(AbsencesCard::class)->assertSee('sfk-dash-span-4');
    Livewire::test(RecentActivityCard::class)->assertSee(['sfk-dash-span-4', 'sfk-dash-t-full', 'is-single']);

    // 財務分析を表示 without the sales app shows no money either: the same row of three (the 個人 layout with the sales
    // app keeps half each — 'lays out 個人 the same …')
    $this->employee->forceFill(['show_financial_analysis' => true, 'financial_scope' => User::FINANCE_PERSONAL])->save();
    Livewire::test(AbsencesCard::class)->assertSee('sfk-dash-span-4');
    Livewire::test(RecentActivityCard::class)->assertSee('sfk-dash-span-4');
});

it('shows 作業者ランキング, 上位の売上先, 担当者別売上 and 売れ筋 TOP3 only to users who see everyone\'s money (bug.md 2026-11-07)', function () {
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業スペース']);
    $space->users()->attach([$this->staff->id, $this->employee->id]);
    $app = new BusinessApp(['code' => 'sales-entry', 'name' => '06. 売上入力']);
    $app->forceFill(['status' => AppStatus::Published])->save();
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
    $app->spaces()->attach($space);
    $overallOnly = [TopCustomersCard::class, TopProductsCard::class, StaffSalesCard::class, TopWorkersCard::class];
    $visible = fn (): array => array_values(array_intersect(
        (new PortalPage)->dashboardWidgets(),
        [...$overallOnly, SalesTrendCard::class, WorkTrendCard::class, PersonalOverviewCard::class],
    ));

    // 全体 (super admins always; and an employee ticked 全体)
    $this->actingAs($this->admin);
    expect($visible())->toBe([SalesTrendCard::class, ...$overallOnly]);
    grantDashboards($this->staff, 'erp')->forceFill(['show_financial_analysis' => true, 'financial_scope' => User::FINANCE_OVERALL])->save();
    $this->actingAs($this->staff);
    expect($visible())->toBe([SalesTrendCard::class, ...$overallOnly]);

    // 個人のみ: their sales over the year instead
    $this->staff->forceFill(['financial_scope' => User::FINANCE_PERSONAL])->save();
    expect($visible())->toBe([SalesTrendCard::class]);

    // without 財務分析を表示: 個人の概要 and their hours and income over the year
    $this->actingAs($this->employee);
    expect($visible())->toBe([PersonalOverviewCard::class, WorkTrendCard::class]);
});

it('lays out 個人 the same for 個人のみ and for 全体 switched to 個人, with the user\'s own figures (bug.md 2026-11-09)', function () {
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業スペース']);
    $space->users()->attach($this->staff);
    foreach (['sales-entry' => '06. 売上入力', ReceiptResource::appCode() => '21. 売入金管理'] as $code => $name) {
        $app = new BusinessApp(['code' => $code, 'name' => $name]);
        $app->forceFill(['status' => AppStatus::Published])->save();
        $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
        $app->spaces()->attach($space);
    }
    $period = ['from' => '2026-10-01', 'to' => '2026-10-31'];
    // 売上合計 · 売上概要 | 売上の推移 · 最近表示した項目 | 休暇・勤怠予定 · 最近のアクティビティ
    $layout = [RevenueCard::class, SalesOverviewCard::class, SalesTrendCard::class, QuickActionsCard::class, AbsencesCard::class, RecentActivityCard::class];
    $cards = function (?string $scope): array {
        $page = new PortalPage;
        $page->filters = ['from' => '2026-10-01', 'to' => '2026-10-31', 'scope' => $scope];

        return $page->dashboardWidgets();
    };
    $spans = function (array $filters): void {
        Livewire::test(RevenueCard::class, ['pageFilters' => $filters])->assertSee('sfk-dash-span-4');
        Livewire::test(SalesOverviewCard::class, ['pageFilters' => $filters])->assertSee('sfk-dash-span-8');
        Livewire::test(SalesTrendCard::class, ['pageFilters' => $filters])->assertSee('sfk-dash-span-8');
        Livewire::test(QuickActionsCard::class, ['pageFilters' => $filters])->assertSee('sfk-dash-span-4');
        Livewire::test(AbsencesCard::class, ['pageFilters' => $filters])->assertSee('sfk-dash-span-6');
        Livewire::test(RecentActivityCard::class, ['pageFilters' => $filters])->assertSee(['sfk-dash-span-6', 'class="sfk-activity is-single"'], escape: false);
    };

    // 全体: everyone's cards; 個人: the 個人 layout with their own sales
    grantDashboards($this->staff, 'erp')->forceFill(['show_financial_analysis' => true, 'financial_scope' => User::FINANCE_OVERALL])->save();
    $this->staff->notifyNow(new SystemNotification(['title' => '06. 売上入力: 長3封筒', 'body' => '更新しました', 'format' => 'filament', 'actions' => []]), ['database']);
    $this->actingAs($this->staff);
    // 全体 (bug.md 2026-11-11): 売上合計 · 売上概要 · 入出金 | 売上の推移 50% · 上位の売上先 30% · 売れ筋 TOP3 20% |
    // 担当者別売上 · 作業者ランキング · 最近表示した項目 (a third each) | 休暇・勤怠予定 · 最近のアクティビティ (half each)
    expect($cards('overall'))->toBe([RevenueCard::class, SalesOverviewCard::class, CashFlowCard::class, SalesTrendCard::class, TopCustomersCard::class,
        TopProductsCard::class, StaffSalesCard::class, TopWorkersCard::class, QuickActionsCard::class, AbsencesCard::class, RecentActivityCard::class])
        ->and($cards('personal'))->toBe($layout);
    $overall = [...$period, 'scope' => 'overall'];
    Livewire::test(SalesOverviewCard::class, ['pageFilters' => $overall])->assertSee('sfk-dash-span-5');
    Livewire::test(SalesTrendCard::class, ['pageFilters' => $overall])->assertSee('sfk-dash-span-6');
    Livewire::test(TopCustomersCard::class, ['pageFilters' => $overall])->assertSee('sfk-dash-span-30p');
    Livewire::test(TopProductsCard::class, ['pageFilters' => $overall])->assertSee('sfk-dash-span-20p');
    foreach ([StaffSalesCard::class, TopWorkersCard::class, QuickActionsCard::class] as $third) {
        Livewire::test($third, ['pageFilters' => $overall])->assertSee('sfk-dash-span-4');
    }
    Livewire::test(AbsencesCard::class, ['pageFilters' => $overall])->assertSee('sfk-dash-span-6');
    Livewire::test(RecentActivityCard::class, ['pageFilters' => $overall])->assertSee(['sfk-dash-span-6', 'class="sfk-activity is-single"'], escape: false);
    $spans([...$period, 'scope' => 'personal']);
    Livewire::withQueryParams(['filters' => $period])->test(PortalPage::class)
        ->assertSee(['¥200,000', '上位の売上先', '入出金'])
        ->call('setScope', 'personal')
        ->assertSet('filters.scope', 'personal');
    Livewire::withQueryParams(['filters' => [...$period, 'scope' => 'personal']])->test(PortalPage::class)
        ->assertSeeInOrder(['売上合計', '¥150,000', '売上概要', '売上の推移', '2026年', 'sfk-quick-title', '休暇・勤怠予定', '最近のアクティビティ'], escape: false)
        ->assertDontSee(['¥200,000', '入出金', '上位の売上先', '担当者別売上', '担当した売上', '売れ筋 TOP3', '作業者ランキング']);

    // 個人のみ: the same, whatever the filters say
    $this->staff->forceFill(['financial_scope' => User::FINANCE_PERSONAL])->save();
    expect($cards('overall'))->toBe($layout)->and($cards(null))->toBe($layout);
    $spans([...$period, 'scope' => 'overall']);

    // without 財務分析を表示: their own layout (個人の概要 first) is kept
    $this->actingAs($this->employee);
    expect($cards('personal'))->toBe([PersonalOverviewCard::class, WorkTrendCard::class, QuickActionsCard::class, AbsencesCard::class, RecentActivityCard::class]);
});

it('draws the 個人のみ user\'s own sales per month of a year against the year before', function () {
    $this->actingAs($this->admin); // the sales the viewer may see
    expect(app(SalesDashboard::class)->forStaff($this->staff->id)->monthly(2026))
        ->toBe([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 80000, 10 => 150000, 11 => 0, 12 => 0])
        ->and(app(SalesDashboard::class)->monthly(2026)[10])->toBe(200000)
        ->and(app(SalesDashboard::class)->forStaff($this->staff->id)->monthly(2025)[10])->toBe(160000);

    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業スペース']);
    $space->users()->attach($this->staff);
    $app = new BusinessApp(['code' => 'sales-entry', 'name' => '06. 売上入力']);
    $app->forceFill(['status' => AppStatus::Published])->save();
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
    $app->spaces()->attach($space);
    grantDashboards($this->staff, 'erp')->forceFill(['show_financial_analysis' => true, 'financial_scope' => User::FINANCE_PERSONAL])->save();
    $this->actingAs($this->staff);

    // 2026 so far: ¥230,000 (the data stops on 10/20). 前年同期比 only over the days both years have data — 10/10〜10/20
    // (the data starts on 2025-10-10): ¥150,000 against ¥160,000, not against months of 2025 without any data
    Livewire::test(SalesTrendCard::class)
        ->assertSee(['売上の推移', '2026年', '2025年', '2026年の売上', '（1/1〜10/20）', '¥230,000', '前年同期比 ▼6.3%', '10/10〜10/20 ¥150,000', '（2025/10/10〜10/20） ¥160,000', '10月', '¥150,000（10/20まで）', '¥160,000'])
        ->assertDontSee('前年比 ▲')
        ->assertDontSee('¥200,000') // someone else's sales are not in it
        ->assertSee('<option value="2026" selected', escape: false)
        ->set('year', '2025')
        ->assertSee(['2025年の売上', '¥160,000', '2024年'])
        ->assertSee('<option value="2025" selected', escape: false)
        ->set('year', '1999')
        ->assertSee('2026年の売上');

    // months without sales data are not ¥0: before 2025-10-10 and after the last 売上日 (10/20) 「データなし」, after this
    // month nothing yet
    Livewire::test(SalesTrendCard::class)->assertSeeInOrder([
        '<th scope="row">9月</th>', '<td>¥80,000</td>', '<td>データなし</td>',
        '<th scope="row">11月</th>', '<td>データなし</td>', '<td>¥0</td>',
        '<th scope="row">12月</th>', '<td>—</td>', '<td>¥0</td>',
    ], escape: false);
});

it('counts the days with a 日報 as 出勤日数 and draws the employee\'s 勤務時間 and 収入 per month', function () {
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '製造スペース']);
    $space->users()->attach($this->employee);
    $app = new BusinessApp(['code' => 'daily-report', 'name' => '08. 日報入力']);
    $app->forceFill(['status' => AppStatus::Published])->save();
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
    $app->spaces()->attach($space);
    $this->employee->forceFill(['work_rate' => 1000, 'overtime_rate' => 1250])->save();

    // two reports on 10/05 count as one day; 10/06 another; 09/30 is in September
    DailyReport::query()->create(['worked_on' => '2026-10-05', 'user_id' => $this->employee->id, 'started_at' => '08:00', 'ended_at' => '12:00', 'break_time' => '00:00']);
    DailyReport::query()->create(['worked_on' => '2026-10-05', 'user_id' => $this->employee->id, 'started_at' => '13:00', 'ended_at' => '15:00', 'break_time' => '00:00']);
    DailyReport::query()->create(['worked_on' => '2026-10-06', 'user_id' => $this->employee->id, 'started_at' => '08:00', 'ended_at' => '10:00', 'break_time' => '00:00']);
    DailyReport::query()->create(['worked_on' => '2026-09-30', 'user_id' => $this->employee->id, 'started_at' => '08:00', 'ended_at' => '11:00', 'break_time' => '00:00']);
    DailyReport::query()->create(['worked_on' => '2026-10-07', 'user_id' => $this->other->id, 'started_at' => '08:00', 'ended_at' => '17:00', 'break_time' => '01:00']); // someone else

    $this->actingAs($this->employee);
    $figures = app(PersonalOverview::class)->for($this->employee, $this->period);
    expect($figures['work_days'])->toBe(2)
        ->and($figures['reports'])->toBe(3)
        ->and($figures['hours'])->toBe(8.0);

    $monthly = app(PersonalOverview::class)->monthly($this->employee, 2026);
    expect($monthly['hours'][9])->toBe(3.0)
        ->and($monthly['hours'][10])->toBe(8.0)
        ->and($monthly['income'][10])->toBe(8000)
        ->and($monthly['hours'][11])->toBe(0.0);

    Livewire::withQueryParams(['filters' => ['from' => '2026-10-01', 'to' => '2026-10-31']])->test(PortalPage::class)
        ->assertSeeInOrder(['個人の概要', '出勤日数', '2', '日', '勤務時間・収入の推移'], escape: false);

    Livewire::test(WorkTrendCard::class)
        ->assertSee(['勤務時間・収入の推移', '2026年の勤務時間', '11', '時間', '2026年の収入', '¥11,000', '8時間', '¥8,000'])
        ->set('year', '2025')
        ->assertSee(['2025年の勤務時間', '2025年の収入', '¥0']);
});

it('builds a 12-month chart on one axis with a round top, smooth lines that stay between their points, and no value after today', function () {
    expect(TrendChart::niceStep(0))->toBe(1.0)
        ->and(TrendChart::niceStep(37_500))->toBe(50_000.0)
        ->and(TrendChart::niceStep(19_000))->toBe(20_000.0)
        ->and(TrendChart::niceStep(2.2))->toBe(2.5)
        ->and(TrendChart::yenAxis(0))->toBe('0')
        ->and(TrendChart::yenAxis(5_000))->toBe('5,000')
        ->and(TrendChart::yenAxis(1_200_000))->toBe('120万')
        ->and(TrendChart::yenAxis(150_000_000))->toBe('1.5億');

    $values = [1 => 0, 2 => 150, 3 => 0, 4 => 0, 5 => 400, 6 => 380, 7 => 10, 8 => 0, 9 => 0, 10 => 0, 11 => null, 12 => null];
    $chart = TrendChart::build(
        [['key' => 'a', 'label' => 'A', 'color' => '#2a78d6', 'values' => $values]],
        fn (int|float $value): string => (string) $value,
        fn (int|float $value): string => $value.'円',
    );

    expect(array_column($chart['ticks'], 'label'))->toBe(['400', '300', '200', '100', '0'])
        ->and($chart['series'][0]['values'][1])->toBe('150円')
        ->and($chart['series'][0]['values'][10])->toBeNull()
        ->and($chart['series'][0]['points'][11])->toBeNull()
        ->and($chart['empty'])->toBeFalse();

    // every coordinate of the path (points and control points) stays inside the plot: never below 0 or above the top
    preg_match_all('/-?\d+(?:\.\d+)?/', $chart['series'][0]['line'], $numbers);
    $ys = array_values(array_filter($numbers[0], fn (int $index): bool => $index % 2 === 1, ARRAY_FILTER_USE_KEY));
    expect(min(array_map('floatval', $ys)))->toBeGreaterThanOrEqual(0.0)
        ->and(max(array_map('floatval', $ys)))->toBeLessThanOrEqual((float) TrendChart::HEIGHT);
    // the line ends at October (x = 9/11 of the width)
    expect($chart['series'][0]['line'])->toEndWith(sprintf('%.1f %.1f', 9 / 11 * TrendChart::WIDTH, (float) TrendChart::HEIGHT));
});

it('counts a monthly 売上目標 only for the days of the month the period covers (2026-11-11)', function () {
    $this->actingAs($this->admin);
    $today = new DateTimeImmutable('2026-11-15');
    $service = app(SalesDashboard::class);

    // the plan of October (¥400,000): the whole month, or 15 of its 31 days — not two months' worth for 30 days
    expect($service->target(Period::between('2026-10-01', '2026-10-31', $today)))->toBe(400000)
        ->and($service->target(Period::between('2026-10-17', '2026-11-15', $today)))->toBe((int) round(400000 * 15 / 31))
        ->and(Period::between('2026-10-17', '2026-11-15', $today)->monthShares())->toBe([[2026, 10, 15 / 31], [2026, 11, 15 / 30]]);
});

it('labels each comparison with its baseline and shows the period of each card (2026-11-11)', function () {
    $this->actingAs($this->admin);
    $filters = ['from' => '2026-10-01', 'to' => '2026-10-31'];

    // the last 売上日 is 10/20, more than SalesCoverage::STALE_DAYS ago: October has no data after it, so no percentage —
    // the baseline and how far the data goes instead
    Livewire::test(RevenueCard::class, ['pageFilters' => $filters])
        ->assertSee(['選択期間', '2026/10/01〜10/31', '前期間', '2026/08/31〜09/30', '¥80,000', '売上データは2026/10/20まで', '50% 達成', '残り ¥200,000'])
        ->assertDontSee('class="sfk-dash-trend', escape: false);
    Livewire::test(SalesOverviewCard::class, ['pageFilters' => $filters])
        ->assertSee(['前年同期', '（2025/10/01〜10/31）', '¥160,000', '前期間', '（2026/08/31〜09/30）', '1件', '売上データは2026/10/20まで'])
        ->assertDontSee(['前年同期比 ▲', '前年同期比 ▼', '前期間比 ▲', '前期間比 ▼']);

    // sales are entered again (a sale on 11/03): the data is current, October compares — with the difference in yen
    Sale::query()->create(['customer_id' => $this->beta->id, 'sold_on' => '2026-11-03', 'delivery_due_on' => '2026-11-03', 'amount' => 1000]);
    Livewire::test(RevenueCard::class, ['pageFilters' => $filters])
        ->assertSee(['前期間比 ▲150.0%', '差額', '+¥120,000', '前期間', '¥80,000'])
        ->assertDontSee('売上データは');
    // 前年同期: 2025-10-01 is before the first 売上日 (2025-10-10) — no baseline, no percentage
    Livewire::test(SalesOverviewCard::class, ['pageFilters' => $filters])
        ->assertSee(['前期間比 ▲300.0%', '（2025/10/01〜10/31）'])
        ->assertDontSee(['前年同期比 ▲', '前年同期比 ▼']);
    Livewire::test(SalesOverviewCard::class, ['pageFilters' => ['from' => '2026-10-10', 'to' => '2026-10-31']])
        ->assertSee('前年同期比 ▲25.0%'); // ¥200,000 against ¥160,000

    foreach ([CashFlowCard::class, TopCustomersCard::class, TopProductsCard::class, StaffSalesCard::class, TopWorkersCard::class] as $card) {
        Livewire::test($card, ['pageFilters' => $filters])->assertSee(['選択期間', '2026/10/01〜10/31']);
    }
    // the cards with periods of their own say which
    Livewire::test(SalesTrendCard::class)->assertSee(['暦年・月別', '2026年1月〜12月'])->assertDontSee('選択期間');
    Livewire::test(AbsencesCard::class)->assertSee(['今日・明日', '2026/11/05〜11/06'])->assertDontSee('選択期間');
});

it('offers 今年度 (May to today) next to 今年 on the date button', function () {
    $this->actingAs($this->admin);

    $this->travelTo(new DateTimeImmutable('2027-02-10 09:00'));
    Livewire::test(PortalPage::class)->assertSee('今年度')->call('setPeriod', 'fiscalYear')
        ->assertSet('filters.from', '2026-05-01')->assertSet('filters.to', '2027-02-10');
    $this->travelTo(new DateTimeImmutable('2026-11-11 09:00'));
    Livewire::test(PortalPage::class)->call('setPeriod', 'fiscalYear')->assertSet('filters.from', '2026-05-01');
    Livewire::test(PortalPage::class)->call('setPeriod', 'year')->assertSet('filters.from', '2026-01-01');

    // the date button names the period: 選択期間, and the preset it is
    Livewire::withQueryParams(['filters' => ['from' => '2026-05-01', 'to' => '2026-11-11']])->test(PortalPage::class)
        ->assertSeeInOrder(['選択期間', '今年度', '2026/05/01 〜 2026/11/11']);
    Livewire::withQueryParams(['filters' => ['from' => '2026-10-02', 'to' => '2026-10-09']])->test(PortalPage::class)
        ->assertSeeInOrder(['選択期間', '2026/10/02 〜 2026/10/09'])
        ->assertDontSee('class="is-active"', escape: false);
    $this->travelBack();
});

it('knows the days the sales data covers: no data is not ¥0, and comparisons reaching past it have no percentage (2026-11-14)', function () {
    $today = new DateTimeImmutable('2026-11-14');
    // the legacy import: 2025-05-01 to 2026-10-16
    $import = SalesCoverage::of('2025-05-01', '2026-10-16', $today);
    expect($import->isStale())->toBeTrue()
        ->and($import->end()?->format('Y-m-d'))->toBe('2026-10-16')
        ->and($import->covers(Period::between('2026-09-01', '2026-09-30', $today)))->toBeTrue()
        ->and($import->covers(Period::between('2026-10-13', '2026-11-11', $today)))->toBeFalse()
        ->and($import->note(Period::between('2026-10-13', '2026-11-11', $today)))->toBe('売上データは2026/10/16まで')
        ->and($import->note(Period::between('2025-01-01', '2026-11-14', $today)))->toBe('売上データは2025/05/01〜2026/10/16')
        ->and($import->note(Period::between('2025-01-01', '2025-10-31', $today)))->toBe('売上データは2025/05/01から')
        ->and($import->month(2025, 4))->toBe(['none', null])
        ->and($import->month(2025, 5))->toBe(['full', null])
        ->and($import->month(2026, 10))->toBe(['partial', '10/16まで'])
        ->and($import->month(2026, 11))->toBe(['none', null]);

    // sales entered up to yesterday: current, the month so far is incomplete, a period into the future too
    $live = SalesCoverage::of('2025-05-01', '2026-11-13', $today);
    expect($live->isStale())->toBeFalse()
        ->and($live->end()?->format('Y-m-d'))->toBe('2026-11-14')
        ->and($live->covers(Period::between('2026-10-16', '2026-11-14', $today)))->toBeTrue()
        ->and($live->month(2026, 11))->toBe(['partial', '11/14時点'])
        ->and($live->note(Period::between('2026-11-01', '2026-11-30', $today)))->toBe('2026/11/14時点');

    expect(SalesCoverage::of(null, null, $today)->covers(Period::between('2026-11-01', '2026-11-14', $today)))->toBeFalse()
        ->and(DashboardCard::change(10, 0))->toBeNull()
        ->and(DashboardCard::change(10, -5))->toBeNull()
        ->and(DashboardCard::change(1, 100))->toBe(-99.0)
        ->and(DashboardCard::change(150, 100, valid: false))->toBeNull();
});

it('never bridges months without data and draws an incomplete month dotted, with a ring (2026-11-14)', function () {
    $chart = TrendChart::build([[
        'key' => 'a', 'label' => 'A', 'color' => '#2a78d6',
        'values' => [1 => null, 2 => null, 3 => 100, 4 => 200, 5 => null, 6 => 300, 7 => 400, 8 => 50, 9 => null, 10 => null, 11 => null, 12 => null],
        'notes' => [8 => '8/16まで'], 'missing' => [1, 2, 5],
    ]], fn (int|float $value): string => (string) $value, fn (int|float $value): string => $value.'円');
    $line = $chart['series'][0];

    // two runs (Mar–Apr, Jun–Aug): nothing drawn across May; Jul → Aug (incomplete) is the dotted part
    expect(substr_count($line['line'], 'M '))->toBe(2)
        ->and(substr_count($line['partialLine'], 'M '))->toBe(1)
        ->and($line['line'])->toStartWith(sprintf('M %.1f', 2 / 11 * TrendChart::WIDTH))
        ->and(substr_count($line['area'], 'Z'))->toBe(2)
        ->and($line['partialLine'])->toStartWith(sprintf('M %.1f', 6 / 11 * TrendChart::WIDTH))
        ->and($line['points'][7]['partial'])->toBeTrue()
        ->and($line['values'][7])->toBe('50円（8/16まで）')
        ->and($line['values'][4])->toBe('データなし')
        ->and($line['values'][0])->toBe('データなし')
        ->and($line['values'][9])->toBeNull();

    $this->actingAs($this->admin);
    Livewire::test(SalesTrendCard::class)->assertSee(['sfk-trend-ring', 'stroke-dasharray="1 5"'], escape: false);
});

it('adds up the 売上目標 of every month of a period in one query, across business years', function () {
    $this->actingAs($this->admin);
    SalesPlan::query()->create(['customer_id' => $this->alpha->id, 'planned_on' => '2025-05-01', 'plan_m04' => 300000]);
    $today = new DateTimeImmutable('2026-11-15');

    DB::enableQueryLog();
    // April 2026 is in the business year 2025, May–October in 2026 (only October has a plan)
    expect(app(SalesDashboard::class)->target(Period::between('2026-04-01', '2026-10-31', $today)))->toBe(700000);
    expect(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'sales_plans'))->count())->toBe(1);
    DB::disableQueryLog();
});

it('names a comparison with the year before 前年度同期 when the period is the business year so far', function () {
    $this->actingAs($this->admin);

    Livewire::test(SalesOverviewCard::class, ['pageFilters' => ['from' => '2026-05-01', 'to' => '2026-11-05']])
        ->assertSeeInOrder(['前年度同期', '（2025/05/01〜11/05）']);
    Livewire::test(SalesOverviewCard::class, ['pageFilters' => ['from' => '2026-05-02', 'to' => '2026-11-05']])
        ->assertSeeInOrder(['前年同期', '（2025/05/02〜11/05）'])
        ->assertDontSee('前年度同期');
});

it('keeps the tooltips short: a line for a figure, the two periods and the difference for a comparison (2026-11-14)', function () {
    $today = new DateTimeImmutable('2026-11-05');
    $now = Period::between('2026-10-01', '2026-10-31', $today);

    // a comparison: both periods with their values and the difference — no description, no formula
    $html = DashboardTips::comparison('前期間', $now, $now->previous(), 200000, 80000, true)->html();
    expect($html)->toContain('<p class="sfk-tip-title">前期間比</p>')
        ->toContain('当期（2026/10/01〜10/31）</dt><dd>¥200,000')
        ->toContain('前期間（2026/08/31〜09/30）</dt><dd>¥80,000')
        ->toContain('増減額</dt><dd class="is-up">+¥120,000')
        ->not->toContain('計算方法')
        ->not->toContain('sfk-tip-text');
    expect(DashboardTips::comparison('前期間', $now, $now->previous(), 5, 7, false)->html())->toContain('増減数</dt><dd class="is-down">−2件');
    expect(DashboardTips::comparison('前期間', $now, $now->previous(), 1000, 100000, true)->html())->toContain('<dd class="is-down">−¥99,000');

    // a figure: its title and one line
    expect(DashboardTips::status('overdue', '期限超過')->html())
        ->toBe('<div class="sfk-tip"><p class="sfk-tip-title">期限超過</p><p class="sfk-tip-text">請求済み・未入金で、回収予定日を過ぎた売上</p></div>')
        ->and(DashboardTips::salesAmount('売上合計', true)->html())->toContain('自分が最初の担当者の売上')
        ->and(DashboardTips::ranking('株式会社アルファ', 75)->html())->toBe('<div class="sfk-tip"><p class="sfk-tip-title">株式会社アルファ</p><dl class="sfk-tip-rows"><div><dt>構成比</dt><dd>75%</dd></div></dl></div>');

    // escaped; the trigger expression goes through the shared settings
    expect(Tip::text('<b>&')->html())->toBe('<div class="sfk-tip"><p class="sfk-tip-text">&lt;b&gt;&amp;</p></div>')
        ->and(Tip::text('x')->x())->toStartWith('sfkTip(')
        ->and(Tip::percent(0.0))->toBe('±0.0%')
        ->and(Tip::percent(2500))->toBe('▲1,000%超')
        ->and(Tip::signedYen(-1200))->toBe('−¥1,200')
        ->and(DashboardTips::share(1, 3))->toBe(33)
        ->and(DashboardTips::share(1, 0))->toBeNull();

    // the cards carry their tooltips on the figures, the chips and the ranking rows — no browser title
    $this->actingAs($this->admin);
    Sale::query()->create(['customer_id' => $this->beta->id, 'sold_on' => '2026-11-03', 'delivery_due_on' => '2026-11-03', 'amount' => 1000]);
    $filters = ['pageFilters' => ['from' => '2026-10-01', 'to' => '2026-10-31']];
    foreach ([RevenueCard::class, SalesOverviewCard::class, CashFlowCard::class, TopCustomersCard::class, TopProductsCard::class, StaffSalesCard::class] as $card) {
        Livewire::test($card, $filters)->assertSee('x-tooltip="sfkTip(', escape: false)->assertDontSee(' title="', escape: false);
    }
    Livewire::test(RevenueCard::class, $filters)->assertSee(['class="sfk-tip-term" tabindex="0"', 'class="sfk-dash-trend is-up"', 'data-sfk-tip x-tooltip="sfkTip(', '当期（2026'], escape: false);
    Livewire::test(TopCustomersCard::class, $filters)->assertSee(['<li tabindex="0" data-sfk-tip', '構成比'], escape: false);
    // what the card already shows has no tooltip: 売上の推移's total and 前年同期比 carry their days and amounts
    Livewire::test(SalesTrendCard::class)->assertDontSee('sfk-tip-term', escape: false);
});

it('gives every screen the same tooltip: Filament tooltips and browser titles take the light look (2026-11-14)', function () {
    $css = view('core::filament.ui')->render();

    expect($css)->toContain('.tippy-box[data-state],')
        ->toContain('window.sfkTipTitle')
        ->toContain("document.addEventListener('pointerover', adopt, true);")
        ->not->toContain('near-black box');
});

it('compares each month of 売上の推移 with the year before in its tooltip, never an incomplete month', function () {
    $chart = TrendChart::build([
        ['key' => 'now', 'label' => '2026年', 'color' => '#2a78d6', 'values' => [1 => 300, 2 => 50, 3 => 100, 4 => null] + array_fill(5, 8, null), 'notes' => [3 => '3/16まで']],
        ['key' => 'before', 'label' => '2025年', 'color' => '#159e6e', 'values' => [1 => 200, 2 => 0, 3 => 100, 4 => 80] + array_fill(5, 8, null)],
    ], fn (int|float $value): string => (string) $value, fn (int|float $value): string => '¥'.$value, fn (int|float $value): string => Tip::signedYen((int) $value));

    expect($chart['compare'][0])->toBe(['difference' => '+¥100', 'change' => '▲50.0%', 'tone' => 'up'])
        ->and($chart['compare'][1])->toBe(['difference' => '+¥50', 'change' => null, 'tone' => 'up']) // no base: no rate
        ->and($chart['compare'][2])->toBe(['note' => '月の途中のため比較なし'])
        ->and($chart['compare'][3])->toBeNull();

    // a chart without a comparison (勤務時間・収入の推移) has none
    expect(TrendChart::build([['key' => 'a', 'label' => 'A', 'color' => '#000', 'values' => [1 => 1]]], fn ($v): string => (string) $v, fn ($v): string => (string) $v)['compare'])
        ->toBe(array_fill(0, 12, null));
});

it('lists only the 10 latest activities, newest first (2026-11-14)', function () {
    $this->actingAs($this->admin);
    foreach (range(1, 12) as $day) {
        $this->travelTo(CarbonImmutable::parse(sprintf('2026-11-%02d 09:00', $day)));
        $this->admin->notifyNow(new SystemNotification(['title' => sprintf('お知らせ%02d', $day), 'body' => null, 'format' => 'filament', 'actions' => []]), ['database']);
    }
    $this->travelTo(CarbonImmutable::parse('2026-11-13 09:00'));

    expect(RecentActivityCard::LIMIT)->toBe(10);
    Livewire::test(RecentActivityCard::class)
        ->assertSeeInOrder(['お知らせ12', 'お知らせ11', 'お知らせ03'])
        ->assertDontSee(['お知らせ02', 'お知らせ01']);
});
