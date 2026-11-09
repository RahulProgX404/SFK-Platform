<?php

/*
 * 分析 (legacy SalesAnalysis.bundle.js and DailyReportAnalysis.bundle.js), two pages, not apps (yet):
 * - 売上分析 (/erp/analysis/sales, 財務分析を表示): 06. 売上入力 per first 担当者, business year (May–April) and month, the
 *   売上先 of a month, 予想 / 仕掛 typed in;
 * - 日報分析 (/erp/analysis/daily-reports, 財務分析の範囲 全体 with 08. 日報入力): the 作業者 ranked by 枚数単価 + 型替え単価 (cut to
 *   whole yen per month) with their hours (作業時間 + 残業時間).
 */

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Analytics\Application\DailyReportRanking;
use Modules\Analytics\Application\StaffSalesAnalysis;
use Modules\Analytics\Filament\Pages\DailyReportAnalysis;
use Modules\Analytics\Filament\Pages\SalesAnalysis;
use Modules\Analytics\Models\SalesForecast;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;
use Modules\MasterData\Models\Partner;
use Modules\Production\Models\DailyReport;
use Modules\Sales\Models\Sale;

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->travelTo(CarbonImmutable::parse('2026-10-21 10:00'));
    $this->admin = User::factory()->superAdmin()->create(['name' => '管理者']);
    $this->first = User::factory()->create(['name' => '営業 一郎', 'sales_staff_code' => '05']);
    $this->second = User::factory()->create(['name' => '営業 二郎', 'sales_staff_code' => '06']);

    $alpha = Partner::query()->create(['name' => '株式会社アルファ', 'short_name' => 'アルファ', 'is_customer' => true, 'code' => 'C001']);
    $beta = Partner::query()->create(['name' => '株式会社ベータ', 'short_name' => 'ベータ', 'is_customer' => true, 'code' => 'C002']);
    $sale = function (Partner $customer, string $soldOn, int $amount, array $staff, string $product = '長3封筒'): void {
        $sale = Sale::query()->create(['customer_id' => $customer->id, 'sold_on' => $soldOn, 'delivery_due_on' => $soldOn, 'product_name' => $product, 'amount' => $amount]);
        foreach ($staff as $user) {
            $sale->assignees()->attach($user->id);
        }
    };
    // the first 担当者 counts (legacy): 一郎 for the shared sale
    $sale($alpha, '2026-05-10', 100000, [$this->first, $this->second], '角2封筒');
    $sale($beta, '2026-05-20', 50000, [$this->second]);
    $sale($alpha, '2026-04-15', 70000, [$this->first]); // business year 2025, 4月
    $sale($beta, '2025-10-01', 30000, [$this->first]);
});

it('sums the sales per first 担当者, business year and month', function () {
    $this->actingAs($this->admin);
    $service = app(StaffSalesAnalysis::class);

    expect($service->years())->toBe([2025, 2026])
        ->and($service->actuals([2025, 2026]))->toEqual([
            $this->first->id => [2026 => [5 => 100000], 2025 => [4 => 70000, 10 => 30000]],
            $this->second->id => [2026 => [5 => 50000]],
        ])
        ->and($service->staff([2025, 2026], $service->actuals([2025, 2026])))->toBe([$this->first->id => '営業 一郎', $this->second->id => '営業 二郎']);

    $month = $service->customers(2026, 5);
    expect(array_column($month, 'name'))->toBe(['株式会社アルファ', '株式会社ベータ'])
        ->and(array_column($month, 'amount'))->toBe([100000, 50000])
        ->and($month[0]['lines'])->toBe([['date' => '2026/05/10', 'product' => '角2封筒', 'amount' => 100000]])
        ->and(array_column($service->customers(2026, 5, $this->second->id), 'name'))->toBe(['株式会社ベータ']);
});

it('shows 売上分析 with the totals, the 担当者 tables and the 売上先 of a month', function () {
    $this->actingAs($this->admin);

    Livewire::test(SalesAnalysis::class)
        ->assertOk()
        ->assertSet('years', [2025, 2026])
        ->assertSee(['売上分析', '今年度の売上', '¥150,000', '年度別 合計', '担当者別', '営業 一郎', '予想', '仕掛'])
        ->call('showDetail', null, 2026, 5)
        ->assertSee(['売上先別 明細', '全担当者・2026年度 5月（2026年5月）', 'C001', '株式会社アルファ', '¥100,000', 'C002', '株式会社ベータ', '¥50,000', '角2封筒'])
        ->call('closeDetail')
        ->assertSet('detail', null)
        ->assertDontSee('全担当者・2026年度 5月')
        ->set('mode', 'yearly')
        ->assertDontSee('担当者別');
});

it('keeps 予想 and 仕掛 of the current year for administrators and the 担当者 themselves', function () {
    $this->actingAs($this->admin);
    Livewire::test(SalesAnalysis::class)
        ->call('saveForecast', $this->first->id, 'forecast', 6, '1200000')
        ->call('saveForecast', $this->first->id, 'wip', 11, 80000)
        ->assertSee(['¥1,200,000', '¥80,000']);

    $forecast = SalesForecast::query()->sole();
    expect($forecast->fiscal_year)->toBe(2026)
        ->and($forecast->months('forecast')[6])->toBe(1200000)
        ->and($forecast->months('wip')[11])->toBe(80000);

    // an employee: only their own rows
    $this->actingAs($this->second);
    expect((new SalesAnalysis)->canEditForecast($this->second->id))->toBeTrue()
        ->and((new SalesAnalysis)->canEditForecast($this->first->id))->toBeFalse();
    $this->actingAs($this->admin);
    expect((new SalesAnalysis)->canEditForecast($this->first->id))->toBeTrue();
});

it('ranks the 作業者 by 枚数単価 + 型替え単価 of their daily reports, with their hours', function () {
    $worker = User::factory()->create(['name' => '製造 太郎']);
    $other = User::factory()->create(['name' => '製造 花子']);
    $report = fn (User $user, string $day, array $figures) => DailyReport::query()->create(['worked_on' => $day, 'user_id' => $user->id, ...$figures]);
    // 1,000 × 1.5 + 300 × 2 = 2,100 and 333 × 0.5 = 166.5: 2,266 for June (cut per month)
    // 作業時間 08:00–12:00 (4h) + 残業 17:00–18:30 (1.5h), then 13:00–15:00 (2h): 7.5 hours in June
    $report($worker, '2026-06-01', ['produced' => '1000', 'processing_rate' => '1.5', 'changeover_rate' => '300', 'changeover_count' => '2',
        'started_at' => '08:00', 'ended_at' => '12:00', 'break_time' => '00:00', 'overtime_started_at' => '17:00', 'overtime_ended_at' => '18:30', 'overtime_break' => '00:00']);
    $report($worker, '2026-06-15', ['produced' => '333', 'processing_rate' => '0.5', 'started_at' => '13:00', 'ended_at' => '15:00', 'break_time' => '00:00']);
    $report($other, '2026-07-01', ['produced' => '2000', 'processing_rate' => '2']);
    $report($other, '2026-04-30', ['produced' => '100', 'processing_rate' => '1']); // business year 2025
    $this->actingAs($this->admin);

    $ranking = app(DailyReportRanking::class)->ranking(2026);
    expect(array_column($ranking, 'name'))->toBe(['製造 花子', '製造 太郎'])
        ->and(array_column($ranking, 'rank'))->toBe([1, 2])
        ->and($ranking[1]['months'][6])->toBe(2266)
        ->and(array_column($ranking, 'total'))->toBe([4000, 2266])
        ->and($ranking[1]['hours'][6])->toBe(7.5)
        ->and($ranking[1]['total_hours'])->toBe(7.5)
        ->and(app(DailyReportRanking::class)->ranking(2025)[0]['total'])->toBe(100)
        ->and(app(DailyReportRanking::class)->top('2026-06-01', '2026-07-01'))->toBe([
            ['rank' => 1, 'user_id' => $worker->id, 'name' => '製造 太郎', 'total' => 2266, 'total_hours' => 7.5, 'reports' => 2],
        ]);

    Livewire::test(DailyReportAnalysis::class)
        ->assertSeeInOrder(['総作業時間', '7.5時間', '年度別金額ランキング', '2026年度（2026年5月〜2027年4月）', '1', '製造 花子', '¥4,000', '2', '製造 太郎', '¥2,266', '7.5時間', '合計', '¥6,266']);
});

it('opens 売上分析 with 財務分析を表示, 日報分析 only with 財務分析の範囲 全体, and neither is an app', function () {
    $this->actingAs($this->admin);
    $this->get('/erp/analysis/sales')->assertOk()->assertSee(['売上分析', '日報分析', '/erp/analysis/daily-reports"'], false);
    $this->get('/erp/analysis/daily-reports')->assertOk()->assertSee(['総作業時間', '/erp/analysis/sales"'], false);

    // an administrator (both apps open to everyone here)
    foreach (['sales-entry' => '06. 売上入力', 'daily-report' => '08. 日報入力'] as $code => $name) {
        $app = new BusinessApp(['code' => $code, 'name' => $name]);
        $app->forceFill(['status' => AppStatus::Published])->save();
        $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
    }
    $admin = grantDashboards(User::factory()->admin()->create(), 'erp');
    $this->actingAs($admin);

    // without 財務分析を表示: neither
    expect($admin->seesFinancialAnalysis())->toBeFalse();
    $this->get('/erp/analysis/sales')->assertForbidden();
    $this->get('/erp/analysis/daily-reports')->assertForbidden();

    // 財務分析の範囲 全体: both, each with the tab of the other
    $admin->forceFill(['show_financial_analysis' => true, 'financial_scope' => User::FINANCE_OVERALL])->save();
    expect(SalesAnalysis::canAccess())->toBeTrue()->and(DailyReportAnalysis::canAccess())->toBeTrue();
    $this->get('/erp/analysis/sales')->assertOk()->assertSee('/erp/analysis/daily-reports"', false);
    $this->get('/erp/analysis/daily-reports')->assertOk()->assertSee('/erp/analysis/sales"', false);

    // 個人のみ: 売上分析 only — no tabs (日報分析 is not one of them) (user, 2026-11-09)
    $admin->forceFill(['financial_scope' => User::FINANCE_PERSONAL])->save();
    expect(SalesAnalysis::canAccess())->toBeTrue()->and(DailyReportAnalysis::canAccess())->toBeFalse();
    $this->get('/erp/analysis/sales')->assertOk()->assertDontSee(['class="sfk-app-tabs"', '/erp/analysis/daily-reports"', '>日報分析</a>'], false);
    $this->get('/erp/analysis/daily-reports')->assertForbidden();

    // an employee without 06. 売上入力 / 08. 日報入力 gets into neither
    $this->actingAs($this->first);
    expect(SalesAnalysis::canAccess())->toBeFalse()->and(DailyReportAnalysis::canAccess())->toBeFalse();
    $this->get('/erp/analysis/sales')->assertForbidden();
    $this->get('/erp/analysis/daily-reports')->assertForbidden();

    expect(SalesAnalysis::shouldRegisterNavigation())->toBeFalse()
        ->and(DailyReportAnalysis::shouldRegisterNavigation())->toBeFalse()
        ->and((new AppDiscovery([base_path('modules')]))->definitions())->not->toHaveKey('analysis');
});

it('shows a 個人のみ user only their own sales, 予想 and 売上先 on 売上分析', function () {
    $this->actingAs($this->admin);
    $mine = app(StaffSalesAnalysis::class)->forStaff($this->second->id);
    SalesForecast::query()->create(['user_id' => $this->first->id, 'fiscal_year' => 2026, 'forecast_m05' => 900000]);
    SalesForecast::query()->create(['user_id' => $this->second->id, 'fiscal_year' => 2026, 'forecast_m05' => 40000]);
    expect($mine->actuals([2025, 2026]))->toEqual([$this->second->id => [2026 => [5 => 50000]]])
        ->and(array_keys($mine->forecasts(2026)))->toBe([$this->second->id])
        ->and(array_column($mine->customers(2026, 5), 'name'))->toBe(['株式会社ベータ'])
        ->and($mine->customers(2026, 5, $this->first->id))->toBe([]);

    $app = new BusinessApp(['code' => 'sales-entry', 'name' => '06. 売上入力']);
    $app->forceFill(['status' => AppStatus::Published])->save();
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業スペース']);
    $app->spaces()->attach($space);
    $space->users()->attach($this->second);
    grantDashboards($this->second, 'erp')->forceFill(['show_financial_analysis' => true, 'financial_scope' => User::FINANCE_PERSONAL])->save();
    $this->actingAs($this->second);

    Livewire::test(SalesAnalysis::class)
        ->assertSee(['営業 二郎', '¥50,000', '¥40,000'])
        ->assertDontSee(['営業 一郎', '¥100,000', '¥900,000']);
    expect((new SalesAnalysis)->canEditForecast($this->second->id))->toBeTrue()
        ->and((new SalesAnalysis)->canEditForecast($this->first->id))->toBeFalse();
});
