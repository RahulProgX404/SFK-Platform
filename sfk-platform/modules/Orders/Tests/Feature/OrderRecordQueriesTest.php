<?php

/*
 * The 受注 record page reads its 取引先 (得意先, 仕入先 and the 仕入先 of every 用紙 / 印刷 / 加工 row) with one query
 * and its users with one, on every request of the page (Core RelationBatch), and works out ‹ › once when it opens
 * (performance pass 2026-11-20, docs/performance.md).
 */

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Core\Application\Records\RelationBatch;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Filament\Resources\Orders\Pages\ViewOrder;
use Modules\Orders\Models\Order;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
    $this->supplier = fn (string $name): Partner => Partner::query()->create(['name' => $name, 'short_name' => mb_substr($name, 0, 4), 'is_supplier' => true]);
    $customer = Partner::query()->create(['name' => '得意先株式会社', 'short_name' => '得意先', 'is_customer' => true]);
    $this->order = Order::query()->create([
        'customer_id' => $customer->id, 'quantity' => '1000',
        'plate_supplier_id' => ($this->supplier)('刷版屋')->id, 'manufacturer_id' => ($this->supplier)('製造所')->id,
        'designer_id' => $this->admin->id,
    ]);
    $this->order->papers()->create(['paper_name' => '上質紙', 'pricing' => '枚', 'sheets' => '100', 'unit_price' => '5', 'supplier_id' => ($this->supplier)('紙屋')->id]);
    $this->order->papers()->create(['paper_name' => 'クラフト', 'pricing' => '枚', 'sheets' => '100', 'unit_price' => '5', 'supplier_id' => ($this->supplier)('紙屋2')->id]);
    $this->order->printings()->create(['supplier_id' => ($this->supplier)('印刷屋')->id]);
    $this->order->papers()->create(['paper_name' => '仕入先なし', 'pricing' => '枚', 'sheets' => '1', 'unit_price' => '1']);
});

it('loads the belongs-to relations of a record and of its rows with one query per table', function () {
    $order = Order::query()->findOrFail($this->order->id);

    DB::flushQueryLog();
    DB::enableQueryLog();
    RelationBatch::load($order, ['customer', 'plateSupplier', 'manufacturer', 'dataSupplier', 'papers.supplier', 'printings.supplier', 'designer', 'creator']);
    $partnerQueries = count(array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'from "partners"')));
    $userQueries = count(array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'from "users"')));
    DB::disableQueryLog();

    expect($partnerQueries)->toBe(1)
        ->and($userQueries)->toBe(1)
        // the same models the relations load themselves; a blank foreign key stays null
        ->and($order->customer->name)->toBe('得意先株式会社')
        ->and($order->plateSupplier->name)->toBe('刷版屋')
        ->and($order->relationLoaded('dataSupplier'))->toBeTrue()
        ->and($order->dataSupplier)->toBeNull()
        ->and($order->papers->map(fn ($paper): ?string => $paper->supplier?->name)->all())->toBe(['紙屋', '紙屋2', null])
        ->and($order->printings->first()->supplier->name)->toBe('印刷屋')
        ->and($order->designer->is($this->admin))->toBeTrue();
});

it('shows the 受注 record with its 取引先 and works out ‹ › only when the page opens', function () {
    $next = Order::query()->create(['customer_id' => $this->order->customer_id, 'quantity' => '20']);
    $this->actingAs($this->admin);

    $page = Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
        ->assertSee('得意先株式会社')
        ->assertSee('刷版屋')
        ->assertSee('紙屋2')
        ->assertSee('印刷屋');
    expect($page->get('neighbourUrls'))->toHaveKeys(['previous', 'next'])
        ->and(array_filter($page->get('neighbourUrls')))->not->toBe([]);

    // a later request of the page keeps them: no query for ‹ ›
    DB::flushQueryLog();
    DB::enableQueryLog();
    $page->call('$refresh');
    $orderQueries = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'from "orders"') && str_contains($query['query'], 'sfk_neighbour'));
    DB::disableQueryLog();
    expect($orderQueries)->toBe([]);
    unset($next);
});
