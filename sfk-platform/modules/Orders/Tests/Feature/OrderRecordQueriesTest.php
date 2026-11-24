<?php

/*
 * The 受注 record page reads its 取引先 (得意先, 仕入先 and the 仕入先 of every 用紙 / 印刷 / 加工 row) with one query
 * and its users with one, on every request of the page (Core RelationBatch), and works out ‹ › once when it opens
 * (performance pass 2026-11-20, docs/performance.md).
 */

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Application\Records\RelationBatch;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Models\AppListView;
use Modules\Core\Models\BusinessApp;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Filament\Resources\Orders\Pages\EditOrder;
use Modules\Orders\Filament\Resources\Orders\Pages\ListOrders;
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

it('reads the 取引先 of the 受注 list with one query per click and none for the users\' cards (2026-11-24)', function () {
    foreach (range(1, 3) as $i) {
        Order::query()->create(['customer_id' => $this->order->customer_id, 'quantity' => (string) $i, 'plate_supplier_id' => $this->order->plate_supplier_id,
            'manufacturer_id' => $this->order->manufacturer_id, 'designer_id' => $this->admin->id])->assignees()->attach($this->admin->id);
    }
    // the users' cards (UserPopup) from the reference cache, as in production
    config(['core.reference_cache' => true]);
    // 04. 受注入力 as the registry has it, its list on 受注一覧 (every order)
    app(AppLifecycle::class)->syncWithCode(['order-entry' => '04. 受注入力'], ['order-entry' => OrderResource::appSettingDefaults()]);
    BusinessApp::query()->where('code', 'order-entry')->sole()->forceFill(['status' => AppStatus::Published])->save();
    $this->actingAs($this->admin);
    $page = Livewire::test(ListOrders::class);
    $page->set('listView', (string) AppListView::query()->where('name', '受注一覧')->value('id'))
        ->assertSee('得意先株式会社');
    // the 取引先 the rows hold, all five relations loaded (from the one query)
    $rows = $page->instance()->getTableRecords()->getCollection();
    expect($rows)->toHaveCount(4)
        ->and($rows->every(fn (Order $order): bool => $order->relationLoaded('customer') && $order->relationLoaded('plateSupplier')
            && $order->relationLoaded('manufacturer') && $order->relationLoaded('dataSupplier') && $order->relationLoaded('filmSupplier')))->toBeTrue()
        ->and($rows->map(fn (Order $order): ?string => $order->manufacturer?->name)->unique()->values()->all())->toBe(['製造所'])
        ->and($rows->first()->dataSupplier)->toBeNull();

    $count = function (string $table) use ($page): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $page->call('$refresh');
        $queries = count(array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'from "'.$table.'"')
            || str_contains($query['query'], 'join "'.$table.'"')));
        DB::disableQueryLog();

        return $queries;
    };
    expect($count('partners'))->toBe(1)
        ->and($count('employee_categories'))->toBe(0)
        ->and($count('positions'))->toBe(0);
    $page->assertSee('得意先株式会社');
});

it('reads the preloaded 取引先 of the 受注 form once per kind, not once per select (2026-11-24)', function () {
    $this->actingAs($this->admin);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $page = Livewire::test(EditOrder::class, ['record' => $this->order->getRouteKey()]);
    $partners = array_values(array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'from "partners"')));
    DB::disableQueryLog();

    $lists = array_filter($partners, fn (array $query): bool => str_starts_with($query['query'], 'select "name", "id" from "partners"'));

    // 得意先 once, the suppliers once (製版先 / 製造先 / データ / フィルム and the rows' 仕入先 share it) — the chosen 取引先
    // are named from those lists, not looked up select by select; then one per supplier of a row (紙屋, 紙屋2, 印刷屋:
    // 得意先ID / 略名 next to it)
    expect(count($lists))->toBe(2)
        ->and(count($partners))->toBe(2 + 3);
    $page->assertSee(['得意先株式会社', '刷版屋', '製造所', '紙屋']);
});
