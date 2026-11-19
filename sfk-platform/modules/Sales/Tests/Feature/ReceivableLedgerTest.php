<?php

/*
 * 23. 売掛管理 / 23-2. 売掛管理(締日別) against the legacy defects (docs/receivables.md; SYSTEM_BRAIN BR-AR-01..07, D-23-2):
 * the real case of customer 629 (喜光堂印刷, 20日締, 翌月末日回収) — a payment before its invoice, an overpayment, months
 * without activity and a credit the legacy system dropped — plus back-dating, rebuilding, zero balances, the single
 * opening-balance function, the 基準月 of the lists and the period rules.
 */

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Models\AppListView;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Application\Receivables\InvoiceBalances;
use Modules\Sales\Application\Receivables\ReceivableLedger;
use Modules\Sales\Application\SalesActionNotAllowed;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Domain\Receivables\ReceivableGap;
use Modules\Sales\Filament\Resources\Receivables\CycleReceivableResource;
use Modules\Sales\Filament\Resources\Receivables\Pages\ListCycleReceivables;
use Modules\Sales\Filament\Resources\Receivables\Pages\ListReceivables;
use Modules\Sales\Filament\Resources\Receivables\ReceivableResource;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\Receipt;
use Modules\Sales\Models\ReceivableMonth;
use Modules\Sales\Models\Sale;

/** 喜光堂印刷 (customer 629): 20日締, 翌月末日回収, 四捨五入. */
function arKikodo(): Partner
{
    $partner = (new Partner)->forceFill([
        'id' => 629, 'name' => '喜光堂印刷', 'short_name' => '喜光堂', 'is_customer' => true, 'closing_day' => '20日', 'rounding' => '四捨五入',
        'collection_month' => '翌月', 'collection_day' => '末日', 'postal_code' => '530-0001', 'address' => '大阪市北区1-1',
    ]);
    $partner->save();

    return $partner;
}

function arCustomer(string $name): Partner
{
    return Partner::query()->create(['name' => $name, 'short_name' => mb_substr($name, 0, 4), 'is_customer' => true, 'closing_day' => '20日', 'rounding' => '四捨五入']);
}

function arSale(Partner $customer, string $soldOn, int $amount, string $taxRate = '10%'): Sale
{
    return Sale::query()->create([
        'customer_id' => $customer->id, 'sold_on' => $soldOn, 'delivery_due_on' => $soldOn, 'product_name' => '化粧箱',
        'quantity' => '1', 'unit_price' => (string) $amount, 'amount' => $amount, 'tax_rate' => $taxRate,
    ]);
}

/**
 * The real case: 2026-07-03 2,000 (手形) before the invoice, 2026-07-20 invoice #0001617 of 914,419 incl. tax (831,290 +
 * 10 % 83,129), 2026-09-09 914,419 (手形); no sales in August – October.
 *
 * @return array{customer: Partner, invoice: Invoice, receipt: Receipt}
 */
function arRealCase(): array
{
    $customer = arKikodo();
    arSale($customer, '2026-07-15', 831290);
    $invoice = Invoice::query()->create([
        'number' => '0001617', 'customer_id' => $customer->id, 'invoiced_on' => '2026-07-20', 'closing_day' => '20日', 'subtotal' => 831290, 'tax_total' => 83129,
    ]);
    $receipt = Receipt::query()->create(['invoice_id' => $invoice->id]);
    $receipt->lines()->create(['received_on' => '2026-07-03', 'amount' => 2000, 'method' => '手形']);
    $receipt->lines()->create(['received_on' => '2026-09-09', 'amount' => 914419, 'method' => '手形']);

    return ['customer' => $customer, 'invoice' => $invoice, 'receipt' => $receipt->fresh()];
}

/** @return array<string, array{opening: int, payments: int, sales: int, closing: int}> YYYY-MM => the figures of the spec table */
function arLedger(Partner $customer, ReceivableBasis $basis = ReceivableBasis::Calendar): array
{
    return ReceivableMonth::query()->ofBasis($basis)->where('customer_id', $customer->id)->orderBy('year_month')->get()
        ->mapWithKeys(fn (ReceivableMonth $month): array => [$month->year_month => [
            'opening' => $month->previous_balance, 'payments' => $month->received, 'sales' => $month->month_total, 'closing' => $month->closing_balance,
        ]])
        ->all();
}

/** @return array<string, array<string, mixed>> every stored column of the customer's months but the timestamps */
function arStoredRows(Partner $customer): array
{
    return ReceivableMonth::query()->where('customer_id', $customer->id)->orderBy('basis')->orderBy('year_month')->get()
        ->mapWithKeys(fn (ReceivableMonth $month): array => [$month->basis->value.' '.$month->year_month => $month->only([
            'customer_id', 'month_start', 'closing_day', ...ReceivableMonth::FIGURES,
        ])])
        ->map(fn (array $row): array => [...$row, 'month_start' => $row['month_start']->toDateString()])
        ->all();
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00'));
});

it('rolls customer 629 forward by calendar month and carries the credit of -2,000 (the 2026-10 row the legacy system never created)', function () {
    ['customer' => $customer] = arRealCase();

    expect(arLedger($customer))->toBe([
        '2026-07' => ['opening' => 0, 'payments' => 2000, 'sales' => 914419, 'closing' => 912419],
        '2026-08' => ['opening' => 912419, 'payments' => 0, 'sales' => 0, 'closing' => 912419],
        '2026-09' => ['opening' => 912419, 'payments' => 914419, 'sales' => 0, 'closing' => -2000],
        '2026-10' => ['opening' => -2000, 'payments' => 0, 'sales' => 0, 'closing' => -2000],
        '2026-11' => ['opening' => -2000, 'payments' => 0, 'sales' => 0, 'closing' => -2000],
    ]);

    // 23-2 (月度 by 20日締): the same months — 07-03 / 07-15 / 09-09 are all on or before the 20th
    expect(arLedger($customer, ReceivableBasis::Cycle))->toBe(arLedger($customer));

    $october = ReceivableMonth::query()->ofBasis(ReceivableBasis::Calendar)->where('customer_id', 629)->where('year_month', '2026-10')->sole();
    expect($october->carriesForward())->toBe('要作成') // 次月分判定 counts a credit (legacy: > 0 only)
        ->and(app(ReceivableLedger::class)->openingBalance(629, ReceivableBasis::Calendar, YearMonth::of(2026, 12)))->toBe(-2000);
});

it('shows invoice #0001617 as 過入金 2,000: paid 916,419 against 914,419', function () {
    ['receipt' => $receipt] = arRealCase();

    expect($receipt->billed_amount)->toBe(914419)
        ->and($receipt->settled_amount)->toBe(916419)
        ->and($receipt->paymentStatus())->toBe('過入金')
        ->and($receipt->overpayment())->toBe(2000)
        ->and($receipt->shortfall())->toBeNull();

    $receipt->lines()->whereDate('received_on', '2026-07-03')->delete();
    $receipt->refreshTotals();
    expect($receipt->paymentStatus())->toBe('済');
});

it('shifts every later opening and closing by +1,000 when a 1,000 sale is back-dated into 2026-08', function () {
    ['customer' => $customer] = arRealCase();

    arSale($customer, '2026-08-05', 1000, '非課税');

    expect(arLedger($customer))->toBe([
        '2026-07' => ['opening' => 0, 'payments' => 2000, 'sales' => 914419, 'closing' => 912419],
        '2026-08' => ['opening' => 912419, 'payments' => 0, 'sales' => 1000, 'closing' => 913419],
        '2026-09' => ['opening' => 913419, 'payments' => 914419, 'sales' => 0, 'closing' => -1000],
        '2026-10' => ['opening' => -1000, 'payments' => 0, 'sales' => 0, 'closing' => -1000],
        '2026-11' => ['opening' => -1000, 'payments' => 0, 'sales' => 0, 'closing' => -1000],
    ]);
});

it('recreates a deleted month identically on roll-forward, and a second run changes nothing', function () {
    ['customer' => $customer] = arRealCase();
    $before = arStoredRows($customer);

    ReceivableMonth::query()->ofBasis(ReceivableBasis::Calendar)->where('customer_id', 629)->where('year_month', '2026-10')->delete();
    expect(fn () => app(ReceivableLedger::class)->openingBalance(629, ReceivableBasis::Calendar, YearMonth::of(2026, 11)))
        ->toThrow(ReceivableGap::class, '2026年11月'); // a hole after a balance ≠ 0 fails loudly, never reads as 0

    expect(app(ReceivableLedger::class)->rollForward())->toBe(['customers' => 1, 'created' => 1, 'updated' => 0, 'deleted' => 0])
        ->and(arStoredRows($customer))->toBe($before)
        ->and(app(ReceivableLedger::class)->rollForward())->toBe(['customers' => 1, 'created' => 0, 'updated' => 0, 'deleted' => 0]);

    // the scheduled command is the same roll-forward
    expect(Artisan::call('sales:receivables-roll'))->toBe(0)
        ->and(Artisan::output())->toContain('1 customer(s): 0 month(s) created, 0 updated, 0 removed');
});

it('writes the new month of every open balance on the daily roll-forward, also without any save', function () {
    ['customer' => $customer] = arRealCase();

    $this->travelTo(CarbonImmutable::parse('2027-01-05 00:30'));
    Artisan::call('sales:receivables-roll');

    expect(array_keys(arLedger($customer)))->toBe(['2026-07', '2026-08', '2026-09', '2026-10', '2026-11', '2026-12', '2027-01'])
        ->and(arLedger($customer)['2027-01'])->toBe(['opening' => -2000, 'payments' => 0, 'sales' => 0, 'closing' => -2000]);
});

it('stops the months of a balance of exactly 0, keeps its history and starts later activity from 0', function () {
    $customer = arCustomer('ゼロ商事');
    arSale($customer, '2026-06-10', 10000);
    $receipt = Receipt::query()->create(['customer_id' => $customer->id]);
    $receipt->lines()->create(['received_on' => '2026-07-10', 'amount' => 11000, 'method' => '振込']);

    expect(arLedger($customer))->toBe([
        '2026-06' => ['opening' => 0, 'payments' => 0, 'sales' => 11000, 'closing' => 11000],
        '2026-07' => ['opening' => 11000, 'payments' => 11000, 'sales' => 0, 'closing' => 0],
    ])->and(app(ReceivableLedger::class)->openingBalance($customer->id, ReceivableBasis::Calendar, YearMonth::of(2026, 10)))->toBe(0);

    arSale($customer, '2026-10-01', 5000);

    expect(arLedger($customer))->toBe([
        '2026-06' => ['opening' => 0, 'payments' => 0, 'sales' => 11000, 'closing' => 11000],
        '2026-07' => ['opening' => 11000, 'payments' => 11000, 'sales' => 0, 'closing' => 0],
        '2026-10' => ['opening' => 0, 'payments' => 0, 'sales' => 5500, 'closing' => 5500],
        '2026-11' => ['opening' => 5500, 'payments' => 0, 'sales' => 0, 'closing' => 5500],
    ]);
});

it('assigns payments to the 月度 by the closing day (20日締): 2026-09-09 → 2026-09, 2026-09-25 → 2026-10', function () {
    expect(ReceivableBasis::Cycle->periodOf(CarbonImmutable::parse('2026-09-09'), ClosingDay::Day20)->key())->toBe('2026-09')
        ->and(ReceivableBasis::Cycle->periodOf(CarbonImmutable::parse('2026-09-25'), ClosingDay::Day20)->key())->toBe('2026-10')
        ->and(ReceivableBasis::Calendar->periodOf(CarbonImmutable::parse('2026-09-25'), ClosingDay::Day20)->key())->toBe('2026-09')
        ->and(ReceivableBasis::Cycle->periodOf(CarbonImmutable::parse('2026-09-25'), ClosingDay::EndOfMonth)->key())->toBe('2026-09');

    ['customer' => $customer, 'receipt' => $receipt] = arRealCase();
    expect(arLedger($customer, ReceivableBasis::Cycle)['2026-09']['payments'])->toBe(914419);

    // a payment without an invoice takes the customer's 締日 (the legacy fell back to the calendar month)
    $advance = Receipt::query()->create(['customer_id' => $customer->id]);
    $advance->lines()->create(['received_on' => '2026-09-25', 'amount' => 500, 'method' => '振込']);
    expect(arLedger($customer, ReceivableBasis::Cycle)['2026-10']['payments'])->toBe(500)
        ->and(arLedger($customer)['2026-09']['payments'])->toBe(914419 + 500); // the same ledger by calendar month
});

it('re-applies a changed 端数区分 of the customer to all its existing months', function () {
    $customer = arCustomer('端数商事');
    arSale($customer, '2026-10-01', 1005); // tax 100.5

    expect(arLedger($customer)['2026-10']['sales'])->toBe(1005 + 101);

    $customer->update(['rounding' => '切捨']);
    expect(arLedger($customer)['2026-10']['sales'])->toBe(1005 + 100)
        ->and(arLedger($customer)['2026-11']['opening'])->toBe(1105);
});

it('refuses 請求書更新 over a gap instead of taking 0 as 前月売掛残', function () {
    ['customer' => $customer] = arRealCase();
    $month = ReceivableMonth::query()->ofBasis(ReceivableBasis::Cycle)->where('customer_id', 629)->where('year_month', '2026-09')->sole();
    $invoice = Invoice::query()->create(['number' => '0001700', 'customer_id' => 629, 'invoiced_on' => '2026-09-20', 'closing_day' => '20日']);

    expect(app(InvoiceBalances::class)->apply($month, $invoice)->previous_amount)->toBe(912419);

    DB::table('receivable_months')->where('basis', 'cycle')->where('customer_id', 629)->where('year_month', '2026-08')->delete();
    expect(fn () => app(InvoiceBalances::class)->apply($month->fresh(), $invoice->fresh()))->toThrow(SalesActionNotAllowed::class, '売掛の月が欠落しています');
});

it('shows the month lists relative to the chosen 基準月, the resolved month in the heading', function () {
    Filament::setCurrentPanel('erp');
    $codes = [ReceivableResource::appCode() => ReceivableResource::appName(), CycleReceivableResource::appCode() => CycleReceivableResource::appName()];
    app(AppLifecycle::class)->syncWithCode($codes, [ReceivableResource::appCode() => ReceivableResource::appSettingDefaults(), CycleReceivableResource::appCode() => CycleReceivableResource::appSettingDefaults()]);
    $space = Space::query()->firstOrCreate(['panel_id' => 'erp', 'name' => '売掛スペース']);
    BusinessApp::query()->whereIn('code', array_keys($codes))->get()->each(function (BusinessApp $app) use ($space): void {
        $app->forceFill(['status' => AppStatus::Published])->save();
        $app->spaces()->syncWithoutDetaching([$space->id]);
    });
    $this->actingAs(User::factory()->superAdmin()->create());
    arRealCase();
    $row = fn (string $month): ReceivableMonth => ReceivableMonth::query()->ofBasis(ReceivableBasis::Calendar)->where('customer_id', 629)->where('year_month', $month)->sole();

    $page = Livewire::test(ListReceivables::class); // stores the views
    $view = fn (string $name): string => (string) AppListView::query()->where('business_app_id', BusinessApp::query()->where('code', 'receivable')->value('id'))->where('name', $name)->value('id');

    $page->set('listView', $view('先月売掛一覧'))
        ->assertSee('先月売掛一覧（2026年10月）')
        ->assertCanSeeTableRecords([$row('2026-10')])
        ->assertCanNotSeeTableRecords([$row('2026-09'), $row('2026-11')])
        ->call('setBaseMonth', '2026-10')
        ->assertSee('先月売掛一覧（2026年09月）')
        ->assertCanSeeTableRecords([$row('2026-09')])
        ->assertCanNotSeeTableRecords([$row('2026-10')])
        ->call('shiftBaseMonth', -1)
        ->assertSee('先月売掛一覧（2026年08月）')
        ->set('listView', $view('先々月売掛一覧'))
        ->assertSee('先々月売掛一覧（2026年07月）')
        ->assertCanSeeTableRecords([$row('2026-07')])
        ->call('resetBaseMonth')
        ->assertSee('先々月売掛一覧（2026年09月）')
        ->set('listView', $view('売掛一覧'))
        ->assertSee('売掛一覧')
        ->assertDontSee('売掛一覧（');

    // 23-2 has the month lists too; no saved view holds a month
    Livewire::test(ListCycleReceivables::class)->assertSee(['当月売掛一覧', '先月売掛一覧', '先々月売掛一覧']);
    expect(AppListView::query()->pluck('filter')->map(fn (array $filter): string => json_encode($filter, JSON_UNESCAPED_UNICODE))->implode(' '))
        ->not->toMatch('/20\d\d-\d\d|20\d\d年/')
        ->not->toContain('last_month');
});

it('turns stored month lists relative to today into 対象月 of the 基準月, and adds the missing ones (migration)', function () {
    $app = BusinessApp::query()->create(['code' => 'receivable', 'name' => '23. 売掛管理', 'panel_id' => 'erp', 'list_views_seeded_at' => now()]);
    $old = fn (string $mode): array => ['match' => 'and', 'conditions' => [['field' => 'month_start', 'operator' => 'eq', 'values' => [], 'mode' => $mode]], 'sorts' => []];
    $app->listViews()->create(['name' => '先月売掛一覧', 'type' => 'table', 'columns' => ['year_month'], 'filter' => $old('last_month'), 'sort_order' => 1]);
    $app->listViews()->create(['name' => '当月売掛一覧', 'type' => 'table', 'columns' => ['year_month'], 'filter' => $old('this_month'), 'sort_order' => 2]);
    $app->listViews()->create(['name' => '売掛一覧', 'type' => 'table', 'columns' => ['year_month'], 'filter' => ['match' => 'and', 'conditions' => [], 'sorts' => []], 'sort_order' => 3]);

    (require base_path('modules/Sales/Database/Migrations/2026_11_19_000001_receivables_period_check_and_month_views.php'))->up();

    $views = AppListView::query()->where('business_app_id', $app->id)->orderBy('sort_order')->get();
    expect($views->pluck('name')->all())->toBe(['先月売掛一覧', '当月売掛一覧', '先々月売掛一覧', '売掛一覧'])
        ->and($views->map(fn (AppListView $view): array => $view->filter['conditions'])->all())->toEqual([ // jsonb keeps no key order
            [['field' => 'base_offset', 'operator' => 'in', 'values' => ['m1']]],
            [['field' => 'base_offset', 'operator' => 'in', 'values' => ['m0']]],
            [['field' => 'base_offset', 'operator' => 'in', 'values' => ['m2']]],
            [],
        ]);
});

it('lets the database keep 年月 and 年月日 of a month together (PostgreSQL)', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('CHECK constraint on PostgreSQL only');
    }
    $customer = arCustomer('検査商事');

    expect(fn () => DB::table('receivable_months')->insert(['basis' => 'calendar', 'customer_id' => $customer->id, 'year_month' => '2026-09', 'month_start' => '2026-10-01', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('receivable_months')->insert(['basis' => 'calendar', 'customer_id' => $customer->id, 'year_month' => '2026年09月', 'month_start' => '2026-09-01', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});
