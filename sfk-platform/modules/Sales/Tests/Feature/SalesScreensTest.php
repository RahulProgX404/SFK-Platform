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

    Livewire::test(ListSales::class)
        ->assertCanSeeTableRecords([$sale])
        ->callTableBulkAction('createDeliveryNotes', [$sale])
        ->assertNotified('納品書を作成しました（1件）')
        ->callTableBulkAction('createInvoices', [$sale], data: ['invoiced_on' => '2026-10-20'])
        ->assertNotified('請求書を作成しました（1件）');

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
