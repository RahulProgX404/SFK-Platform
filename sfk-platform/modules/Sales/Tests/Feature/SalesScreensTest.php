<?php

/*
 * The screens of 06 売上入力, 10 納品書管理(売上), 11 請求書管理(売上), 21 売入金管理（売上）, 23 売掛管理 and 23-2
 * 売掛管理(締日別) (SYSTEM_BRAIN APP_06/10/11/21/23/23-2): the apps register with the switches of their legacy templates,
 * the legacy views, the documents made from the 06 list (BR-SA-08/09), the statement arithmetic of an invoice
 * (BR-SA-11), receipts settling it (BR-AR-04) and the receivables only taking 消費税端数調整 by hand (BR-AR-03), with
 * 請求書更新 of 23-2 (BR-SA-12).
 */

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Models\AppListView;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Filament\Resources\Orders\Pages\ViewOrder;
use Modules\Orders\Models\Order;
use Modules\Sales\Application\Documents\DeliveryNoteIssuer;
use Modules\Sales\Application\Documents\InvoiceIssuer;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\Sales\Filament\Resources\DeliveryNotes\Pages\CreateDeliveryNote;
use Modules\Sales\Filament\Resources\DeliveryNotes\Pages\EditDeliveryNote;
use Modules\Sales\Filament\Resources\DeliveryNotes\Pages\ListDeliveryNotes;
use Modules\Sales\Filament\Resources\DeliveryNotes\Pages\ViewDeliveryNote;
use Modules\Sales\Filament\Resources\Invoices\InvoiceResource;
use Modules\Sales\Filament\Resources\Invoices\Pages\EditInvoice;
use Modules\Sales\Filament\Resources\Invoices\Pages\ListInvoices;
use Modules\Sales\Filament\Resources\Invoices\Pages\ViewInvoice;
use Modules\Sales\Filament\Resources\Receipts\Pages\CreateReceipt;
use Modules\Sales\Filament\Resources\Receipts\Pages\EditReceipt;
use Modules\Sales\Filament\Resources\Receipts\Pages\ListReceipts;
use Modules\Sales\Filament\Resources\Receipts\Pages\ViewReceipt;
use Modules\Sales\Filament\Resources\Receipts\ReceiptResource;
use Modules\Sales\Filament\Resources\Receivables\CycleReceivableResource;
use Modules\Sales\Filament\Resources\Receivables\Pages\EditCycleReceivable;
use Modules\Sales\Filament\Resources\Receivables\Pages\ListCycleReceivables;
use Modules\Sales\Filament\Resources\Receivables\Pages\ListReceivables;
use Modules\Sales\Filament\Resources\Receivables\Pages\ViewCycleReceivable;
use Modules\Sales\Filament\Resources\Receivables\Pages\ViewReceivable;
use Modules\Sales\Filament\Resources\Receivables\ReceivableResource;
use Modules\Sales\Filament\Resources\Sales\Pages\CreateSale;
use Modules\Sales\Filament\Resources\Sales\Pages\EditSale;
use Modules\Sales\Filament\Resources\Sales\Pages\ListSales;
use Modules\Sales\Filament\Resources\Sales\Pages\ViewSale;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Models\DeliveryNote;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\Receipt;
use Modules\Sales\Models\ReceivableMonth;
use Modules\Sales\Models\Sale;

/** The six sales apps, published in a space of 白石封筒 as アプリ設定 would add them (registry sync). */
function publishSalesApps(): void
{
    $resources = [SaleResource::class, DeliveryNoteResource::class, InvoiceResource::class, ReceiptResource::class, ReceivableResource::class, CycleReceivableResource::class];
    $codes = [];
    $defaults = [];
    foreach ($resources as $resource) {
        $codes[$resource::appCode()] = $resource::appName();
        $defaults[$resource::appCode()] = $resource::appSettingDefaults();
    }

    app(AppLifecycle::class)->syncWithCode($codes, $defaults);
    $space = Space::query()->firstOrCreate(['panel_id' => 'erp', 'name' => '売上スペース']);
    BusinessApp::query()->whereIn('code', array_keys($codes))->get()
        ->each(fn (BusinessApp $app) => $app->forceFill(['status' => AppStatus::Published, 'space_id' => $space->id])->save());
}

/** The id of a stored list view of an app (stored when its list is first shown). */
function salesView(string $code, string $name): string
{
    return (string) AppListView::query()->where('business_app_id', BusinessApp::query()->where('code', $code)->value('id'))->where('name', $name)->value('id');
}

function screenCustomer(string $name = '株式会社得意先', array $attributes = []): Partner
{
    return Partner::query()->create([
        'name' => $name, 'short_name' => mb_substr($name, 0, 4), 'code' => 'C'.random_int(100, 999), 'is_customer' => true,
        'closing_day' => '20日', 'rounding' => '切捨', 'postal_code' => '530-0001', 'address' => '大阪市北区1-1',
        'invoice_issuance' => '郵送', 'delivery_note_issuance' => '郵送', 'collection_month' => '翌月', 'collection_day' => '末日',
        ...$attributes,
    ]);
}

function screenSale(Partner $customer, array $attributes = []): Sale
{
    return Sale::query()->create([
        'customer_id' => $customer->id, 'sold_on' => '2026-10-05', 'delivery_due_on' => '2026-10-06', 'product_name' => '長3封筒',
        'quantity' => '1000', 'unit_price' => '10', 'amount' => 10000, ...$attributes,
    ]);
}

/** A delivered and invoiced sale of the customer: its invoice (with its 21 record). */
function screenInvoice(Partner $customer, array $sale = []): Invoice
{
    $sale = screenSale($customer, $sale);
    app(DeliveryNoteIssuer::class)->issue([$sale]);

    return app(InvoiceIssuer::class)->issue([$sale->fresh()], CarbonImmutable::parse('2026-10-20'))[0];
}

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00'));
    publishSalesApps();
    $this->admin = User::factory()->superAdmin()->create(['name' => '管理者']);
    $this->customer = screenCustomer();
});

it('registers the six apps from the code, with 一括削除 as in their legacy templates', function () {
    expect((new AppDiscovery([base_path('modules')]))->definitions())
        ->toHaveKey('sales-entry', '06. 売上入力')
        ->toHaveKey('delivery-note', '10. 納品書管理(売上)')
        ->toHaveKey('invoice', '11. 請求書管理(売上)')
        ->toHaveKey('receipt', '21. 売入金管理（売上）')
        ->toHaveKey('receivable', '23. 売掛管理')
        ->toHaveKey('receivable-cycle', '23-2. 売掛管理(締日別)');

    $apps = BusinessApp::query()->pluck('bulk_delete_enabled', 'code');
    expect($apps['sales-entry'])->toBeFalse()
        ->and($apps['delivery-note'])->toBeFalse()
        ->and($apps['invoice'])->toBeFalse()
        ->and($apps['receipt'])->toBeFalse()
        ->and($apps['receivable'])->toBeTrue()
        ->and($apps['receivable-cycle'])->toBeTrue();
});

it('enters a sale and makes its delivery note and invoice from the 06 list', function () {
    $this->actingAs($this->admin);

    $component = Livewire::test(CreateSale::class)
        ->fillForm([
            'kind' => '通常売上',
            'sold_on' => '2026-10-05',
            'customer_id' => $this->customer->id,
            'delivery_due_on' => '2026-10-06',
            'product_name' => '長3封筒',
            'quantity' => '1000',
            'unit' => '枚',
            'unit_price' => '12.5',
            'amount' => '12500',
            'tax_rate' => '10%',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sale = Sale::query()->sole();
    $component->assertRedirect(SaleResource::getUrl('view', ['record' => $sale]));
    expect($sale->tax)->toBe(1250)->and($sale->billing_month)->toBe('2026-10');

    Livewire::test(ViewSale::class, ['record' => $sale->getRouteKey()])->assertSee([$sale->sales_number, '株式会社得意先', '¥12,500']);

    // the legacy list buttons (Customine 06 pages 6 / 9) on the checked rows, with their 機能説明 (page 22)
    Livewire::test(ListSales::class)
        ->assertCanSeeTableRecords([$sale])
        ->assertSeeInOrder(['期間指定から納品レコード作成', '選択して納品書レコード作成', 'sfk-menu-badge', '件', '選択して請求書レコード作成', 'sfk-menu-badge', '件', '随時請求書レコード作成', '編集制限',
            'ボタンの説明', '売上日付を指定した上で任意の売上レコードを取引先別に'], false)
        ->assertDontSee('sfk-app-menu-count', false)
        ->callAction('createDeliveryNotes')
        ->assertNotified('レコードを選択してください。')
        ->set('selectedTableRecords', [(string) $sale->id])
        ->callAction('createDeliveryNotes')
        ->assertNotified('納品書レコードの作成が完了しました（1件）')
        ->set('selectedTableRecords', [(string) $sale->id])
        ->callAction('createInvoices', data: ['invoiced_on' => '2026-10-20'])
        ->assertNotified('請求書レコードの作成が完了しました（1件）')
        ->set('selectedTableRecords', [(string) $sale->id])
        ->callAction('restrictEditing', data: ['operation' => '編集不可にする'])
        ->assertNotified('編集を不可にしました');
    expect($sale->fresh()->locked)->toBeTrue();

    $invoice = Invoice::query()->sole();
    expect(DeliveryNote::query()->sole()->subtotal)->toBe(12500)
        ->and($invoice->invoiced_on?->toDateString())->toBe('2026-10-20')
        ->and($invoice->billed_total)->toBe(13750)
        ->and($invoice->receipt)->not->toBeNull()
        ->and($sale->fresh()->invoice_id)->toBe($invoice->id);
});

it('starts a sale of a job from 売上入力 on its 04 record (legacy app action)', function () {
    $order = Order::query()->create(['customer_id' => $this->customer->id, 'product_name' => '角2封筒', 'quantity' => '1000', 'sales_amount' => 20000]);
    $this->actingAs($this->admin);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertActionVisible(TestAction::make('enterSale'))
        ->assertActionHasUrl(TestAction::make('enterSale'), SaleResource::getUrl('create', ['order' => $order->id]));

    Livewire::withQueryParams(['order' => $order->id])->test(CreateSale::class)
        ->assertSchemaStateSet(['customer_id' => $this->customer->id, 'order_id' => $order->id, 'product_name' => '角2封筒']);
});

it('shows and edits a delivery note, an untouched table row is not saved', function () {
    $sale = screenSale($this->customer);
    $note = app(DeliveryNoteIssuer::class)->issue([$sale])[0];
    $this->actingAs($this->admin);

    Livewire::test(ListDeliveryNotes::class)
        ->assertSee('納品書管理一覧')
        ->assertCanSeeTableRecords([$note])
        ->set('listView', salesView('delivery-note', '20日締日一覧'))
        ->assertCanSeeTableRecords([$note])
        ->set('listView', salesView('delivery-note', '5日締日一覧'))
        ->assertCanNotSeeTableRecords([$note]);

    Livewire::test(ViewDeliveryNote::class, ['record' => $note->getRouteKey()])
        ->assertSeeInOrder(['納品書番号', $note->number, '得意先名', '株式会社得意先', '納品書発行区分', '郵送', '納品金額合計(税抜)', '¥10,000',
            '納品内容詳細', '長3封筒', '10%対象小計', '¥10,000', '10%対象消費税', '¥1,000']);

    Livewire::test(EditDeliveryNote::class, ['record' => $note->getRouteKey()])->fillForm(['send_status' => '済'])->call('save')->assertHasNoFormErrors();
    expect($note->fresh()->send_status)->toBe('済')->and($note->fresh()->lines)->toHaveCount(1);

    // the first row is always there with 税率 10%; left like that it is not a row
    Livewire::test(CreateDeliveryNote::class)->fillForm(['customer_id' => $this->customer->id])->call('create')->assertHasNoFormErrors();
    expect(DeliveryNote::query()->latest('id')->first()->lines)->toHaveCount(0);
});

it('lists invoices by the closing day of the customer and keeps the statement arithmetic on edit', function () {
    $invoice = screenInvoice($this->customer);
    $other = screenInvoice(screenCustomer('五日締商事', ['closing_day' => '5日']), ['sold_on' => '2026-10-01', 'delivery_due_on' => '2026-10-02']);
    $this->actingAs($this->admin);

    Livewire::test(ListInvoices::class)
        ->assertSee('請求管理一覧')
        ->assertCanSeeTableRecords([$invoice, $other])
        ->set('listView', salesView('invoice', '20日締日一覧'))
        ->assertCanSeeTableRecords([$invoice])
        ->assertCanNotSeeTableRecords([$other])
        ->set('listView', 'all')
        ->sortTable('purchase_total')
        ->sortTable('master_closing_day')
        ->call('applyAppFilter', ['conditions' => [['field' => 'master_closing_day', 'operator' => 'in', 'values' => ['5日']]]])
        ->assertCanSeeTableRecords([$other])
        ->assertCanNotSeeTableRecords([$invoice]);

    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertSeeInOrder(['請求書番号', $invoice->number, '今回締日', '20日', '今回御買上額(税抜)', '¥10,000', '今回消費税額', '¥1,000',
            '合計御買上金額', '¥11,000', '御請求額', '¥11,000', '請求内容詳細', '長3封筒']);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->fillForm(['previous_amount' => '50000', 'received_amount' => '20000'])
        ->call('save')
        ->assertHasNoFormErrors();

    // 繰越金額 = 前月 − 入金; 御請求額 = 繰越 + 今回御買上額 + 消費税 (BR-SA-11)
    $invoice->refresh();
    expect($invoice->carried_amount)->toBe(30000)->and($invoice->billed_total)->toBe(41000)->and($invoice->subtotal)->toBe(10000);
});

it('settles an invoice with 入金明細 and lists what is still unpaid', function () {
    $paid = screenInvoice($this->customer);
    $unpaid = screenInvoice(screenCustomer('未入金商事'));
    $this->actingAs($this->admin);
    $undoFake = Repeater::fake();

    Livewire::test(EditReceipt::class, ['record' => $paid->receipt->getRouteKey()])
        ->fillForm(['lines' => [
            ['received_on' => '2026-11-05', 'amount' => '10670', 'fee' => '330', 'discount' => null, 'method' => '振込', 'bank' => '三菱UFJ銀行'],
        ]])
        ->call('save')
        ->assertHasNoFormErrors();
    $undoFake();

    $receipt = $paid->receipt->fresh();
    // 入金済 = 入金 + 手数料 + 歩引き (BR-AR-04)
    expect($receipt->settled_amount)->toBe(11000)
        ->and($receipt->shortfall())->toBeNull()
        ->and($receipt->lines->sole()->discount)->toBe(0)
        ->and(ReceivableMonth::query()->ofBasis(ReceivableBasis::Calendar)->where('customer_id', $this->customer->id)->where('year_month', '2026-11')->sole()->received)->toBe(11000);

    Livewire::test(ViewReceipt::class, ['record' => $receipt->getRouteKey()])
        ->assertSeeInOrder(['請求書番号', $paid->number, '得意先名', '株式会社得意先', '回収予定月', '翌月', '請求金額', '¥11,000', '入金済', '¥11,000',
            '入金明細', '2026-11-05', '¥10,670', '¥330', '振込', '2026年11月', '2026年11月', '¥11,000', '請求明細', '長3封筒']);

    Livewire::test(ListReceipts::class)
        ->assertSee('入金管理一覧　未入金')
        ->assertCanSeeTableRecords([$unpaid->receipt])
        ->assertCanNotSeeTableRecords([$receipt])
        ->set('listView', salesView('receipt', '入金管理一覧'))
        ->assertCanSeeTableRecords([$receipt, $unpaid->receipt])
        ->sortTable('shortfall');

    // only invoices without a receipt can be chosen; the receipt takes the invoice's figures
    $open = Invoice::query()->create(['customer_id' => $this->customer->id, 'invoiced_on' => '2026-10-31', 'closing_day' => '20日']);
    Livewire::test(CreateReceipt::class)
        ->assertFormFieldExists('invoice_id', fn ($field): bool => array_key_exists($open->id, $field->getOptions()) && ! array_key_exists($paid->id, $field->getOptions()))
        ->fillForm(['invoice_id' => $open->id])
        ->call('create')
        ->assertHasNoFormErrors();
    expect($open->fresh()->receipt?->customer_id)->toBe($this->customer->id)->and($open->fresh()->receipt?->closing_day)->toBe('20日');
});

it('shows the receivables, takes only 消費税端数調整 by hand and updates the invoice from 23-2', function () {
    $invoice = screenInvoice($this->customer);
    screenSale($this->customer, ['sold_on' => '2026-09-10', 'delivery_due_on' => '2026-09-10', 'amount' => 5000]);
    $this->actingAs($this->admin);

    $calendar = ReceivableMonth::query()->ofBasis(ReceivableBasis::Calendar)->where('customer_id', $this->customer->id)->where('year_month', '2026-10')->sole();
    $cycle = ReceivableMonth::query()->ofBasis(ReceivableBasis::Cycle)->where('customer_id', $this->customer->id)->where('year_month', '2026-10')->sole();
    expect(ReceivableResource::canCreate())->toBeFalse()->and(ReceivableResource::canDelete($calendar))->toBeFalse();

    Livewire::test(ListReceivables::class)
        ->assertSee(['先月売掛一覧', '当月売掛一覧', '売掛一覧'])
        ->assertCanSeeTableRecords([$calendar]) // 先月 = 2026年10月
        ->set('listView', salesView('receivable', '当月売掛一覧'))
        ->assertCanNotSeeTableRecords([$calendar])
        ->set('listView', salesView('receivable', '売掛一覧'))
        ->assertCanSeeTableRecords([$calendar])
        ->assertCanNotSeeTableRecords([$cycle]);

    Livewire::test(ListCycleReceivables::class)
        ->set('listView', salesView('receivable-cycle', '売掛一覧 - 20日締'))
        ->assertCanSeeTableRecords([$cycle])
        ->set('listView', salesView('receivable-cycle', '売掛一覧 - 5日締'))
        ->assertCanNotSeeTableRecords([$cycle]);

    // 前月売掛残 ¥5,500 (September) + ¥11,000 = ¥16,500
    Livewire::test(ViewReceivable::class, ['record' => $calendar->getRouteKey()])
        ->assertSeeInOrder(['年月', '2026年10月', '得意先名', '株式会社得意先', '前月売掛残', '¥5,500', '売上額', '¥10,000', '消費税額', '¥1,000',
            '当月売掛残', '¥16,500', '関連レコード一覧', '2026年11月', '2026年10月', '2026年09月', '売上情報詳細', '長3封筒']);

    Livewire::test(EditCycleReceivable::class, ['record' => $cycle->getRouteKey()])
        ->fillForm(['tax_adjustment' => '-1'])
        ->call('save')
        ->assertHasNoFormErrors();
    $cycle->refresh();
    expect($cycle->tax_adjustment)->toBe(-1)
        ->and($cycle->closing_balance)->toBe(16499)
        ->and(ReceivableMonth::query()->ofBasis(ReceivableBasis::Cycle)->where('customer_id', $this->customer->id)->where('year_month', '2026-11')->sole()->previous_balance)->toBe(16499);

    // 請求書更新: 前月御請求額 = 前月売掛残, ご入金額 = 当月入金額
    Livewire::test(ViewCycleReceivable::class, ['record' => $cycle->getRouteKey()])
        ->assertSee('過去売掛')
        ->callAction(TestAction::make('updateInvoice'), data: ['invoice_id' => (string) $invoice->id])
        ->assertHasNoActionErrors()
        ->assertRedirect(InvoiceResource::getUrl('view', ['record' => $invoice]));
    $invoice->refresh();
    expect($invoice->previous_amount)->toBe(5500)->and($invoice->received_amount)->toBe(0)->and($invoice->billed_total)->toBe(16500);
});

it('lets employees see the receivables as the app permissions allow, without 請求書更新 or 新規作成', function () {
    screenInvoice($this->customer);
    $cycle = ReceivableMonth::query()->ofBasis(ReceivableBasis::Cycle)->where('customer_id', $this->customer->id)->where('year_month', '2026-10')->sole();
    $app = BusinessApp::query()->where('code', 'receivable-cycle')->sole();
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true, AppAbility::Update->column() => true]);
    $employee = grantDashboards(User::factory()->create(), 'erp');
    $app->space?->users()->attach($employee);
    $this->actingAs($employee);

    $this->get(CycleReceivableResource::getUrl('index'))->assertOk()->assertSee('株式会社得意先');
    $this->get(CycleReceivableResource::getUrl('view', ['record' => $cycle]))->assertOk()->assertSee('再集計')->assertDontSee('請求書更新');
    $this->get(CycleReceivableResource::getUrl('edit', ['record' => $cycle]))->assertOk();
});

it('rewrites the delivery note and invoice row of a sale from its record (Customine 06 pages 14 / 15) and prints its note (page 5)', function () {
    $invoice = screenInvoice($this->customer);
    $sale = Sale::query()->sole();
    $note = DeliveryNote::query()->sole();
    $other = screenSale($this->customer, ['amount' => 3000]);
    $this->actingAs($this->admin);

    // a changed sale does not change its documents until the buttons are pressed (BR-SA-15)
    $sale->forceFill(['quantity' => '1200', 'amount' => 12000])->save();
    expect($note->fresh()->subtotal)->toBe(10000);

    Livewire::test(ViewSale::class, ['record' => $sale->getRouteKey()])
        ->callAction(TestAction::make('refreshDeliveryNote')->arguments([]))
        ->assertNotified('納品書番号：'.$note->number.' を更新しました。')
        ->callAction(TestAction::make('refreshInvoice'))
        ->assertNotified('請求書番号：'.$invoice->number.' を更新しました。');
    expect($note->fresh()->subtotal)->toBe(12000)
        ->and($invoice->fresh()->subtotal)->toBe(12000)
        ->and(Receipt::query()->where('invoice_id', $invoice->id)->sole()->billed_amount)->toBe(13200);

    // without documents: refused; the note is made and printed
    Livewire::test(ViewSale::class, ['record' => $other->getRouteKey()])
        ->callAction(TestAction::make('refreshInvoice'))
        ->assertNotified('この売上データの請求レコードが作成されていません。先に請求レコードの作成を行ってください。')
        ->callAction(TestAction::make('issueDeliveryNote'))
        ->assertNotified('納品書レコード未作成だったため新規作成しました。');
    expect($other->fresh()->delivery_note_id)->not->toBeNull();

    // a row taken off its note: the sale goes back to 未作成
    $note->lines()->where('sale_id', $sale->id)->sole()->delete();
    $sale->forceFill(['delivery_note_id' => $note->id])->saveQuietly();
    Livewire::test(ViewSale::class, ['record' => $sale->getRouteKey()])
        ->callAction(TestAction::make('refreshDeliveryNote'))
        ->assertNotified('納品書番号：'.$note->number.' が見つかりません。');
    expect($sale->fresh()->delivery_note_id)->toBeNull();
});

it('warns while a sale is filled in and needs 売上先 before 注番 (Customine 06 pages 2 / 21)', function () {
    $order = Order::query()->create(['customer_id' => $this->customer->id, 'product_name' => '角2封筒', 'quantity' => '1000', 'sales_amount' => 20000]);
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->assertFormFieldIsDisabled('order_id')
        ->assertSee(['売上先を入力しないと注番の選択はできません。', '管理者のみ編集可能となっています。', '初期値は10%です。'])
        ->fillForm(['customer_id' => $this->customer->id])
        ->assertFormFieldIsEnabled('order_id')
        ->fillForm(['order_id' => $order->id, 'unit_price' => '25'])
        ->fillForm(['quantity' => '1200'])
        ->assertNotified('受注数よりも多い数量で売上を作成できません。');
});

it('sums the receivables list and creates the missing months from 「…」 (Customine 23 pages 1 / 4 / 6, 23-2 pages 3 / 6 / 8)', function () {
    screenInvoice($this->customer);
    $this->actingAs($this->admin);

    Livewire::test(ListReceivables::class)
        ->set('listView', salesView('receivable', '売掛一覧'))
        ->assertSee(['合計', '対象件数: 2 件', '前月売掛残', '当月売掛残', '¥11,000'])
        ->callAction('refreshReceivables')
        ->assertNotified('売掛を更新しました（1得意先）');

    ReceivableMonth::query()->ofBasis(ReceivableBasis::Calendar)->where('year_month', '2026-11')->delete();
    Livewire::test(ListReceivables::class)->callAction('createCurrentMonth')->assertNotified('処理が完了しました（2026年11月：1件作成）');
    expect(ReceivableMonth::query()->ofBasis(ReceivableBasis::Calendar)->where('year_month', '2026-11')->sole()->previous_balance)->toBe(11000);

    ReceivableMonth::query()->ofBasis(ReceivableBasis::Cycle)->where('year_month', '2026-11')->delete();
    Livewire::test(ListCycleReceivables::class)
        ->callAction('createCurrentMonth', data: ['closing_day' => '5日'])
        ->assertNotified('処理が完了しました（2026年11月：0件作成）')
        ->callAction('createCurrentMonth', data: ['closing_day' => '20日'])
        ->assertNotified('処理が完了しました（2026年11月：1件作成）');
});

it('makes the delivery notes of a period from 期間指定から納品レコード作成 (Customine 06 page 1)', function () {
    $sale = screenSale($this->customer);
    screenSale(screenCustomer('株式会社別締日', ['closing_day' => '末日']));
    $this->actingAs($this->admin);

    Livewire::test(ListSales::class)
        ->callAction('issueDeliveryNotesForPeriod', data: ['from' => '2026-10-01', 'to' => '2026-10-31', 'closing_day' => '20日'])
        ->assertNotified('納品書レコードの作成が完了しました（1件）')
        ->callAction('issueDeliveryNotesForPeriod', data: ['from' => '2026-10-01', 'to' => '2026-10-31', 'closing_day' => '指定なし'])
        ->assertNotified('納品書レコードの作成が完了しました（1件）');
    expect($sale->fresh()->delivery_note_id)->not->toBeNull()->and(DeliveryNote::query()->count())->toBe(2);

    // the record buttons are buttons of the record page
    Livewire::test(ViewSale::class, ['record' => $sale->getRouteKey()])
        ->assertSeeInOrder(['納品書発行あり', '納品レコード更新', '請求レコード更新', 'ボタンの説明', '納品書発行あり', 'このレコードのみの納品書を発行します。']);
});

it('adds the checked sales to the note or invoice one of them is on with the 【新】 buttons of 06_SalesEntry.bundle.js', function () {
    $this->actingAs($this->admin); // the default view lists the sales one created
    $first = screenSale($this->customer);
    app(DeliveryNoteIssuer::class)->issue([$first]);
    $note = DeliveryNote::query()->sole();
    $second = screenSale($this->customer, ['delivery_due_on' => '2026-10-08', 'product_name' => '角2封筒']);

    Livewire::test(ListSales::class)
        ->assertSeeInOrder(['編集制限', '【新】選択して納品書レコード作成', 'sfk-menu-badge', '【新】選択して請求書レコード作成', 'sfk-menu-badge', '削除'], false)
        ->callAction('appendDeliveryNotes')
        ->assertNotified('レコードを選択してください。')
        ->set('selectedTableRecords', [(string) $first->id, (string) $second->id])
        ->callAction('appendDeliveryNotes')
        ->assertNotified('納品書レコードの作成が完了しました。');

    // the second sale joined the note of the first (no new note), its row added at the end
    expect(DeliveryNote::query()->count())->toBe(1)
        ->and($second->fresh()->delivery_note_id)->toBe($note->id)
        ->and($note->lines()->orderBy('sort')->pluck('product_name')->all())->toBe(['長3封筒', '角2封筒'])
        ->and($note->fresh()->subtotal)->toBe(20000);

    // one customer's sales on two notes are refused
    $other = screenSale($this->customer);
    app(DeliveryNoteIssuer::class)->issue([$other]);
    Livewire::test(ListSales::class)
        ->set('selectedTableRecords', [(string) $first->id, (string) $other->id])
        ->callAction('appendDeliveryNotes')
        ->assertNotified('売上先「株式会社得意先」のレコードに、異なる「納品書番号」が混在しています：'.$note->number.'、'.$other->fresh()->deliveryNote?->number.'。同じ売上先では同じ納品書番号のレコードのみ選択してください。');

    // invoices: every sale needs a note; then onto a new invoice, and a later sale onto that invoice
    $undelivered = screenSale($this->customer);
    Livewire::test(ListSales::class)
        ->set('selectedTableRecords', [(string) $first->id, (string) $undelivered->id])
        ->callAction('appendInvoices')
        ->assertNotified('納品書番号が未設定のレコードが 1 件あります。請求書を作成するには、すべての選択レコードに納品書番号が必要です。（未設定の売上No: '.$undelivered->sales_number.'）')
        ->set('selectedTableRecords', [(string) $first->id])
        ->callAction('appendInvoices', data: ['invoiced_on' => '2026-10-20'])
        ->assertNotified('請求書レコードの作成が完了しました。')
        ->set('selectedTableRecords', [(string) $first->id, (string) $second->id])
        ->callAction('appendInvoices')
        ->assertNotified('請求書レコードの作成が完了しました。');

    $invoice = Invoice::query()->sole();
    expect($invoice->invoiced_on?->toDateString())->toBe('2026-10-20')
        ->and($invoice->receipt)->not->toBeNull()
        ->and($invoice->lines()->orderBy('sort')->pluck('product_name')->all())->toBe(['長3封筒', '角2封筒'])
        ->and($second->fresh()->invoice_id)->toBe($invoice->id);
});

it('deletes the checked sales after two confirmations with 削除 of 06_SalesEntry.bundle.js, their rows going from the note', function () {
    $this->actingAs($this->admin); // the default view lists the sales one created
    $sale = screenSale($this->customer);
    $kept = screenSale($this->customer, ['product_name' => '角2封筒']);
    app(DeliveryNoteIssuer::class)->issue([$sale, $kept]);
    $note = DeliveryNote::query()->sole();

    Livewire::test(ListSales::class)
        ->set('selectedTableRecords', [(string) $sale->id])
        ->mountAction('deleteSales')
        ->assertMountedActionModalSee('選択された1件の売上データに関連する売上明細は、対応する納品書および請求書データから削除されます。')
        ->callMountedAction()
        ->assertActionMounted('confirmDeleteSales')
        ->assertMountedActionModalSee('この操作は取り消せません。実行してもよろしいですか？')
        ->callMountedAction()
        ->assertNotified('選択されたレコードの削除が完了しました。');

    expect(Sale::query()->whereKey($sale->id)->exists())->toBeFalse()
        ->and($note->lines()->pluck('product_name')->all())->toBe(['角2封筒'])
        ->and($note->fresh()->subtotal)->toBe(10000);

    // not for employees, even with 削除 on the app
    $app = BusinessApp::query()->where('code', 'sales-entry')->sole();
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true, AppAbility::Delete->column() => true]);
    $employee = grantDashboards(User::factory()->create(), 'erp');
    $app->space?->users()->attach($employee);
    $this->actingAs($employee);
    Livewire::test(ListSales::class)->assertActionHidden('deleteSales')->assertActionHidden('appendDeliveryNotes')->assertActionHidden('appendInvoices');
});

it('writes a changed sale address to its delivery note when an administrator saves (AutoReflectSalesDeliveryAddress.bundle.js)', function () {
    $sale = screenSale($this->customer);
    app(DeliveryNoteIssuer::class)->issue([$sale]);
    $note = DeliveryNote::query()->sole();
    expect($note->address)->toBe('大阪市北区1-1');
    $this->actingAs($this->admin);

    Livewire::test(EditSale::class, ['record' => $sale->getRouteKey()])
        ->fillForm(['other_address' => ['する'], 'manual_postal_code' => '100-0001', 'manual_address' => '東京都千代田区1-1'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($note->fresh()->only(['postal_code', 'address']))->toBe(['postal_code' => '100-0001', 'address' => '東京都千代田区1-1']);

    // a note without an address is left alone
    $note->update(['address' => null]);
    Livewire::test(EditSale::class, ['record' => $sale->getRouteKey()])
        ->fillForm(['manual_address' => '東京都港区2-2'])
        ->call('save');
    expect($note->fresh()->address)->toBeNull();
});

it('brings the listed 23-2 records up to date with 「[新]一括更新」, not on 売掛一覧 (23-2_AccountsReceivableManagement.bundle.js)', function () {
    screenInvoice($this->customer);
    $this->actingAs($this->admin);

    Livewire::test(ListReceivables::class)->assertActionDoesNotExist('refreshListedReceivables');

    Livewire::test(ListCycleReceivables::class)
        ->set('listView', salesView('receivable-cycle', '売掛一覧'))
        ->mountAction('refreshListedReceivables')
        ->assertNotified('売掛一覧はレコード数が多いため一覧条件を変更の上実行してください。')
        ->set('listView', salesView('receivable-cycle', '売掛一覧 - 20日締'))
        ->callAction('refreshListedReceivables')
        ->assertNotified('更新が完了しました。（'.ReceivableMonth::query()->ofBasis(ReceivableBasis::Cycle)->count().'件）');
});
