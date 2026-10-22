<?php

/*
 * The screens of 05 納品(仕入)入力, 07 支払入力, 15 運送請求データ取込, 20 消耗品発注 and 23 買掛管理 (SYSTEM_BRAIN APP_05/07/15/
 * 20/23B): the apps register with the switches of their legacy templates and the legacy views; 金額 = 数量 × 単価 by the
 * supplier's 端数区分 (BR-PU-02), numbering (BR-PU-01), the overrun alert (BR-PU-05), CSV import with validation and
 * posting (16_REBUILD_SPEC), 出金日 = 支払日 (BR-PU-08), carrier data posted as purchases, the approval of consumable
 * orders (BR-PU-09) and the payables taking only 消費税端数調整 by hand (BR-PU-06).
 */

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Models\AppListView;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;
use Modules\MasterData\Models\Consumable;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Order;
use Modules\Purchasing\Domain\ConsumableOrders\ConsumableOrderStatus;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\ConsumableOrderResource;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\Pages\CreateConsumableOrder;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\Pages\ListConsumableOrders;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\Pages\ViewConsumableOrder;
use Modules\Purchasing\Filament\Resources\FreightCharges\FreightChargeResource;
use Modules\Purchasing\Filament\Resources\FreightCharges\Pages\ListFreightCharges;
use Modules\Purchasing\Filament\Resources\FreightCharges\Pages\ViewFreightCharge;
use Modules\Purchasing\Filament\Resources\Payables\Pages\EditPayable;
use Modules\Purchasing\Filament\Resources\Payables\Pages\ListPayables;
use Modules\Purchasing\Filament\Resources\Payables\Pages\ViewPayable;
use Modules\Purchasing\Filament\Resources\Payables\PayableResource;
use Modules\Purchasing\Filament\Resources\Payments\Pages\CreatePayment;
use Modules\Purchasing\Filament\Resources\Payments\Pages\ListPayments;
use Modules\Purchasing\Filament\Resources\Payments\Pages\ViewPayment;
use Modules\Purchasing\Filament\Resources\Payments\PaymentResource;
use Modules\Purchasing\Filament\Resources\Purchases\Pages\CreatePurchase;
use Modules\Purchasing\Filament\Resources\Purchases\Pages\EditPurchase;
use Modules\Purchasing\Filament\Resources\Purchases\Pages\ListPurchases;
use Modules\Purchasing\Filament\Resources\Purchases\Pages\ViewPurchase;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseResource;
use Modules\Purchasing\Models\ConsumableOrder;
use Modules\Purchasing\Models\FreightCharge;
use Modules\Purchasing\Models\PayableMonth;
use Modules\Purchasing\Models\Payment;
use Modules\Purchasing\Models\Purchase;

/** The five purchasing apps, published in a space of 白石封筒 as アプリ設定 would add them (registry sync). */
function publishPurchasingApps(): void
{
    $resources = [PurchaseResource::class, PaymentResource::class, FreightChargeResource::class, ConsumableOrderResource::class, PayableResource::class];
    $codes = [];
    $defaults = [];
    foreach ($resources as $resource) {
        $codes[$resource::appCode()] = $resource::appName();
        $defaults[$resource::appCode()] = $resource::appSettingDefaults();
    }

    app(AppLifecycle::class)->syncWithCode($codes, $defaults);
    $space = Space::query()->firstOrCreate(['panel_id' => 'erp', 'name' => '購買スペース']);
    BusinessApp::query()->whereIn('code', array_keys($codes))->get()
        ->each(fn (BusinessApp $app) => $app->forceFill(['status' => AppStatus::Published, 'space_id' => $space->id])->save());
}

/** The id of a stored list view of an app (stored when its list is first shown). */
function purchasingView(string $code, string $name): string
{
    return (string) AppListView::query()->where('business_app_id', BusinessApp::query()->where('code', $code)->value('id'))->where('name', $name)->value('id');
}

function screenSupplier(string $name = '紙商事', array $attributes = []): Partner
{
    return Partner::query()->create([
        'name' => $name, 'short_name' => mb_substr($name, 0, 4), 'code' => 'S'.random_int(100, 999), 'is_supplier' => true,
        'purchase_category' => '03.材料仕入', 'supplier_tax_mode' => '外', 'supplier_rounding' => '四捨五入', ...$attributes,
    ]);
}

function screenJob(string $product = '長3封筒'): Order
{
    $customer = Partner::query()->firstOrCreate(['name' => '得意先'], ['short_name' => '得意', 'is_customer' => true]);

    return Order::query()->create(['customer_id' => $customer->id, 'product_name' => $product, 'quantity' => '1000', 'sales_amount' => 50000]);
}

function csvFile(string $contents, string $name = 'import.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00'));
    publishPurchasingApps();
    $this->admin = User::factory()->superAdmin()->create(['name' => '管理者']);
    $this->supplier = screenSupplier();
});

it('registers the five apps from the code, with 一括削除 as in their legacy templates', function () {
    expect((new AppDiscovery([base_path('modules')]))->definitions())
        ->toHaveKey('purchase-entry', '05. 納品(仕入)入力')
        ->toHaveKey('payment-entry', '07. 支払入力')
        ->toHaveKey('freight-billing', '15. 運送請求データ取込')
        ->toHaveKey('consumable-order', '20. 消耗品発注')
        ->toHaveKey('payable', '23. 買掛管理');

    $apps = BusinessApp::query()->pluck('bulk_delete_enabled', 'code');
    expect($apps['purchase-entry'])->toBeTrue()
        ->and($apps['payment-entry'])->toBeFalse()
        ->and($apps['freight-billing'])->toBeFalse()
        ->and($apps['consumable-order'])->toBeFalse()
        ->and($apps['payable'])->toBeTrue();
});

it('enters a purchase: 金額 from 数量 × 単価, numbered, totalled, its job and payables updated', function () {
    $job = screenJob();
    $watcher = User::factory()->create(['name' => '通知 太郎']);
    $this->actingAs($this->admin);

    // 1,234 × 1.5 = 1,851 (四捨五入 of the supplier)
    $component = Livewire::test(CreatePurchase::class)
        ->assertSee('マスタ登録と異なる仕入カテゴリの場合は選択してください。') // 吹き出し (Customine 05 page 3)
        ->fillForm(['supplier_id' => $this->supplier->id])
        ->fillForm(['purchased_on' => '2026-11-05', 'order_id' => $job->id, 'product_name' => 'クラフト紙', 'quantity' => '1234', 'unit_price' => '1.5'])
        ->assertSchemaStateSet(['amount' => 1851])
        ->fillForm(['cutting_amount' => '500', 'overruns' => ['用紙'], 'notified_users' => [$watcher->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    $purchase = Purchase::query()->sole();
    $component->assertRedirect(PurchaseResource::getUrl('view', ['record' => $purchase]));
    expect($purchase->purchase_number)->toBe('0000001')
        ->and($purchase->total)->toBe(2351)
        ->and($job->fresh()->purchase_cost_total)->toBe(2351)
        ->and(PayableMonth::query()->where('supplier_id', $this->supplier->id)->where('year_month', '2026-11')->sole()->purchase_amount)->toBe(2351)
        // 仕入原価超過 → 通知先 (BR-PU-05)
        ->and($watcher->notifications()->sole()->data['body'])->toBe('仕入原価超過があります。（用紙）');

    $other = Purchase::query()->create(['supplier_id' => $this->supplier->id, 'purchased_on' => '2026-11-01', 'order_id' => $job->id, 'product_name' => '別の紙', 'amount' => 700]);
    Livewire::test(ViewPurchase::class, ['record' => $purchase->getRouteKey()])
        ->assertSeeInOrder(['納品(仕入)日', '2026-11-05', '仕入先', '紙商事', '消費税区分', '外', '仕入カテゴリ(マスタ)', '03.材料仕入', '0000001',
            '注番基本情報', '長3封筒', '¥50,000', '品名', 'クラフト紙', '¥1,851', '同注番の仕入履歴', '別の紙', '¥700', '仕入金額合計', '¥2,351']);

    // an unchanged alert is not sent again; a new 通知先 gets it
    $second = User::factory()->create();
    Livewire::test(EditPurchase::class, ['record' => $purchase->getRouteKey()])->fillForm(['product_name' => 'クラフト紙A'])->call('save')->assertHasNoFormErrors();
    Livewire::test(EditPurchase::class, ['record' => $purchase->getRouteKey()])->fillForm(['notified_users' => [$watcher->id, $second->id]])->call('save')->assertHasNoFormErrors();
    expect($watcher->notifications()->count())->toBe(2)->and($second->notifications()->count())->toBe(1);

    Livewire::test(ListPurchases::class)
        ->assertSee('納品(仕入)一覧')
        ->assertCanSeeTableRecords([$purchase, $other])
        ->sortTable('supplier_id')
        ->sortTable('effective_category')
        ->call('applyAppFilter', ['conditions' => [['field' => 'overruns', 'operator' => 'in', 'values' => ['用紙']]]])
        ->assertCanSeeTableRecords([$purchase])
        ->assertCanNotSeeTableRecords([$other]);
});

it('imports purchases from a CSV file only when every line is valid', function () {
    // uploads are kept by Livewire for a day of real time: no time travel while files are uploaded
    $this->travelBack();
    $job = screenJob();
    $this->actingAs($this->admin);
    $header = "納品(仕入)日,仕入先,受注No,品名,数量,単価,金額,断裁金額,仕入カテゴリ\n";

    Livewire::test(ListPurchases::class)
        ->callAction('importCsv', data: ['file' => csvFile($header."2026/11/2,紙商事,{$job->order_number},クラフト紙,100,12.345,,300,\n2026-02-30,無い商事,X-1,,,,abc,,99.不明\n")])
        ->assertNotified('読み込めませんでした');
    expect(Purchase::query()->count())->toBe(0);

    // Shift_JIS files are read too
    $file = mb_convert_encoding($header."2026/11/2,紙商事,{$job->order_number},クラフト紙,100,12.345,,300,\n20261103,紙商事,,運送代,,,5000,,23.運送代\n", 'SJIS-win', 'UTF-8');
    Livewire::test(ListPurchases::class)
        ->callAction('importCsv', data: ['file' => csvFile($file)])
        ->assertNotified('2件のレコードを読み込みました');

    $purchases = Purchase::query()->orderBy('id')->get();
    expect($purchases->pluck('purchase_number')->all())->toBe(['0000001', '0000002'])
        ->and($purchases[0]->amount)->toBe(1235) // round(100 × 12.345) by 四捨五入 (BR-PU-02)
        ->and($purchases[0]->total)->toBe(1535)
        ->and($purchases[1]->effectiveCategory()?->value)->toBe('23.運送代')
        ->and($job->fresh()->purchase_cost_total)->toBe(1535)
        ->and(PayableMonth::query()->where('supplier_id', $this->supplier->id)->sole()->purchase_amount)->toBe(6535);
});

it('enters a payment run: 出金日 = 支払日, the total and the payables follow, an untouched row is not saved', function () {
    $other = screenSupplier('印刷所');
    $this->actingAs($this->admin);
    $undoFake = Repeater::fake();

    Livewire::test(CreatePayment::class)
        ->fillForm(['paid_on' => '2026-11-25', 'lines' => [
            ['supplier_id' => $this->supplier->id, 'method' => '7.振込', 'amount' => '10000', 'notes' => '10月分'],
            ['supplier_id' => $other->id, 'method' => '5.相殺', 'amount' => '2000', 'notes' => null],
            ['supplier_id' => null, 'method' => '7.振込', 'amount' => null, 'notes' => null],
        ]])
        ->call('create')
        ->assertHasNoFormErrors();
    $undoFake();

    $payment = Payment::query()->sole();
    expect($payment->total)->toBe(12000)
        ->and($payment->lines)->toHaveCount(2)
        ->and($payment->lines->pluck('withdrawn_on')->map->toDateString()->unique()->all())->toBe(['2026-11-25'])
        ->and(PayableMonth::query()->where('supplier_id', $this->supplier->id)->where('year_month', '2026-11')->sole()->paid)->toBe(10000);

    Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->assertSeeInOrder(['支払日', '2026-11-25', '支払金額合計', '¥12,000', '支払内容詳細', '紙商事', '7.振込', '¥10,000', '2026-11-25', '10月分', '2026年11月',
            '印刷所', '5.相殺', '¥2,000']);

    Livewire::test(ListPayments::class)
        ->assertSee('支払入力一覧')
        ->assertCanSeeTableRecords([$payment])
        ->sortTable('lines')
        ->call('applyAppFilter', ['conditions' => [['field' => 'supplier_id', 'operator' => 'in', 'values' => [(string) $other->id]]]])
        ->assertCanSeeTableRecords([$payment]);
});

it('imports carrier billing data and posts the chosen rows as purchases', function () {
    // uploads are kept by Livewire for a day of real time: no time travel while files are uploaded
    $this->travelBack();
    $job = screenJob();
    $carrier = screenSupplier('運送会社', ['purchase_category' => '13.原運送代', 'supplier_rounding' => '切捨']);
    $this->actingAs($this->admin);

    Livewire::test(ListFreightCharges::class)
        ->callAction('importCsv', data: ['file' => csvFile("日付,注番,金額,運送会社番号,備考,単価,数量\n2026/11/04,{$job->order_number},3300,A-1,大阪便,,\n2026/11/05,,,A-2,,15.5,11\n")])
        ->assertNotified('2件のレコードを読み込みました');

    [$first, $second] = FreightCharge::query()->orderBy('id')->get()->all();
    expect($first->order_id)->toBe($job->id)->and($first->charged_on?->toDateString())->toBe('2026-11-04');

    Livewire::test(ListFreightCharges::class)
        ->assertSee('運送請求データ一覧')
        ->callTableBulkAction('postPurchases', [$first, $second], data: ['supplier_id' => $carrier->id])
        ->assertNotified('納品(仕入)を作成しました（2件）')
        // already posted rows are left out
        ->callTableBulkAction('postPurchases', [$first], data: ['supplier_id' => $carrier->id])
        ->assertNotified('納品(仕入)を作成しました（0件）');

    $first->refresh();
    expect(Purchase::query()->count())->toBe(2)
        ->and($first->purchase?->product_name)->toBe('大阪便')
        ->and($first->purchase?->amount)->toBe(3300)
        ->and($second->fresh()->purchase?->amount)->toBe(170) // 切捨 of 15.5 × 11
        ->and($job->fresh()->purchase_cost_total)->toBe(3300)
        ->and(PayableMonth::query()->where('supplier_id', $carrier->id)->sole()->getAttribute('cat_cost_delivery'))->toBe(3470);

    Livewire::test(ViewFreightCharge::class, ['record' => $first->getRouteKey()])
        ->assertSeeInOrder(['日付', '2026-11-04', '注番', $job->order_number, '金額', '¥3,300', '運送会社番号', 'A-1', '納品(仕入)入力No', $first->purchase?->purchase_number]);
});

it('orders consumables: 単価 / 単位 copied from the master, 承認依頼 and 承認する', function () {
    $creator = grantDashboards(User::factory()->create(['name' => '発注 太郎']), 'erp');
    $app = BusinessApp::query()->where('code', 'consumable-order')->sole();
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true, AppAbility::Create->column() => true,
        AppAbility::Update->column() => true]);
    $app->space?->users()->attach($creator);
    $box = Consumable::query()->create(['name' => '段ボール A式', 'unit_price' => '85.5', 'unit' => '枚']);
    $this->actingAs($creator);
    $undoFake = Repeater::fake();

    Livewire::test(CreateConsumableOrder::class)
        ->fillForm(['ordered_on' => '2026-11-09', 'supplier_id' => $this->supplier->id, 'orderers' => [$creator->id], 'lines' => [
            ['consumable_id' => $box->id, 'quantity' => '200', 'requested_on' => '2026-11-20', 'orderer' => '発注 太郎', 'delivered_on' => null, 'notes' => null],
        ]])
        ->call('create')
        ->assertHasNoFormErrors();
    $undoFake();

    $order = ConsumableOrder::query()->sole();
    $line = $order->lines->sole();
    expect($order->status)->toBe(ConsumableOrderStatus::Unprocessed)->and($line->unit_price)->toBe('85.5000')->and($line->unit)->toBe('枚');

    // a later master price does not change the order (lookup snapshot)
    $box->update(['unit_price' => '99']);
    Livewire::test(ViewConsumableOrder::class, ['record' => $order->getRouteKey()])
        ->assertSeeInOrder(['ステータス', '未処理', '作業者', '発注 太郎', '発注日', '2026-11-09', '発注先', '紙商事', '発注明細', '段ボール A式', '85.5', '200', '枚'])
        ->assertActionVisible(TestAction::make('advanceStatus'))
        ->callAction(TestAction::make('advanceStatus'))
        ->assertHasNoActionErrors();
    expect($order->fresh()->status)->toBe(ConsumableOrderStatus::Checking);

    // 承認する is for administrators; the waiting order is in their （作業者が自分）
    Livewire::test(ViewConsumableOrder::class, ['record' => $order->getRouteKey()])->assertActionHidden(TestAction::make('advanceStatus'));
    $this->actingAs($this->admin);
    Livewire::test(ListConsumableOrders::class)
        ->assertSee('発注一覧')
        ->set('listView', purchasingView('consumable-order', '（作業者が自分）'))
        ->assertCanSeeTableRecords([$order]);
    Livewire::test(ViewConsumableOrder::class, ['record' => $order->getRouteKey()])->callAction(TestAction::make('advanceStatus'));
    expect($order->fresh()->status)->toBe(ConsumableOrderStatus::Approved)
        ->and($creator->notifications()->count())->toBe(1);
    Livewire::test(ListConsumableOrders::class)
        ->set('listView', purchasingView('consumable-order', '（作業者が自分）'))
        ->assertCanNotSeeTableRecords([$order]);
});

it('shows the payables, takes only 消費税端数調整 by hand and sums the list', function () {
    Purchase::query()->create(['supplier_id' => $this->supplier->id, 'purchased_on' => '2026-10-05', 'product_name' => 'クラフト紙', 'amount' => 10000]);
    $payment = Payment::query()->create(['paid_on' => '2026-11-25']);
    $payment->lines()->create(['supplier_id' => $this->supplier->id, 'amount' => 4000]);
    $this->actingAs($this->admin);

    $october = PayableMonth::query()->where('supplier_id', $this->supplier->id)->where('year_month', '2026-10')->sole();
    $november = PayableMonth::query()->where('supplier_id', $this->supplier->id)->where('year_month', '2026-11')->sole();
    expect(PayableResource::canCreate())->toBeFalse()->and(PayableResource::canDelete($october))->toBeFalse();

    Livewire::test(ListPayables::class)
        ->assertSee(['先月買掛一覧', '当月買掛一覧', '買掛一覧', '合計', '¥11,000']) // 先月 = 2026年10月
        ->assertCanSeeTableRecords([$october])
        ->assertCanNotSeeTableRecords([$november])
        ->set('listView', purchasingView('payable', '当月買掛一覧'))
        ->assertCanSeeTableRecords([$november])
        ->set('listView', purchasingView('payable', '買掛一覧(すべての項目)'))
        ->assertCanSeeTableRecords([$october, $november])
        ->sortTable('purchase_category');

    // 前月買掛残 ¥11,000 − 当月支払額 ¥4,000 = ¥7,000
    Livewire::test(ViewPayable::class, ['record' => $november->getRouteKey()])
        ->assertSeeInOrder(['年月', '2026年11月', '得意先名', '紙商事', '前月買掛残', '¥11,000', '当月支払額', '¥4,000', '差引残高', '¥7,000', '当月買掛残', '¥7,000',
            '▼ 仕入カテゴリ別内訳', '過去買掛レコード', '2026年11月', '2026年10月', '支払明細', '2026-11-25', '7.振込', '¥4,000']);
    Livewire::test(ViewPayable::class, ['record' => $october->getRouteKey()])
        ->assertSeeInOrder(['材料仕入', '¥10,000', '仕入情報詳細', '2026-10-05', 'クラフト紙', '¥10,000', '03.材料仕入']);

    Livewire::test(EditPayable::class, ['record' => $october->getRouteKey()])->fillForm(['tax_adjustment' => '-1'])->call('save')->assertHasNoFormErrors();
    expect($october->fresh()->tax_amount)->toBe(999)->and($november->fresh()->closing_balance)->toBe(6999);

    // after the supplier's terms changed, 一括更新 / 再集計 recalculate
    $this->supplier->update(['supplier_tax_mode' => '内']);
    Livewire::test(ListPayables::class)->callAction('refreshPayables')->assertNotified('買掛を更新しました（1仕入先）');
    expect($october->fresh()->month_total)->toBe(10000);
    Livewire::test(ViewPayable::class, ['record' => $october->getRouteKey()])->callAction(TestAction::make('recalculate'))->assertNotified('再集計しました');

    // 当月未作成一括作成 (Customine 23買掛 page 9): a month nothing was saved in is created again
    PayableMonth::query()->whereKey($november->id)->delete();
    Livewire::test(ListPayables::class)->callAction('createCurrentMonth')->assertNotified('処理が完了しました（2026年11月：1件作成）');
    expect(PayableMonth::query()->where('supplier_id', $this->supplier->id)->where('year_month', '2026-11')->sole()->previous_balance)->toBe(10000);
    Livewire::test(ListPayables::class)->callAction('createPreviousMonth')->assertNotified('処理が完了しました（2026年10月：0件作成）');

    // 吹き出し of 消費税額 / 消費税端数調整 (page 2)
    Livewire::test(EditPayable::class, ['record' => $october->getRouteKey()])
        ->assertSee(['誤差の調整に利用するフィールド。', '仕入額に対して、「消費税区分」と「端数区分」によって計算されます。']);
});

it('puts the delivered quantity of a consumable order into the stock of 19 once', function () {
    $box = Consumable::query()->create(['name' => '段ボール B式', 'unit_price' => '60', 'unit' => '枚']);
    $tape = Consumable::query()->create(['name' => 'ガムテープ', 'unit_price' => '150', 'unit' => '巻']);
    $order = ConsumableOrder::query()->create(['ordered_on' => '2026-11-02', 'supplier_id' => $this->supplier->id]);
    $line = $order->lines()->create(['consumable_id' => $box->id, 'quantity' => '200']);
    $order->lines()->create(['consumable_id' => $tape->id, 'quantity' => '10']);
    expect($box->stock())->toBeNull(); // ordered, not delivered yet

    $line->update(['delivered_on' => '2026-11-06']);
    expect($box->stock())->toBe('200.0000')
        ->and($box->stockMovements()->sole()->moved_on->toDateString())->toBe('2026-11-06');

    // saved again or corrected: still one 入庫
    $line->update(['quantity' => '180']);
    $line->save();
    expect($box->stock())->toBe('180.0000')->and($box->stockMovements()->count())->toBe(1);

    $line->update(['delivered_on' => null]);
    expect($box->stock())->toBeNull();

    $line->update(['delivered_on' => '2026-11-07']);
    $order->delete();
    expect($box->stock())->toBeNull()->and($tape->stock())->toBeNull();
});

it('lets employees use the purchasing apps as the app permissions allow', function () {
    $purchase = Purchase::query()->create(['supplier_id' => $this->supplier->id, 'purchased_on' => '2026-11-05', 'product_name' => 'クラフト紙', 'amount' => 1000]);
    $app = BusinessApp::query()->where('code', 'purchase-entry')->sole();
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
    $employee = grantDashboards(User::factory()->create(), 'erp');
    $app->space?->users()->attach($employee);
    $this->actingAs($employee);

    $this->get(PurchaseResource::getUrl('index'))->assertOk()->assertSee('クラフト紙');
    $this->get(PurchaseResource::getUrl('view', ['record' => $purchase]))->assertOk();
    $this->get(PurchaseResource::getUrl('create'))->assertForbidden();
    Livewire::test(ListPurchases::class)->assertActionHidden('importCsv');
    $this->get(PaymentResource::getUrl('index'))->assertForbidden();
});

it('has the legacy list buttons of 05 for administrators (Customine 05 pages 6 / 8 / 10 / 11)', function () {
    $job = screenJob();
    $purchase = Purchase::query()->create(['supplier_id' => $this->supplier->id, 'purchased_on' => '2026-10-05', 'order_id' => $job->id, 'product_name' => 'クラフト紙', 'amount' => 10000]);
    Purchase::query()->whereKey($purchase->id)->update(['purchase_number' => null]);
    $this->actingAs($this->admin);

    Livewire::test(ListPurchases::class)
        ->assertSeeInOrder(['納品(仕入)入力No発番', '受注レコード更新', '買掛レコード更新', 'ボタンの説明', '納品(仕入)入力No発番', 'csv一括読み込み後、採番されていないレコードに対して一括で採番します。'])
        ->callAction('numberPurchases')
        ->assertNotified('納品(仕入)入力Noの発番が完了しました。（1件）')
        ->callAction('numberPurchases')
        ->assertNotified('発番可能なレコードがありません。')
        ->callAction('refreshOrders')
        ->assertNotified('受注レコードに仕入金額を連携しました。（1件）')
        ->callAction('refreshPayables')
        ->assertNotified('買掛レコードに仕入金額を連携しました。（1仕入先）');
    expect($purchase->fresh()->purchase_number)->not->toBeNull();

    $employee = grantDashboards(User::factory()->create(), 'erp');
    BusinessApp::query()->where('code', 'purchase-entry')->sole()->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
    BusinessApp::query()->where('code', 'purchase-entry')->sole()->space?->users()->attach($employee);
    $this->actingAs($employee);
    Livewire::test(ListPurchases::class)->assertActionHidden('numberPurchases')->assertDontSee('csv一括読み込み後');
});
