<?php

/*
 * The screens of 22 過去の売上データ and 25 販売先別売上計画 (SYSTEM_BRAIN APP_22 / APP_25): business years run May–April,
 * 年間合計 sums the months, the legacy graphs of 22 are views, and 25 takes its actuals from 06. 売上入力 with
 * 計画比進捗率 = ROUNDUP(年間合計 / 計画値 × 100, 1).
 */

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Analytics\Filament\Resources\PastSales\Pages\CreatePastSale;
use Modules\Analytics\Filament\Resources\PastSales\Pages\ListPastSales;
use Modules\Analytics\Filament\Resources\PastSales\Pages\ViewPastSale;
use Modules\Analytics\Filament\Resources\SalesPlans\Pages\ListSalesPlans;
use Modules\Analytics\Filament\Resources\SalesPlans\Pages\ViewSalesPlan;
use Modules\Analytics\Models\PastSale;
use Modules\Analytics\Models\SalesPlan;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Models\Sale;

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->travelTo(CarbonImmutable::parse('2026-10-21 10:00'));
    $this->admin = User::factory()->superAdmin()->create(['name' => '管理者']);
});

it('works out the business year May–April, the era and the progress rate', function () {
    $months = FiscalYear::months(new DateTimeImmutable('2026-03-15'));

    expect(array_key_first($months))->toBe('m05')
        ->and($months['m05']->key())->toBe('2025-05')
        ->and($months['m04']->key())->toBe('2026-04')
        ->and(FiscalYear::era(new DateTimeImmutable('2019-04-30')))->toBe('平成31年')
        ->and(FiscalYear::era(new DateTimeImmutable('2019-05-01')))->toBe('令和1年')
        ->and(FiscalYear::progress(1234, 1000))->toBe('123.4')
        ->and(FiscalYear::progress(1001, 3000))->toBe('33.4') // 33.366… rounds up
        ->and(FiscalYear::progress(500, null))->toBeNull();
});

it('keeps past sales with 年間合計 and draws the legacy graphs', function () {
    expect((new AppDiscovery([base_path('modules')]))->definitions())->toHaveKey('past-sales', '22. 過去の売上データ')->toHaveKey('sales-plan', '25. 販売先別売上計画');
    $this->actingAs($this->admin);

    Livewire::test(CreatePastSale::class)
        ->fillForm(['salesperson' => '営業 一郎', 'fiscal_month_start' => '2024-04-01', 'm05' => '1000000', 'm06' => '500000', 'm04' => '250000'])
        ->call('create')
        ->assertHasNoFormErrors();
    $sale = PastSale::query()->sole();
    PastSale::query()->create(['salesperson' => '営業 一郎', 'fiscal_month_start' => '2025-04-01', 'm05' => 900000]);
    PastSale::query()->create(['salesperson' => '営業 二郎', 'fiscal_month_start' => '2025-04-01', 'm07' => 300000]);
    expect($sale->total)->toBe(1750000)->and($sale->flag)->toBe('正常');

    Livewire::test(ViewPastSale::class, ['record' => $sale->getRouteKey()])
        ->assertSeeInOrder(['担当者', '営業 一郎', '異常値フラグ', '正常', '決算年度', '2024年', '小計', '¥1,750,000', '年間合計', '¥1,750,000', '5月', '¥1,000,000']);

    Livewire::test(ListPastSales::class)->assertSee('売上一覧')->assertCanSeeTableRecords([$sale]);
    // the app is not in the registry here: its code-defined views are used as they are

    Livewire::withQueryParams(['view' => 'annual'])->test(ListPastSales::class)
        ->assertSeeInOrder(['営業 一郎', '2024年', '¥1,750,000', '2025年', '¥900,000', '営業 二郎', '2025年', '¥300,000']);
    Livewire::withQueryParams(['view' => 'range'])->test(ListPastSales::class)
        ->assertSee(['営業 一郎', '最大 ¥1,750,000 / 最小 ¥900,000']);
});

it('takes the actuals of a sales plan from the sales of its business year', function () {
    $customer = Partner::query()->create(['name' => '株式会社計画先', 'short_name' => '計画', 'is_customer' => true, 'code' => 'C900', 'partner_group' => 'DNP']);
    $plan = SalesPlan::query()->create(['customer_id' => $customer->id, 'planned_on' => '2026-05-01', 'plan_total' => 30000, 'plan_m05' => 10000]);
    foreach ([['2026-05-10', 12000], ['2026-05-20', 3000], ['2026-07-01', 8000], ['2026-04-30', 99999]] as [$soldOn, $amount]) {
        Sale::query()->create(['customer_id' => $customer->id, 'sold_on' => $soldOn, 'delivery_due_on' => $soldOn, 'product_name' => '長3封筒', 'amount' => $amount]);
    }
    $this->actingAs($this->admin);

    Livewire::test(ListSalesPlans::class)
        ->assertSee('販売先別売上計画')
        ->assertCanSeeTableRecords([$plan])
        ->callAction('fillActuals')
        ->assertNotified('売上実績を反映しました（1件）');

    $plan->refresh();
    // May 15,000 + July 8,000 (April 2026 belongs to the year before)
    expect($plan->getAttribute('actual_m05'))->toBe(15000)
        ->and($plan->getAttribute('actual_m07'))->toBe(8000)
        ->and($plan->actual_total)->toBe(23000)
        ->and($plan->progress())->toBe('76.7');

    Livewire::test(ViewSalesPlan::class, ['record' => $plan->getRouteKey()])
        ->assertSeeInOrder(['得意先コード', 'C900', '得意先名', '株式会社計画先', 'グループ名', 'DNP', '年', '2026年05月', '計画値', '¥30,000', '年間合計', '¥23,000',
            '計画比進捗率', '76.7%', '▼ 計画値', '¥10,000', '▼ 実績', '¥15,000']);
});
