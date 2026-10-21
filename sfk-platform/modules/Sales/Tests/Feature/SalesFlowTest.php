<?php

/*
 * The order-to-cash flow behind 06 / 10 / 11 / 21 / 23 / 23-2 (SYSTEM_BRAIN BP-06/07/08): line tax and 月度 of a sale
 * (BR-TX-05, BR-AR-01/02), the job's sales totals (Customine 06 page 25/26), delivery notes and invoices per customer
 * (BR-SA-08..14, BR-TX-04), receipts (BR-AR-04/06) and the receivables recalculated from them (BR-AR-03/05, BR-TX-06).
 */

use Carbon\CarbonImmutable;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Order;
use Modules\Sales\Application\Documents\DeliveryNoteIssuer;
use Modules\Sales\Application\Documents\InvoiceIssuer;
use Modules\Sales\Application\Receivables\ReceivableLedger;
use Modules\Sales\Application\SalesActionNotAllowed;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Domain\Sales\SaleChecks;
use Modules\Sales\Models\DeliveryNote;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\Receipt;
use Modules\Sales\Models\ReceivableMonth;
use Modules\Sales\Models\Sale;

function salesCustomer(string $name = '株式会社得意先', array $attributes = []): Partner
{
    return Partner::query()->create([
        'name' => $name, 'short_name' => mb_substr($name, 0, 4), 'is_customer' => true,
        'closing_day' => '20日', 'rounding' => '四捨五入', 'postal_code' => '530-0001', 'address' => '大阪市北区1-1',
        ...$attributes,
    ]);
}

function makeSale(Partner $customer, array $attributes = []): Sale
{
    return Sale::query()->create([
        'customer_id' => $customer->id, 'sold_on' => '2026-10-05', 'delivery_due_on' => '2026-10-06', 'product_name' => '長3封筒',
        'quantity' => '1000', 'unit_price' => '12.345', 'amount' => 12345, ...$attributes,
    ]);
}

/** @return array<string, array<string, int>> YYYY-MM => figures */
function receivables(Partner $customer, ReceivableBasis $basis): array
{
    return ReceivableMonth::query()->ofBasis($basis)->where('customer_id', $customer->id)->orderBy('year_month')->get()
        ->mapWithKeys(fn (ReceivableMonth $month): array => [$month->year_month => $month->only(['previous_balance', 'received', 'sales_amount', 'tax_amount', 'closing_balance'])])
        ->all();
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00'));
});

it('numbers a sale and copies the customer terms: line tax by 端数区分, 月度 by the closing day', function () {
    $customer = salesCustomer();
    $sale = makeSale($customer, ['sold_on' => '2026-10-25']);

    expect($sale->sales_number)->toBe(str_pad((string) $sale->id, 7, '0', STR_PAD_LEFT))
        ->and($sale->closing_day)->toBe('20日')
        ->and($sale->customer_rounding)->toBe('四捨五入')
        ->and($sale->tax)->toBe(1235) // 12,345 × 10% = 1,234.5 → 四捨五入
        ->and($sale->amountWithTax())->toBe(13580)
        ->and($sale->billing_month)->toBe('2026-11'); // 25日 > 20日締 → next month (BR-AR-01)

    $sale->update(['closing_day_override' => '随時請求', 'billing_date' => '2026-10-31', 'tax_rate' => '8%']);
    expect($sale->billing_month)->toBe('2026-10')->and($sale->isAdHoc())->toBeTrue()->and($sale->tax)->toBe(988);

    $sale->update(['closing_day_override' => null]);
    expect($sale->fresh()->billing_date)->toBeNull(); // 請求日付 only for 随時請求 (BR-SA-06)
});

it('keeps the job sales totals equal to the sum of its sales and checks over-selling', function () {
    $customer = salesCustomer();
    $order = Order::query()->create(['customer_id' => $customer->id, 'quantity' => '1000', 'sales_amount' => 20000]);
    $first = makeSale($customer, ['order_id' => $order->id, 'quantity' => '600', 'amount' => 12000]);
    makeSale($customer, ['order_id' => $order->id, 'quantity' => '300', 'amount' => 6000]);

    $order->refresh();
    expect($order->sold_quantity)->toBe('900.0000')->and((int) $order->sold_amount)->toBe(18000)->and($order->sales_remaining)->toBe('100.0000');

    $first->delete();
    expect($order->fresh()->sold_quantity)->toBe('300.0000');

    // BR-SA-02 / 03 / 04
    expect(SaleChecks::problems('通常売上', '200', 1000, '100', 6000, 20000))->toHaveCount(1)
        ->and(SaleChecks::problems('通常売上', '50', 15000, '100', 6000, 20000))->toHaveCount(1)
        ->and(SaleChecks::problems('通常売上', '10', 100, '0', 6000, 20000)[0])->toContain('全数売上済み')
        ->and(SaleChecks::problems('マイナス処理', '10', -100, '0', 6000, 20000))->toBe([])
        ->and(SaleChecks::problems('通常売上', '50', 1000, '100', 6000, 20000))->toBe([]);
});

it('creates one delivery note per customer with tax per rate on the note, then one invoice with the balances', function () {
    $customer = salesCustomer();
    $other = salesCustomer('有限会社別会社', ['rounding' => null]);
    $a = makeSale($customer, ['delivery_due_on' => '2026-10-08', 'amount' => 1005, 'tax_rate' => '10%']);
    $b = makeSale($customer, ['delivery_due_on' => '2026-10-07', 'amount' => 1005, 'tax_rate' => '10%']);
    $c = makeSale($customer, ['delivery_due_on' => '2026-10-09', 'amount' => 1000, 'tax_rate' => '8%']);
    $d = makeSale($other, ['amount' => 999]);

    $notes = app(DeliveryNoteIssuer::class)->issue(Sale::query()->get());
    expect($notes)->toHaveCount(2);

    $note = DeliveryNote::query()->where('customer_id', $customer->id)->sole();
    expect($note->number)->toBe('0000001')
        ->and($note->registration_number)->toBe('T-1200-0102-0414')
        ->and($note->address)->toBe('大阪市北区1-1')
        ->and($note->lines->pluck('sales_number')->all())->toBe([$b->sales_number, $a->sales_number, $c->sales_number]) // by 納品予定日
        ->and($note->subtotal)->toBe(3010)
        ->and($note->tax_total)->toBe(201 + 80) // round(2,010 × 10%) + round(1,000 × 8%) on the note, not per line (1,005 → 101 each)
        ->and($a->fresh()->delivery_note_id)->toBe($note->id);

    expect(fn () => app(DeliveryNoteIssuer::class)->issue([$a->fresh()]))->toThrow(SalesActionNotAllowed::class);

    // receipts of last cycle feed the next invoice: an earlier sale of September, paid in October
    $september = makeSale($customer, ['sold_on' => '2026-09-10', 'amount' => 50000]);
    $receipt = Receipt::query()->create(['customer_id' => $customer->id, 'closing_day' => '20日']);
    $receipt->lines()->create(['received_on' => '2026-10-15', 'amount' => 54000, 'fee' => 660, 'discount' => 340]);

    expect(fn () => app(InvoiceIssuer::class)->issue([$september->fresh()]))->toThrow(SalesActionNotAllowed::class); // no delivery note

    $invoices = app(InvoiceIssuer::class)->issue(Sale::query()->whereNotNull('delivery_note_id')->where('customer_id', $customer->id)->get());
    $invoice = $invoices[0];
    expect($invoice->number)->toBe('0000001')
        ->and($invoice->invoiced_on->toDateString())->toBe('2026-11-20') // 20日締: that day of the current month (BR-SA-10)
        ->and($invoice->closing_day)->toBe('20日')
        ->and($invoice->billing_month)->toBe('2026-11')
        ->and($invoice->previous_amount)->toBe(55000) // cycle 2026-10: 前月売掛残 = September 50,000 + tax 5,000
        ->and($invoice->received_amount)->toBe(55000) // 54,000 + 660 + 340 (BR-AR-04)
        ->and($invoice->carried_amount)->toBe(0)
        ->and($invoice->subtotal)->toBe(3010)
        ->and($invoice->billed_total)->toBe(0 + 3010 + 281)
        ->and($invoice->lines->first()->slip_number)->toBe($note->number)
        ->and($invoice->receipt)->not->toBeNull()
        ->and($invoice->receipt->billed_amount)->toBe(3291)
        ->and(Invoice::query()->count())->toBe(1);
});

it('recalculates the receivables by calendar month and by billing cycle from sales and receipts', function () {
    $customer = salesCustomer();
    makeSale($customer, ['sold_on' => '2026-09-25', 'amount' => 10000]); // calendar 09, cycle 10 (25日 > 20日)
    makeSale($customer, ['sold_on' => '2026-10-05', 'amount' => 20000]); // calendar 10, cycle 10
    $receipt = Receipt::query()->create(['customer_id' => $customer->id, 'closing_day' => '20日']);
    $receipt->lines()->create(['received_on' => '2026-10-25', 'amount' => 11000]); // calendar 10, cycle 11

    expect(receivables($customer, ReceivableBasis::Calendar))->toBe([
        '2026-09' => ['previous_balance' => 0, 'received' => 0, 'sales_amount' => 10000, 'tax_amount' => 1000, 'closing_balance' => 11000],
        '2026-10' => ['previous_balance' => 11000, 'received' => 11000, 'sales_amount' => 20000, 'tax_amount' => 2000, 'closing_balance' => 22000],
        '2026-11' => ['previous_balance' => 22000, 'received' => 0, 'sales_amount' => 0, 'tax_amount' => 0, 'closing_balance' => 22000], // open balance carries (BR-AR-05)
    ])->and(receivables($customer, ReceivableBasis::Cycle))->toBe([
        '2026-10' => ['previous_balance' => 0, 'received' => 0, 'sales_amount' => 30000, 'tax_amount' => 3000, 'closing_balance' => 33000],
        '2026-11' => ['previous_balance' => 33000, 'received' => 11000, 'sales_amount' => 0, 'tax_amount' => 0, 'closing_balance' => 22000],
    ]);

    // 消費税端数調整 is kept and counts; deleting a sale recalculates (legacy 23-2 missed it)
    $october = ReceivableMonth::query()->ofBasis(ReceivableBasis::Calendar)->where('year_month', '2026-10')->sole();
    $october->update(['tax_adjustment' => -1]);
    expect(receivables($customer, ReceivableBasis::Calendar)['2026-10']['closing_balance'])->toBe(21999);

    Sale::query()->whereDate('sold_on', '2026-10-05')->sole()->delete();
    expect(receivables($customer, ReceivableBasis::Cycle)['2026-10']['sales_amount'])->toBe(10000)
        ->and(receivables($customer, ReceivableBasis::Calendar)['2026-10']['closing_balance'])->toBe(-1);

    $receipt->delete();
    app(ReceivableLedger::class)->refresh($customer->id);
    expect(array_keys(receivables($customer, ReceivableBasis::Calendar)))->toBe(['2026-09', '2026-10', '2026-11']);
});

it('takes a deleted sale off its delivery note and invoice', function () {
    $customer = salesCustomer();
    $keep = makeSale($customer, ['amount' => 1000]);
    $drop = makeSale($customer, ['amount' => 2000]);
    app(DeliveryNoteIssuer::class)->issue([$keep, $drop]);
    app(InvoiceIssuer::class)->issue(Sale::query()->get());

    $drop->fresh()->delete();

    $note = DeliveryNote::query()->sole();
    $invoice = Invoice::query()->sole();
    expect($note->lines()->count())->toBe(1)->and($note->subtotal)->toBe(1000)
        ->and($invoice->lines()->count())->toBe(1)->and($invoice->subtotal)->toBe(1000)->and($invoice->tax_total)->toBe(100);
});
