<?php

/*
 * Records of another app offered on a screen — dropdowns, lookups and what they copy, 絞り込む choices, the 製袋予定表's
 * 注番 lookup and 売上を追加 from a job — are only those the user may see in that app (security review 2026-11-14;
 * the legacy lookups offered only what the user could view): AppResource::visibleOr(), VisibleChoice.
 */

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Filament\Support\VisibleChoice;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;
use Modules\MasterData\Filament\Resources\Partners\PartnerResource;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Models\Order;
use Modules\Production\Application\Schedules\SalesLookup;
use Modules\Sales\Filament\Resources\Sales\Pages\CreateSale;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Models\Sale;

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業スペース']);
    $this->clerk = grantDashboards(User::factory()->create(['name' => '売上 担当']), 'erp');
    $this->space->users()->attach($this->clerk);

    // the clerk may add 売上 — 04. 受注入力 and 01. 取引先マスタ are not open to them yet
    $this->open = function (string $code, string $name, array $abilities): void {
        $app = BusinessApp::query()->firstOrNew(['code' => $code], ['name' => $name]);
        $app->forceFill(['status' => AppStatus::Published])->save();
        $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, ...array_fill_keys(array_map(fn (AppAbility $a): string => $a->column(), $abilities), true)]);
        $app->spaces()->syncWithoutDetaching([$this->space->id]);
        // the rights are kept for the request (AppAccess): the next screen is a new request
        app()->instance('request', Request::create('/erp'));
    };
    ($this->open)('sales-entry', '06. 売上入力', [AppAbility::View, AppAbility::Create, AppAbility::Update]);
    foreach (['order-entry' => '04. 受注入力', 'partner-master' => '01. 取引先マスタ'] as $code => $name) {
        $app = BusinessApp::query()->firstOrNew(['code' => $code], ['name' => $name]);
        $app->forceFill(['status' => AppStatus::Published])->save();
    }

    $this->customer = Partner::query()->create(['name' => '株式会社秘密', 'short_name' => '秘密', 'is_customer' => true]);
    $this->used = Partner::query()->create(['name' => '株式会社いつもの', 'short_name' => 'いつもの', 'is_customer' => true]);
    $this->order = Order::query()->create(['customer_id' => $this->customer->id, 'product_name' => '極秘封筒', 'quantity' => '1000', 'sales_amount' => 20000, 'order_number' => '90001']);
    Sale::query()->create(['customer_id' => $this->used->id, 'sold_on' => '2026-11-01', 'delivery_due_on' => '2026-11-01', 'product_name' => '長3封筒', 'amount' => 1000]);
});

it('fills 売上を追加 from a job only when the user may see the job (?order=)', function () {
    $this->actingAs($this->clerk);

    Livewire::withQueryParams(['order' => (string) $this->order->id])->test(CreateSale::class)
        ->assertSchemaStateSet(['product_name' => null, 'order_id' => null])
        ->assertDontSee('極秘封筒');

    ($this->open)('order-entry', '04. 受注入力', [AppAbility::View]);
    Livewire::withQueryParams(['order' => (string) $this->order->id])->test(CreateSale::class)
        ->assertSchemaStateSet(['product_name' => '極秘封筒', 'order_id' => $this->order->id]);
});

it('offers another app\'s records the user may see, plus those their own records already refer to', function () {
    $this->actingAs($this->clerk);
    $keep = SaleResource::getEloquentQuery()->select('sales.customer_id');

    // no 閲覧 on 01. 取引先マスタ: only the 取引先 of the sales they can see — never the others
    expect(PartnerResource::visibleOr(Partner::query(), $keep)->pluck('name')->all())->toBe(['株式会社いつもの'])
        ->and(PartnerResource::visibleOr(Partner::query())->count())->toBe(0)
        ->and(OrderResource::visibleOr(Order::query())->count())->toBe(0);

    // with 閲覧: all of them
    ($this->open)('partner-master', '01. 取引先マスタ', [AppAbility::View]);
    expect(PartnerResource::visibleOr(Partner::query(), $keep)->pluck('name')->sort()->values()->all())->toBe(['株式会社いつもの', '株式会社秘密']);
});

it('refuses a reference to a record the user may not choose, also when sent by hand', function () {
    $this->actingAs($this->clerk);
    $rule = new VisibleChoice(OrderResource::class, Order::class);
    $failed = null;
    $rule->validate('data.order_id', $this->order->id, function (string $message) use (&$failed): void {
        $failed = $message;
    });
    expect($failed)->toBe('選択できない値です。');

    ($this->open)('order-entry', '04. 受注入力', [AppAbility::View]);
    $failed = null;
    $rule->validate('data.order_id', $this->order->id, function (string $message) use (&$failed): void {
        $failed = $message;
    });
    expect($failed)->toBeNull();
});

it('reads the 06. 売上入力 values of a 注番 for the 製袋予定表 only from sales the user may see', function () {
    $sale = Sale::query()->create(['customer_id' => $this->customer->id, 'order_id' => $this->order->id, 'sold_on' => '2026-11-02', 'delivery_due_on' => '2026-11-02', 'product_name' => '極秘封筒', 'amount' => 5000]);
    $outsider = grantDashboards(User::factory()->create(), 'erp');
    $this->space->users()->attach($outsider);

    // the clerk sees 06. 売上入力; someone without it gets nothing
    $this->actingAs($this->clerk);
    expect(app(SalesLookup::class)->find('90001')['product'] ?? null)->toBe('極秘封筒');

    BusinessApp::query()->where('code', 'sales-entry')->sole()->permissions()->delete();
    $this->actingAs($outsider);
    expect(app(SalesLookup::class)->find('90001'))->toBeNull();
    expect($sale->exists)->toBeTrue();
});
