<?php

/*
 * The purchase-to-pay flow behind 05 / 07 / 20 / 23. 買掛管理 (SYSTEM_BRAIN BP-05): numbering and 仕入金額合計 of a purchase
 * (BR-PU-01/03), the job's 仕入 costs recalculated from its purchases (Customine 05 pages 9/14), the payables recalculated
 * from purchases and payments with the supplier's tax rule and the category breakdown (BR-PU-04/06/07/08, BR-TX-07), and
 * the approval of consumable orders (BR-PU-09).
 */

use App\Models\User;
use Carbon\CarbonImmutable;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Order;
use Modules\Purchasing\Application\ConsumableOrders\ConsumableOrderWorkflow;
use Modules\Purchasing\Application\PurchasingActionNotAllowed;
use Modules\Purchasing\Domain\ConsumableOrders\ConsumableOrderStatus;
use Modules\Purchasing\Models\ConsumableOrder;
use Modules\Purchasing\Models\PayableMonth;
use Modules\Purchasing\Models\Payment;
use Modules\Purchasing\Models\Purchase;

function flowSupplier(string $name = '紙商事', array $attributes = []): Partner
{
    return Partner::query()->create([
        'name' => $name, 'short_name' => mb_substr($name, 0, 4), 'is_supplier' => true,
        'purchase_category' => '03.材料仕入', 'supplier_tax_mode' => '外', 'supplier_rounding' => '四捨五入',
        ...$attributes,
    ]);
}

function flowPurchase(Partner $supplier, array $attributes = []): Purchase
{
    return Purchase::query()->create(['supplier_id' => $supplier->id, 'purchased_on' => '2026-10-05', 'product_name' => 'クラフト紙', 'amount' => 10000, ...$attributes]);
}

function flowPayment(string $paidOn, array $lines): Payment
{
    $payment = Payment::query()->create(['paid_on' => $paidOn]);
    foreach ($lines as $sort => $line) {
        $payment->lines()->create([...$line, 'sort' => $sort]);
    }

    return $payment->fresh();
}

/** @return array<string, array<string, int>> YYYY-MM => figures */
function payables(Partner $supplier, array $only = ['previous_balance', 'paid', 'purchase_amount', 'tax_amount', 'month_total', 'closing_balance']): array
{
    return PayableMonth::query()->where('supplier_id', $supplier->id)->orderBy('year_month')->get()
        ->mapWithKeys(fn (PayableMonth $month): array => [$month->year_month => $month->only($only)])
        ->all();
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-10 10:00'));
});

it('numbers a purchase and totals it with its cost components', function () {
    $purchase = flowPurchase(flowSupplier(), ['cutting_amount' => 500, 'plate_amount' => 300, 'stored_plate_amount' => null, 'spot_color_amount' => 200]);

    expect($purchase->purchase_number)->toBe('0000001')
        ->and($purchase->total)->toBe(11000) // 金額 + 断裁 + 版代 + 置き版代 + 特色 (BR-PU-03)
        ->and($purchase->stored_plate_amount)->toBe(0)
        ->and($purchase->effectiveCategory()?->value)->toBe('03.材料仕入');

    $purchase->update(['category' => '05.外注加工']);
    expect($purchase->fresh()->effectiveCategory()?->value)->toBe('05.外注加工'); // per-line override (BR-PU-04)
    expect(flowPurchase(flowSupplier('別商事'))->purchase_number)->toBe('0000002');
});

it('keeps the job purchase costs equal to the sums of its purchases', function () {
    $supplier = flowSupplier();
    $order = Order::query()->create(['customer_id' => Partner::query()->create(['name' => '得意先', 'short_name' => '得意', 'is_customer' => true])->id,
        'quantity' => '1000', 'sales_amount' => 50000]);
    $first = flowPurchase($supplier, ['order_id' => $order->id, 'cutting_amount' => 1000]);
    flowPurchase($supplier, ['order_id' => $order->id, 'amount' => 5000, 'plate_amount' => 2000]);

    $order->refresh();
    expect($order->purchase_amount)->toBe(15000)
        ->and($order->purchase_cutting_amount)->toBe(1000)
        ->and($order->purchase_plate_amount)->toBe(2000)
        ->and($order->purchase_cost_total)->toBe(18000);

    $other = Order::query()->create(['customer_id' => $order->customer_id, 'quantity' => '10', 'sales_amount' => 100]);
    $first->update(['order_id' => $other->id]);
    expect($order->fresh()->purchase_cost_total)->toBe(7000)->and($other->fresh()->purchase_cost_total)->toBe(11000);

    $first->delete();
    expect($other->fresh()->purchase_amount)->toBe(0)->and($other->fresh()->purchase_cost_total)->toBe(0);
});

it('rolls the payables forward from purchases and payments with the supplier tax rule', function () {
    $supplier = flowSupplier();
    flowPurchase($supplier, ['cutting_amount' => 500]); // October: 仕入額 10,500
    flowPurchase($supplier, ['purchased_on' => '2026-10-20', 'amount' => 1001, 'category' => '04.材料仕入(否インボス)']);
    $payment = flowPayment('2026-11-25', [['supplier_id' => $supplier->id, 'amount' => 10000], ['supplier_id' => $supplier->id, 'amount' => 1000, 'method' => '5.相殺']]);

    // 外 / 四捨五入: tax = round(11,501 × 10%) = 1,150; 当月合計 = 12,651
    expect(payables($supplier))->toBe([
        '2026-10' => ['previous_balance' => 0, 'paid' => 0, 'purchase_amount' => 11501, 'tax_amount' => 1150, 'month_total' => 12651, 'closing_balance' => 12651],
        '2026-11' => ['previous_balance' => 12651, 'paid' => 11000, 'purchase_amount' => 0, 'tax_amount' => 0, 'month_total' => 0, 'closing_balance' => 1651],
        '2026-12' => ['previous_balance' => 1651, 'paid' => 0, 'purchase_amount' => 0, 'tax_amount' => 0, 'month_total' => 0, 'closing_balance' => 1651],
    ]);
    $october = PayableMonth::query()->where('supplier_id', $supplier->id)->where('year_month', '2026-10')->sole();
    expect($october->cutting_amount)->toBe(500)
        ->and($october->getAttribute('cat_materials'))->toBe(11501) // the (否インボス) line counts in 材料仕入 too
        ->and($october->materialsAndGoods())->toBe(11501)
        ->and($payment->total)->toBe(11000)
        ->and($payment->lines->pluck('withdrawn_on')->map->toDateString()->all())->toBe(['2026-11-25', '2026-11-25']);

    // 消費税端数調整 is kept and rolls into the following months
    $october->update(['tax_adjustment' => -1]);
    expect(payables($supplier)['2026-12']['closing_balance'])->toBe(1650);

    // moving the payment run to October moves its rows (出金日 = 支払日, BR-PU-08)
    $payment->update(['paid_on' => '2026-10-31']);
    expect(payables($supplier, ['paid', 'closing_balance']))->toBe([
        '2026-10' => ['paid' => 11000, 'closing_balance' => 1650],
        '2026-11' => ['paid' => 0, 'closing_balance' => 1650],
        '2026-12' => ['paid' => 0, 'closing_balance' => 1650],
    ]);

    $payment->delete();
    expect(payables($supplier, ['paid', 'closing_balance'])['2026-12'])->toBe(['paid' => 0, 'closing_balance' => 12650]);
});

it('takes the tax out of 内 purchases and none for 無, and maps the freight categories', function () {
    $inclusive = flowSupplier('内税商事', ['supplier_tax_mode' => '内', 'supplier_rounding' => '切捨', 'purchase_category' => '13.原運送代']);
    flowPurchase($inclusive, ['amount' => 11000]);
    $none = flowSupplier('非課税商事', ['supplier_tax_mode' => '無', 'purchase_category' => '23.運送代']);
    flowPurchase($none, ['amount' => 11000]);

    // 内: tax = 11,000 − 11,000 / 1.1 = 1,000, 当月合計 = 仕入額
    expect(payables($inclusive)['2026-10'])->toMatchArray(['tax_amount' => 1000, 'month_total' => 11000, 'closing_balance' => 11000])
        ->and(payables($none)['2026-10'])->toMatchArray(['tax_amount' => 0, 'month_total' => 11000])
        ->and(PayableMonth::query()->where('supplier_id', $inclusive->id)->where('year_month', '2026-10')->sole()->getAttribute('cat_cost_delivery'))->toBe(11000)
        ->and(PayableMonth::query()->where('supplier_id', $none->id)->where('year_month', '2026-10')->sole()->getAttribute('cat_delivery'))->toBe(11000);
});

it('moves a purchase to the payables of its new supplier and month', function () {
    $supplier = flowSupplier();
    $other = flowSupplier('他商事');
    $purchase = flowPurchase($supplier);

    $purchase->update(['supplier_id' => $other->id, 'purchased_on' => '2026-11-02']);
    expect(payables($supplier))->toBe([])
        ->and(array_keys(payables($other)))->toBe(['2026-11', '2026-12']);

    $purchase->delete();
    expect(payables($other))->toBe([]);
});

it('takes a consumable order through 承認依頼 and 承認する', function () {
    $creator = User::factory()->create(['name' => '発注 太郎']);
    $employee = User::factory()->create(['name' => '他の社員']);
    $admin = User::factory()->admin()->create(['name' => '管理者']);
    $this->actingAs($creator);
    $order = ConsumableOrder::query()->create(['ordered_on' => '2026-12-01', 'supplier_id' => flowSupplier()->id]);
    $workflow = app(ConsumableOrderWorkflow::class);

    expect($order->status)->toBe(ConsumableOrderStatus::Unprocessed)
        ->and($workflow->canAdvance($order, $employee))->toBeFalse();

    $workflow->advance($order, $creator, '20. 消耗品発注', 'http://localhost/order');
    expect($order->fresh()->status)->toBe(ConsumableOrderStatus::Checking)
        ->and($admin->notifications()->count())->toBe(1);

    expect(fn () => $workflow->advance($order->fresh(), $creator, '20. 消耗品発注', 'http://localhost/order'))->toThrow(PurchasingActionNotAllowed::class);

    $workflow->advance($order->fresh(), $admin, '20. 消耗品発注', 'http://localhost/order');
    expect($order->fresh()->status)->toBe(ConsumableOrderStatus::Approved)
        ->and($creator->notifications()->count())->toBe(1)
        ->and($workflow->canAdvance($order->fresh(), $admin))->toBeFalse();
});
