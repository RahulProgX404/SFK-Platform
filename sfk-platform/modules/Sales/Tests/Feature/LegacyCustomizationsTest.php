<?php

/*
 * The legacy customizations ported without their defects (docs/legacy-customizations.md): derived job totals (1.2/1.3),
 * the sales summary (2.1), delivery notes and invoices made twice (2.2/2.3), deleting sales with their documents (2.4),
 * optimistic locking (R5) and 「ファイルに書き出す」 (8).
 */

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Application\Records\RecordDeletion;
use Modules\Core\Application\Records\RecordVersion;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;
use Modules\MasterData\Models\Machine;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Order;
use Modules\Production\Models\DailyReport;
use Modules\Purchasing\Models\Purchase;
use Modules\Sales\Application\Documents\DeliveryNoteIssuer;
use Modules\Sales\Application\Documents\InvoiceIssuer;
use Modules\Sales\Application\Receivables\ReceivableLedger;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\Sales\Filament\Resources\DeliveryNotes\Pages\EditDeliveryNote;
use Modules\Sales\Filament\Resources\Sales\Pages\ListSales;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Models\DeliveryNote;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\Receipt;
use Modules\Sales\Models\ReceivableMonth;
use Modules\Sales\Models\Sale;

function lcCustomer(string $name = '株式会社得意先'): Partner
{
    return Partner::query()->create(['name' => $name, 'short_name' => mb_substr($name, 0, 4), 'is_customer' => true, 'closing_day' => '20日', 'rounding' => '切捨']);
}

function lcSale(Partner $customer, array $attributes = []): Sale
{
    return Sale::query()->create([
        'customer_id' => $customer->id, 'sold_on' => '2026-10-05', 'delivery_due_on' => '2026-10-06', 'product_name' => '長3封筒',
        'quantity' => '1000', 'unit_price' => '10', 'amount' => 10000, ...$attributes,
    ]);
}

/** 06 / 10 / 11 / 21 published in a space (registry sync), as アプリ設定 adds them. */
function lcPublish(string ...$resources): void
{
    $codes = [];
    $defaults = [];
    foreach ($resources as $resource) {
        $codes[$resource::appCode()] = $resource::appName();
        $defaults[$resource::appCode()] = $resource::appSettingDefaults();
    }
    app(AppLifecycle::class)->syncWithCode($codes, $defaults);
    $space = Space::query()->firstOrCreate(['panel_id' => 'erp', 'name' => '売上スペース']);
    BusinessApp::query()->whereIn('code', array_keys($codes))->get()->each(function (BusinessApp $app) use ($space): void {
        $app->forceFill(['status' => AppStatus::Published])->save();
        $app->spaces()->syncWithoutDetaching([$space->id]);
    });
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00'));
});

it('keeps 仕入原価計, 社内原価 and 利益 of a job current from purchases, daily reports and ALL its sales, without opening it (1.2 / 1.3)', function () {
    $customer = lcCustomer();
    $order = Order::query()->create(['customer_id' => $customer->id, 'quantity' => '2000', 'sales_amount' => 60000]);
    $supplier = Partner::query()->create(['name' => '紙商事', 'short_name' => '紙商事', 'is_supplier' => true, 'supplier_tax_mode' => '外']);

    Purchase::query()->create(['supplier_id' => $supplier->id, 'order_id' => $order->id, 'purchased_on' => '2026-10-01', 'product_name' => 'クラフト紙', 'amount' => 10000, 'cutting_amount' => 1000]);
    $worker = User::factory()->create(['work_rate' => 1200]);
    $machine = Machine::query()->create(['name' => '製袋機1号', 'code' => 'M01', 'machine_rate' => '3000']);
    $report = DailyReport::query()->create(['worked_on' => '2026-10-02', 'user_id' => $worker->id, 'order_id' => $order->id, 'machine_id' => $machine->id,
        'started_at' => '08:00', 'ended_at' => '12:00', 'break_time' => '00:00', 'produced' => '1000', 'crew' => '1']);
    $first = lcSale($customer, ['order_id' => $order->id, 'amount' => 20000]);
    lcSale($customer, ['order_id' => $order->id, 'amount' => 30000]);

    $order->refresh();
    $cost = 11000 + $report->internal_cost;
    expect($order->purchase_amount + $order->purchase_cutting_amount)->toBe(11000)
        ->and($order->internal_cost)->toBe($report->internal_cost)
        ->and($order->actual_cost_total)->toBe($cost)
        ->and($order->sold_amount)->toBe(50000) // every sale of the job, without tax
        ->and($order->profit)->toBe(50000 - $cost);

    // deleting a sale moves the job's profit and the receivables (the legacy delete left 04 / 23 alone)
    app(RecordDeletion::class)->delete($first->fresh());
    expect($order->fresh()->profit)->toBe(30000 - $cost)
        ->and(ReceivableMonth::query()->ofBasis(ReceivableBasis::Calendar)->where('customer_id', $customer->id)->where('year_month', '2026-10')->value('sales_amount'))->toBe(30000);
});

it('takes 粗利 of the sales summary without tax, as 利益 of the job (2.1)', function () {
    Filament::setCurrentPanel('erp');
    lcPublish(SaleResource::class);
    $this->actingAs(User::factory()->superAdmin()->create());
    $order = Order::query()->create(['customer_id' => lcCustomer()->id, 'quantity' => '1000', 'sales_amount' => 10000]);
    Purchase::query()->create(['supplier_id' => Partner::query()->create(['name' => '紙商事', 'short_name' => '紙', 'is_supplier' => true])->id,
        'order_id' => $order->id, 'purchased_on' => '2026-10-01', 'product_name' => 'クラフト紙', 'amount' => 4000]);
    lcSale($order->customer, ['order_id' => $order->id, 'amount' => 10000]); // tax 1,000

    $rows = array_column(Livewire::test(ListSales::class)->instance()->salesSummary()['rows'], 2, 0);
    expect($rows['売上金額（税込）'])->toBe(11000)
        ->and($rows['粗利'])->toBe(10000 - 4000)
        ->and($rows['粗利率'])->toBe(60.0);
});

it('leaves a delivery note and an invoice as they are when made again from the same sales, one per customer id (2.2 / 2.3)', function () {
    // names are unique in 01. 取引先マスタ, and the documents group by the customer's id, never by its name
    $customer = lcCustomer('株式会社同名');
    $namesake = lcCustomer('株式会社同名 大阪支店');
    $sales = [lcSale($customer), lcSale($customer, ['amount' => 5000]), lcSale($namesake, ['amount' => 7000])];

    $notes = app(DeliveryNoteIssuer::class)->issueOrAppend($sales);
    $again = app(DeliveryNoteIssuer::class)->issueOrAppend(array_map(fn (Sale $sale): Sale => $sale->fresh(), $sales));

    expect($notes)->toHaveCount(2)
        ->and(DeliveryNote::query()->count())->toBe(2)
        ->and(collect($again)->pluck('id')->sort()->values()->all())->toBe(collect($notes)->pluck('id')->sort()->values()->all())
        ->and(DeliveryNote::query()->where('customer_id', $customer->id)->sole()->lines()->count())->toBe(2)
        ->and(DeliveryNote::query()->where('customer_id', $customer->id)->sole()->subtotal)->toBe(15000);

    app(InvoiceIssuer::class)->issueOrAppend(Sale::query()->get());
    app(InvoiceIssuer::class)->issueOrAppend(Sale::query()->get());
    expect(Invoice::query()->count())->toBe(2)
        ->and(Invoice::query()->where('customer_id', $customer->id)->sole()->lines()->count())->toBe(2)
        ->and(Receipt::query()->count())->toBe(2);
});

it('carries a credit into the invoice from the one opening-balance function, dated by its period whatever the day (2.3)', function () {
    $customer = lcCustomer();
    // September (月度 09): 10,000 + tax, overpaid by 2,000 → a credit of −2,000 going into 月度 10
    lcSale($customer, ['sold_on' => '2026-09-10', 'delivery_due_on' => '2026-09-10']);
    $receipt = Receipt::query()->create(['customer_id' => $customer->id]);
    $receipt->lines()->create(['received_on' => '2026-09-15', 'amount' => 13000, 'method' => '振込']);
    $october = lcSale($customer);
    app(DeliveryNoteIssuer::class)->issue([$october]);

    $opening = app(ReceivableLedger::class)->openingBalance($customer->id, ReceivableBasis::Cycle, YearMonth::of(2026, 10));
    $invoice = app(InvoiceIssuer::class)->issue([$october->fresh()])[0];
    expect($opening)->toBe(-2000)
        ->and($invoice->previous_amount)->toBe(-2000)
        ->and($invoice->invoiced_on->toDateString())->toBe('2026-10-20');

    // made on another day: the same date
    $invoice->delete();
    $this->travelTo(CarbonImmutable::parse('2026-12-03 09:00'));
    expect(app(InvoiceIssuer::class)->issue([$october->fresh()])[0]->invoiced_on->toDateString())->toBe('2026-10-20');
});

it('deletes the documents a sale empties, blocks it over a paid invoice, and rolls back as a whole (2.4)', function () {
    $customer = lcCustomer();
    $sale = lcSale($customer);
    $other = lcSale($customer, ['amount' => 5000]);
    app(DeliveryNoteIssuer::class)->issue([$sale, $other]);
    $invoice = app(InvoiceIssuer::class)->issue([$sale->fresh()])[0];
    $invoice->receipt->lines()->create(['received_on' => '2026-11-01', 'amount' => 0, 'fee' => 330, 'method' => '振込']);

    // a fee is a payment: the invoice would go, so the sale stays
    expect(app(RecordDeletion::class)->delete($sale->fresh()))->toContain('請求書番号: '.$invoice->number)
        ->and(Sale::query()->whereKey($sale->id)->exists())->toBeTrue()
        ->and(app(RecordDeletion::class)->impact(Sale::class, [$sale->id]))->toContain(
            ['app' => '21. 売入金管理（売上）', 'text' => '入金のある請求書（'.$invoice->number.'）の売上は削除されません', 'blocks' => true],
        );

    // an error part way: nothing is deleted
    $invoice->receipt->lines()->delete();
    Sale::deleted(fn () => throw new RuntimeException('failure after the cascade'));
    expect(fn () => app(RecordDeletion::class)->delete($sale->fresh()))->toThrow(RuntimeException::class);
    expect(Sale::query()->count())->toBe(2)
        ->and(DeliveryNote::query()->sole()->lines()->count())->toBe(2)
        ->and(Invoice::query()->count())->toBe(1)
        ->and(Receipt::query()->count())->toBe(1);
});

it('refuses to overwrite a record someone else saved since it was opened (R5)', function () {
    Filament::setCurrentPanel('erp');
    lcPublish(DeliveryNoteResource::class, SaleResource::class);
    $this->actingAs(User::factory()->superAdmin()->create());
    app(DeliveryNoteIssuer::class)->issue([lcSale(lcCustomer())]);
    $note = DeliveryNote::query()->sole();

    $page = Livewire::test(EditDeliveryNote::class, ['record' => $note->getRouteKey()])->fillForm(['notes' => '私の変更']);
    $this->travel(1)->minutes();
    $note->update(['notes' => '他の人の変更']);

    $page->call('save')->assertNotified(RecordVersion::CONFLICT);
    expect($note->fresh()->notes)->toBe('他の人の変更');

    // opened afresh: saved
    Livewire::test(EditDeliveryNote::class, ['record' => $note->getRouteKey()])->fillForm(['notes' => '私の変更'])->call('save')->assertHasNoFormErrors();
    expect($note->fresh()->notes)->toBe('私の変更');
});

it('exports the list as CSV to users with ファイル書き出し, with its stored columns only (8)', function () {
    Filament::setCurrentPanel('erp');
    lcPublish(SaleResource::class);
    lcSale(lcCustomer());
    $this->actingAs(User::factory()->superAdmin()->create());

    $columns = Livewire::test(ListSales::class)->instance()->exportColumns();
    expect(array_key_first($columns))->toBe('id')
        ->and($columns)->toHaveKeys(['sold_on', 'customer_id', 'amount'])
        ->and(array_keys($columns))->each->toBeIn(Schema::getColumnListing('sales'));
    Livewire::test(ListSales::class)->callAction('exportCsv')->assertFileDownloaded('06. 売上入力_20261110_100000.csv');

    // without ファイル書き出し: no export
    $app = BusinessApp::query()->where('code', SaleResource::appCode())->sole();
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
    $employee = grantDashboards(User::factory()->create(), 'erp');
    $app->spaces->first()?->users()->attach($employee);
    $this->actingAs($employee);
    Livewire::test(ListSales::class)->assertActionHidden('exportCsv');
});
