<?php

/*
 * App 04. 受注入力 (SYSTEM_BRAIN APP_04): 注番 numbering on create (BR-OR-01, D-04-1), the legacy save check
 * (得意先 required, 数量 > 0), the estimate with the customer's rounding (BR-OR-02..06, BR-TX-01), the actual cost and
 * profit (BR-OR-11), the legacy views, 集計サマリー, 過去の受注履歴検索 (D-04-3), 受注書の読み込み, the record features of
 * the template (一括削除 off) and access through the app permissions.
 */

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Models\AppListView;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\RecordVersion;
use Modules\Core\Models\Space;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Application\FaxImport\FaxDocument;
use Modules\Orders\Application\FaxImport\FaxOrderReader;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Filament\Resources\Orders\Pages\CreateOrder;
use Modules\Orders\Filament\Resources\Orders\Pages\EditOrder;
use Modules\Orders\Filament\Resources\Orders\Pages\ListOrders;
use Modules\Orders\Filament\Resources\Orders\Pages\OrderSettings;
use Modules\Orders\Filament\Resources\Orders\Pages\ViewOrder;
use Modules\Orders\Models\Order;

function orderPartner(string $name, array $attributes = []): Partner
{
    return Partner::query()->create(['name' => $name, 'short_name' => mb_substr($name, 0, 4), 'is_customer' => true, ...$attributes]);
}

function makeOrder(array $attributes = []): Order
{
    return Order::query()->create(['customer_id' => $attributes['customer_id'] ?? orderPartner('受注テスト'.Str::random(4))->id, 'quantity' => '1000', ...$attributes]);
}

/** Publishes 04. 受注入力 in a space of 白石封筒, as アプリ設定 would add it (registry sync). */
function publishedOrderApp(): BusinessApp
{
    app(AppLifecycle::class)->syncWithCode(['order-entry' => '04. 受注入力'], ['order-entry' => OrderResource::appSettingDefaults()]);
    $space = Space::query()->firstOrCreate(['panel_id' => 'erp', 'name' => '営業スペース']);
    $app = BusinessApp::query()->where('code', 'order-entry')->sole();
    $app->forceFill(['status' => AppStatus::Published])->save();
    $app->spaces()->syncWithoutDetaching([$space->id]);

    return $app;
}

/** Key of a stored 一覧 of the app (the legacy views are stored the first time the list opens). */
function orderView(string $name): string
{
    return (string) AppListView::query()->where('name', $name)->value('id');
}

function orderEmployeeWith(array $abilities): User
{
    $app = publishedOrderApp();
    $columns = [];
    foreach (AppAbility::cases() as $ability) {
        $columns[$ability->column()] = in_array($ability, $abilities, true);
    }
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, ...$columns]);

    $employee = grantDashboards(User::factory()->create(), 'erp');
    $app->spaces->first()?->users()->attach($employee);

    return $employee;
}

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->admin = User::factory()->superAdmin()->create(['name' => '管理者']);
});

it('registers itself as an app from the code, with 一括削除 off like its legacy template', function () {
    $discovery = new AppDiscovery([base_path('modules')]);
    expect($discovery->definitions())->toHaveKey('order-entry', '04. 受注入力')
        ->and($discovery->settingDefaults())->toHaveKey('order-entry', ['bulk_delete_enabled' => false]);

    $app = publishedOrderApp();
    expect($app->bulk_delete_enabled)->toBeFalse()
        ->and($app->record_comments_enabled)->toBeTrue()
        ->and($app->inline_edit_enabled)->toBeTrue()
        ->and($app->record_duplicate_enabled)->toBeTrue()
        ->and($app->record_history_enabled)->toBeTrue();
});

it('creates an order with 注番, the customer terms and the estimate rounded by the customer rule', function () {
    $customer = orderPartner('株式会社サンプル', ['rounding' => '四捨五入', 'closing_day' => '20日']);
    $supplier = orderPartner('紙商事', ['is_customer' => false, 'is_supplier' => true, 'short_name' => '紙商']);
    $assignee = User::factory()->create(['name' => '営業 花子']);
    $this->actingAs($this->admin);
    $undoFake = Repeater::fake();

    $component = Livewire::test(CreateOrder::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'assignees' => [$assignee->id],
            'product_name' => '長3封筒',
            'quantity' => '10000',
            'status' => '断裁作業中',
            'sales_amount' => '120000',
            'design_fee' => '5000',
            'plate_count' => '4',
            'plate_unit_price' => '2500.5',
            'bag_unit_price' => '1.25',
            'papers' => [[
                'supplier_id' => $supplier->id, 'paper_name' => 'クラフト', 'pricing' => 'キロ', 'sheets' => '1500', 'weight_kg' => '90.5',
                'unit_price' => '333', 'bundles' => '5', 'cutting_unit_price' => '200', 'manual' => [], 'cutting_manual' => [],
            ]],
            'printings' => [['supplier_id' => $supplier->id, 'passes' => '2500', 'colors' => '2', 'unit_price' => '1.2', 'manual' => []]],
            'finishings' => [['unit_price' => '3', 'passes' => '1000', 'manual' => ['手動'], 'manual_amount' => '2500']],
        ])
        ->assertSchemaStateSet(['closing_day' => '20日'])
        ->call('create')
        ->assertHasNoFormErrors();
    $undoFake();

    $order = Order::query()->sole();
    $component->assertRedirect(OrderResource::getUrl('view', ['record' => $order]));

    expect($order->order_number)->toBe('sfk-0000001')
        ->and($order->closing_day)->toBe('20日')
        ->and($order->customer_rounding)->toBe('四捨五入')
        ->and($order->order_date?->toDateString())->toBe(now()->toDateString())
        ->and($order->unit)->toBe('枚')
        ->and($order->assignees->pluck('id')->all())->toBe([$assignee->id])
        ->and($order->papers)->toHaveCount(1)
        // 用紙 (1.5 × 90.5 × 333 = 45,204.75 → 45,205) + デザイン料 5,000 + 断裁 1,000
        ->and($order->paper_subtotal)->toBe(51205)
        // 4 × 2,500.5 = 10,002
        ->and($order->getAttribute('plate_amount'))->toBe(10002)
        // 1.25 × 10,000
        ->and($order->getAttribute('bag_subtotal'))->toBe(12500)
        // 51,205 + 10,002 + 印刷 6,000 + トムソン(手動) 2,500 + 12,500
        ->and($order->estimated_cost_total)->toBe(82207)
        ->and($order->estimated_profit)->toBe(37793)
        ->and($order->getAttribute('sales_remaining'))->toBe('10000.0000')
        ->and($order->created_by)->toBe($this->admin->id)
        ->and(RecordVersion::query()->where('record_type', $order->getMorphClass())->where('record_id', $order->id)->sole()->snapshot['papers'])->toHaveCount(1);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertSeeInOrder(['基本情報', '得意先(受注先)', '株式会社サンプル', '注番', 'sfk-0000001', 'ステータス', '断裁作業中', '締日', '20日',
            '品名', '長3封筒', '数量', '10,000', '担当者', '営業 花子', '売上・納品', '売上金額', '¥120,000', '見積原価合計', '¥82,207', '見積利益', '¥37,793',
            '過去の受注履歴検索', '用紙情報', '紙商事', '紙商', 'クラフト', '¥45,205', '小計', '¥51,205', '金額(刷版)', '¥10,002',
            '印　刷', '¥6,000', 'トムソン・NO・シルク・箔', '手動', '¥2,500', '製 袋・製 本・他 加 工', '▲ 折りたたむ', 'その他', '日報一覧', '仕入履歴',
            '受注入力ID', '各種原価']);
});

it('numbers each new order and never reuses a number taken from the legacy data (BR-OR-01)', function () {
    makeOrder(['order_number' => 'sfk-0000002']);
    $first = makeOrder();
    $second = makeOrder();

    expect($first->order_number)->toBe('sfk-0000001')
        ->and($second->order_number)->toBe('sfk-0000003');
});

it('always shows the first row of each table, like the legacy form, and saves no empty rows', function () {
    $customer = orderPartner('得意先T');
    $this->actingAs($this->admin);

    $page = Livewire::test(CreateOrder::class);
    foreach (['papers', 'printings', 'finishings'] as $table) {
        expect($page->get("data.{$table}"))->toHaveCount(1);
    }
    expect($page->html())
        ->toContain('kt-subtable')
        ->toContain('--kt-subtable-width: 1494px')
        ->toContain('行を追加する')
        // the only row cannot be removed
        ->not->toContain('行を削除する');

    $page->fillForm(['customer_id' => $customer->id, 'quantity' => '100'])->call('create')->assertHasNoFormErrors();

    $order = Order::query()->sole();
    expect($order->papers()->count())->toBe(0)
        ->and($order->printings()->count())->toBe(0)
        ->and($order->finishings()->count())->toBe(0);

    // the edit form shows a row to fill in again; a row emptied there is removed
    $order->printings()->create(['passes' => '100', 'colors' => '1', 'unit_price' => '2']);
    $edit = Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()]);
    expect($edit->get('data.papers'))->toHaveCount(1)->and($edit->get('data.printings'))->toHaveCount(1);
    $key = array_key_first($edit->get('data.printings'));
    $edit->set("data.printings.{$key}.passes", '')->set("data.printings.{$key}.colors", '')->set("data.printings.{$key}.unit_price", '')
        ->call('save')
        ->assertHasNoFormErrors();
    expect($order->printings()->count())->toBe(0);
});

it('blocks saving without 得意先 or with 数量 of 0 or less, with the legacy messages', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateOrder::class)
        ->fillForm(['customer_id' => null, 'quantity' => ''])
        ->call('create')
        ->assertHasFormErrors(['customer_id' => 'required', 'quantity' => 'required'])
        ->assertSee('得意先（受注先）は必須項目です。')
        ->assertSee('数量は必須項目です。0より大きい数値を入力してください。');

    Livewire::test(CreateOrder::class)
        ->fillForm(['customer_id' => orderPartner('得意先A')->id, 'quantity' => '0'])
        ->call('create')
        ->assertHasFormErrors(['quantity' => 'gt'])
        ->assertSee('数量は0より大きい値を入力してください。');

    // only 得意先 / その他 can be the customer (BR-MD-02)
    $supplierOnly = orderPartner('仕入先のみ', ['is_customer' => false, 'is_supplier' => true]);
    Livewire::test(CreateOrder::class)->fillForm(['customer_id' => $supplierOnly->id, 'quantity' => '10'])->call('create')->assertHasFormErrors(['customer_id']);

    expect(Order::query()->count())->toBe(0);
});

it('recalculates after table rows change on edit and keeps the 変更履歴 with the rows', function () {
    $customer = orderPartner('得意先B', ['rounding' => '切上']);
    $order = makeOrder(['customer_id' => $customer->id, 'sales_amount' => 50000]);
    $order->papers()->create(['pricing' => '枚', 'sheets' => '100', 'unit_price' => '10.01', 'manual' => true, 'manual_amount' => 900]);
    $order->refreshTotals();
    expect($order->paper_subtotal)->toBe(900);

    $this->actingAs($this->admin);
    Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
        ->fillForm(['quantity' => '2000', 'internal_cost' => '3000', 'purchase_amount' => '10000', 'sold_amount' => '48000'])
        ->set('data.papers.record-'.$order->papers->first()->id.'.manual', [])
        ->call('save')
        ->assertHasNoFormErrors();

    $order->refresh();
    $paper = $order->papers->first();
    // 手動 unchecked: the manual amount goes, the calculated 1,001 (100 × 10.01, 切上) counts
    expect($paper->manual)->toBeFalse()
        ->and($paper->manual_amount)->toBeNull()
        ->and($order->paper_subtotal)->toBe(1001)
        ->and($order->actual_cost_total)->toBe(13000)
        // 利益 = 現在売上済金額 − 実原価合計; 利益率 = 利益 / 売上金額 (BR-OR-11)
        ->and($order->profit)->toBe(35000)
        ->and($order->profit_rate)->toBe('70.00')
        ->and(RecordVersion::query()->where('record_type', $order->getMorphClass())->where('record_id', $order->id)->count())->toBe(1);
});

it('keeps 締日 and 端数区分 of the customer as they were when the order was taken', function () {
    $customer = orderPartner('得意先C', ['rounding' => '四捨五入', 'closing_day' => '末日']);
    $order = makeOrder(['customer_id' => $customer->id]);

    $customer->forceFill(['rounding' => '切捨', 'closing_day' => '20日'])->save();
    $order->forceFill(['product_name' => '名刺'])->save();

    expect($order->refresh()->customer_rounding)->toBe('四捨五入')->and($order->closing_day)->toBe('末日');
});

it('offers the legacy views, newest first, and sums the listed orders in 集計サマリー', function () {
    publishedOrderApp();
    $this->actingAs($this->admin);
    $mine = makeOrder(['product_name' => '担当分', 'sales_amount' => 100000, 'purchase_amount' => 20000, 'internal_cost' => 10000]);
    $mine->assignees()->attach($this->admin);
    $done = makeOrder(['customer_id' => orderPartner('売上済商事')->id, 'product_name' => '売上済み', 'quantity' => '500', 'sold_quantity' => '500', 'sales_amount' => 50000]);
    $shipped = makeOrder(['product_name' => '発送済み', 'status' => '発送完了']);

    $list = Livewire::test(ListOrders::class)
        ->assertSeeInOrder(['自分が担当の受注一覧', '受注一覧', '売上完了', '未売上あり', '未製造あり', '製造完了', '発送完了済', '製袋予定一覧', '製袋予定表出力', '（すべて）'])
        ->assertSet('listView', orderView('自分が担当の受注一覧'))
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$done, $shipped])
        ->assertSeeInOrder(['集計サマリー', '対象件数: 1 件', '売上金額', '¥100,000', '仕入原価計', '¥20,000', '実原価合計', '¥30,000', '利益率', '62.5%'])
        ->set('listView', orderView('受注一覧'))
        ->assertCanSeeTableRecords([$shipped, $done, $mine], inOrder: true);
    expect($list->html())->toContain('対象件数: 3 件');

    $list->set('listView', orderView('売上完了'))
        ->assertCanSeeTableRecords([$done])
        ->assertCanNotSeeTableRecords([$mine, $shipped])
        ->set('listView', orderView('未売上あり'))
        ->assertCanSeeTableRecords([$mine, $shipped])
        ->assertCanNotSeeTableRecords([$done])
        ->set('listView', orderView('発送完了済'))
        ->assertCanSeeTableRecords([$shipped])
        ->assertCanNotSeeTableRecords([$mine, $done]);

    $list->set('listView', 'all')
        ->assertTableColumnVisible('id')
        ->assertTableColumnVisible('purchase_cost_total')
        ->call('applyAppFilter', ['conditions' => [['field' => 'customer_id', 'operator' => 'like', 'values' => ['売上済商']]]])
        ->assertCanSeeTableRecords([$done])
        ->assertCanNotSeeTableRecords([$mine, $shipped]);
});

it('edits a row in the list with the legacy save check (インライン編集)', function () {
    publishedOrderApp();
    $order = makeOrder(['bag_unit_price' => '2']);
    $this->actingAs($this->admin);

    Livewire::test(ListOrders::class)
        ->set('listView', orderView('受注一覧'))
        ->callTableAction('inlineEdit', $order)
        ->set('inlineData.quantity', '0')
        ->callTableAction('inlineSave', $order)
        ->assertHasErrors(['inlineData.quantity' => 'gt'])
        ->set('inlineData.quantity', '1500')
        ->set('inlineData.due_date', '2026-11-05')
        ->set('inlineData.due_time', '14:30')
        ->set('inlineData.assignees', [(string) $this->admin->id])
        ->callTableAction('inlineSave', $order)
        ->assertHasNoErrors()
        ->assertSet('inlineRecordKey', null);

    $order->refresh();
    expect($order->getAttribute('quantity'))->toBe('1500.0000')
        ->and($order->due_date?->toDateString())->toBe('2026-11-05')
        ->and($order->amPm())->toBe('PM')
        ->and($order->getAttribute('bag_amount'))->toBe(3000)
        ->and($order->assignees->pluck('id')->all())->toBe([$this->admin->id]);
});

it('searches past orders of the customer from a record (過去の受注履歴検索)', function () {
    $customer = orderPartner('リピート商会');
    $old = makeOrder(['customer_id' => $customer->id, 'product_name' => '角2封筒', 'order_date' => '2026-01-10']);
    $older = makeOrder(['customer_id' => $customer->id, 'product_name' => '長3封筒', 'order_date' => '2025-06-01']);
    makeOrder(['product_name' => '角2封筒', 'order_date' => '2026-02-01']);
    $current = makeOrder(['customer_id' => $customer->id, 'product_name' => '角2封筒', 'order_date' => '2026-10-01']);
    $this->actingAs($this->admin);

    $page = Livewire::test(ViewOrder::class, ['record' => $current->getRouteKey()])
        ->assertSet('pastCustomer', 'リピート商会')
        ->assertSee('過去の受注履歴検索')
        ->assertDontSee('全2件中');

    $page->call('searchPastOrders');
    expect($page->html())->toContain('全2件中 1～2件を表示')
        ->and($page->instance()->pastOrders()['orders']->pluck('id')->all())->toBe([$old->id, $older->id]);

    $page->set('pastProduct', '角2')->call('searchPastOrders');
    expect($page->html())->toContain('全1件中 1～1件を表示');

    $page->set('pastFrom', '2026-02-01')->call('searchPastOrders');
    expect($page->instance()->pastOrders()['total'])->toBe(0);

    $page->call('resetPastOrders')->assertSet('pastCustomer', '')->assertSet('pastSearched', false);
});

it('fills a new order from a read purchase order (受注書を読み込む) and rejects other issuers', function () {
    $heart = orderPartner('ハート封筒株式会社');
    $inHouse = orderPartner('社内', ['is_customer' => false, 'is_supplier' => true]);
    $reading = [
        '発注情報' => ['発行元' => 'ハート封筒（株）大阪工場'],
        '赤マーク' => ['検印欄' => '古井'],
        '赤丸番号付きデータ' => ['印刷名' => 'サンプル 長3', '手配数' => '10,100 枚', '納期' => '2026/11/20', 'フタ仕様_折り返し寸法' => '手書「11/10」'],
        '口糊加工' => ['加工方法' => 'スチック'],
    ];
    app()->instance(FaxOrderReader::class, new class($reading) implements FaxOrderReader
    {
        public function __construct(private array $reading) {}

        public function read(FaxDocument $document): array
        {
            return $this->reading;
        }

        public function rereadInspectionStamp(FaxDocument $document, array $firstReading, array $candidates): array
        {
            return $firstReading;
        }
    });
    $this->actingAs($this->admin);

    $page = Livewire::test(CreateOrder::class)
        ->assertActionVisible('faxImport')
        ->assertActionHasLabel('faxImport', 'ハート封筒受注書を読み込む')
        ->callAction('faxImport', data: ['file' => UploadedFile::fake()->image('fax.png')])
        ->assertActionMounted('faxPreview');
    expect(view('orders::filament.fax-preview', ['import' => $page->instance()->faxImport])->render())
        ->toContain('読み取り元: fax.png ／ 得意先: 古井（判定元: 検印欄）')
        ->toContain('古井　サンプル 長3')
        ->toContain('抽出元: 検印欄「古井」＋ 印刷名');

    $page->callMountedAction()
        ->assertSchemaStateSet([
            'customer_id' => $heart->id,
            'manufacturer_id' => $inHouse->id,
            'product_name' => '古井　サンプル 長3',
            'quantity' => '10100',
            'unit' => '枚',
            'due_date' => '2026-11-20',
            'completed_on' => '2026-11-19',
            'delivered_on' => '2026-11-10',
            'bag_schedule' => 'カマス貼 1F・2F',
            'flap_glue' => 'アドヘア',
            'category1' => '封筒(別注)',
            'category2' => '紙支給',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Order::query()->sole()->product_name)->toBe('古井　サンプル 長3');

    $reading['発注情報']['発行元'] = 'サンプル封筒株式会社';
    app()->instance(FaxOrderReader::class, new class($reading) implements FaxOrderReader
    {
        public function __construct(private array $reading) {}

        public function read(FaxDocument $document): array
        {
            return $this->reading;
        }

        public function rereadInspectionStamp(FaxDocument $document, array $firstReading, array $candidates): array
        {
            return $firstReading;
        }
    });

    Livewire::test(CreateOrder::class)
        ->callAction('faxImport', data: ['file' => UploadedFile::fake()->image('fax.png')])
        ->assertNotified('このツールは発行元が「ハート封筒株式会社」の発注書の読み込み専用です。読み取った発行元は「サンプル封筒株式会社」でした。対象の発注書か確認のうえ、再度お試しください。')
        ->assertSchemaStateSet(['product_name' => null]);
});

it('has the record features: 再利用 with the table rows, コメント, 変更履歴 and no 一括削除', function () {
    publishedOrderApp();
    $order = makeOrder(['product_name' => '角2封筒', 'manufactured_quantity' => '300', 'legacy_id' => 77]);
    $order->papers()->create(['paper_name' => '上質紙', 'pricing' => '枚', 'sheets' => '100', 'unit_price' => '5']);
    $this->actingAs($this->admin);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertSee('sfk-sidebar is-collapsed', false)
        ->assertActionHasUrl('duplicate', OrderResource::getUrl('create', ['duplicate' => $order->id]));

    $undoFake = Repeater::fake();
    Livewire::test(CreateOrder::class, ['duplicate' => (string) $order->id])
        ->assertSchemaStateSet(['product_name' => '角2封筒', 'manufactured_quantity' => null])
        ->call('create')
        ->assertHasNoFormErrors();
    $undoFake();

    $copy = Order::query()->whereKeyNot($order->id)->sole();
    expect($copy->order_number)->not->toBe($order->order_number)
        ->and($copy->legacy_id)->toBeNull()
        ->and($copy->papers->pluck('paper_name')->all())->toBe(['上質紙'])
        ->and($copy->paper_subtotal)->toBe(500);

    expect(Livewire::test(ListOrders::class)->instance()->canBulkDelete())->toBeFalse();
    Livewire::test(OrderSettings::class)->assertSet('bulkDeleteEnabled', false)->assertSet('commentsEnabled', true);

    $order->delete();
    expect(RecordVersion::query()->where('record_id', $order->id)->where('record_type', $order->getMorphClass())->count())->toBe(0);
});

it('limits employees to what the app permissions allow', function () {
    $order = makeOrder(['product_name' => '従業員閲覧']);
    $viewer = orderEmployeeWith([AppAbility::View]);
    $this->actingAs($viewer);

    $this->get(OrderResource::getUrl('index'))->assertOk();
    $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertOk()->assertSee('従業員閲覧');
    $this->get(OrderResource::getUrl('create'))->assertForbidden();
    $this->get(OrderResource::getUrl('edit', ['record' => $order]))->assertForbidden();

    $this->actingAs(grantDashboards(User::factory()->create(), 'erp'));
    $this->get(OrderResource::getUrl('index'))->assertForbidden();
});
