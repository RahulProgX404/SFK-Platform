<?php

/*
 * App 19. 消耗品マスタ (SYSTEM_BRAIN APP_19): legacy form / record / list, unique 名称, supplier lookup (仕入先 only,
 * BR-MD-02), 在庫数 from a stock-movement ledger (APP_19 "do NOT copy blindly") with the reorder signal 発注判定, the
 * legacy view 消耗品一覧 and the app features of its template (一括削除 off).
 */

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\RecordComment;
use Modules\Core\Models\RecordVersion;
use Modules\Core\Models\Space;
use Modules\MasterData\Domain\Consumables\ConsumableFields;
use Modules\MasterData\Domain\Consumables\StockMovementReason;
use Modules\MasterData\Filament\Resources\Consumables\ConsumableResource;
use Modules\MasterData\Filament\Resources\Consumables\Pages\CreateConsumable;
use Modules\MasterData\Filament\Resources\Consumables\Pages\EditConsumable;
use Modules\MasterData\Filament\Resources\Consumables\Pages\ListConsumables;
use Modules\MasterData\Filament\Resources\Consumables\Pages\ViewConsumable;
use Modules\MasterData\Models\Consumable;
use Modules\MasterData\Models\Partner;

function makeConsumable(array $attributes = []): Consumable
{
    return Consumable::query()->create(['name' => '段ボール A-1', ...$attributes]);
}

function moveStock(Consumable $consumable, string $quantity, StockMovementReason $reason = StockMovementReason::Receipt): void
{
    $consumable->stockMovements()->create(['moved_on' => now()->toDateString(), 'reason' => $reason, 'quantity' => $quantity]);
}

/** 入出庫を登録 in the 入出庫履歴 section of the record page. */
function stockAction(): TestAction
{
    return TestAction::make('recordStockMovement')->schemaComponent('stockMovements', schema: 'infolist');
}

/** Publishes 19. 消耗品マスタ in a space of 白石封筒, as アプリ設定 would add it (registry sync). */
function publishedConsumableApp(): BusinessApp
{
    app(AppLifecycle::class)->syncWithCode(['consumables-master' => '19. 消耗品マスタ'], ['consumables-master' => ConsumableResource::appSettingDefaults()]);
    $space = Space::query()->firstOrCreate(['panel_id' => 'erp', 'name' => '製造スペース']);
    $app = BusinessApp::query()->where('code', 'consumables-master')->sole();
    $app->forceFill(['status' => AppStatus::Published])->save();
    $app->spaces()->syncWithoutDetaching([$space->id]);

    return $app;
}

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->admin = User::factory()->superAdmin()->create(['name' => '管理者']);
    $this->supplier = Partner::query()->create(['name' => '株式会社ダンボール', 'short_name' => 'ダンボ', 'code' => 'S100', 'is_supplier' => true]);
});

it('registers itself as an app from the code, with 一括削除 off like its legacy template', function () {
    expect((new AppDiscovery([base_path('modules')]))->definitions())->toHaveKey('consumables-master', '19. 消耗品マスタ');

    $app = publishedConsumableApp();
    expect($app->bulk_delete_enabled)->toBeFalse()
        ->and($app->record_comments_enabled)->toBeTrue()
        ->and($app->record_history_enabled)->toBeTrue();
});

it('creates a consumable with its supplier and shows the saved record with 在庫数 blank', function () {
    $customer = Partner::query()->create(['name' => '得意先だけ', 'short_name' => '得意', 'is_customer' => true]);
    $this->actingAs($this->admin);

    $component = Livewire::test(CreateConsumable::class)
        ->assertFormFieldExists('supplier_id', fn ($field): bool => array_key_exists($this->supplier->id, $field->getOptions())
            && ! array_key_exists($customer->id, $field->getOptions()))
        ->fillForm([
            'name' => '段ボール A-1',
            'box_type' => '【A式】',
            'kind' => '段ボール',
            'reorder_point' => '50',
            'amount' => '12000',
            'unit_price' => '120.5',
            'unit' => '枚',
            'lot_size' => '100',
            'supplier_id' => $this->supplier->id,
            'notes' => '角2用',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $consumable = Consumable::query()->sole();
    $component->assertRedirect(ConsumableResource::getUrl('view', ['record' => $consumable]));
    expect($consumable->unit_price)->toBe('120.5000')
        ->and($consumable->stock())->toBeNull()
        ->and($consumable->reorderFlag())->toBe('')
        ->and($consumable->created_by)->toBe($this->admin->id);

    Livewire::test(ViewConsumable::class, ['record' => $consumable->getRouteKey()])
        ->assertSeeInOrder(['名称', '段ボール A-1', 'カテゴリ', '【A式】', '区分', '段ボール', '仕入先名', '株式会社ダンボール', '単価', '¥120.5', '単位', '枚',
            '金額', '¥12,000', '在庫数', '発注点', '50', 'ロット数', '100', '備考', '角2用', 'レコード番号', '作成者', '更新者',
            '得意先コード', 'S100', '入出庫履歴']);

    Livewire::test(CreateConsumable::class)->fillForm(['name' => '段ボール A-1'])->call('create')->assertHasFormErrors(['name' => 'unique']);
    Livewire::test(CreateConsumable::class)->fillForm(['name' => '', 'unit_price' => '-1', 'lot_size' => '0.12345'])->call('create')
        ->assertHasFormErrors(['name' => 'required', 'unit_price', 'lot_size']);
});

it('derives 在庫数 from the stock movements and flags 要発注 at the reorder point', function () {
    $consumable = makeConsumable(['reorder_point' => '50']);
    $this->actingAs($this->admin);

    Livewire::test(ViewConsumable::class, ['record' => $consumable->getRouteKey()])
        ->callAction(stockAction(), data: ['moved_on' => '2026-10-01', 'reason' => 'receipt', 'quantity' => '100', 'note' => '初回入荷'])
        ->assertHasNoActionErrors()
        ->assertNotified('入出庫を登録しました');
    expect($consumable->fresh()->stock())->toBe('100.0000')->and($consumable->fresh()->reorderFlag())->toBe('');

    moveStock($consumable, '-60', StockMovementReason::Usage);
    expect($consumable->fresh()->stock())->toBe('40.0000')
        ->and($consumable->fresh()->reorderFlag())->toBe(ConsumableFields::REORDER)
        ->and(ConsumableFields::reorderFlag('50', '50'))->toBe('要発注')
        ->and(ConsumableFields::reorderFlag('51', '50'))->toBe('')
        ->and(ConsumableFields::reorderFlag(null, '50'))->toBe('');

    Livewire::test(ViewConsumable::class, ['record' => $consumable->getRouteKey()])
        ->assertSeeInOrder(['在庫数', '40', '入出庫履歴', '日付', '区分', '数量', '使用', '-60', '入庫', '100', '初回入荷']);

    // 入庫 needs a positive quantity; 棚卸調整 may go either way but not 0
    Livewire::test(ViewConsumable::class, ['record' => $consumable->getRouteKey()])
        ->callAction(stockAction(), data: ['moved_on' => '2026-10-02', 'reason' => 'receipt', 'quantity' => '-5'])
        ->assertHasActionErrors(['quantity'])
        ->callAction(stockAction(), data: ['moved_on' => '2026-10-02', 'reason' => 'adjustment', 'quantity' => '0'])
        ->assertHasActionErrors(['quantity'])
        ->callAction(stockAction(), data: ['moved_on' => '2026-10-02', 'reason' => 'adjustment', 'quantity' => '-2.5'])
        ->assertHasNoActionErrors();
    expect($consumable->fresh()->stock())->toBe('37.5000');
});

it('offers the legacy view 消耗品一覧 and （すべて）, sorting and filtering on 在庫数 and 発注判定', function () {
    publishedConsumableApp();
    $low = makeConsumable(['name' => '段ボール 少', 'reorder_point' => '10', 'supplier_id' => $this->supplier->id]);
    moveStock($low, '5');
    $high = makeConsumable(['name' => '段ボール 多', 'reorder_point' => '10']);
    moveStock($high, '500');
    $none = makeConsumable(['name' => 'インク']);
    $this->actingAs($this->admin);

    Livewire::test(ListConsumables::class)
        ->assertSee('消耗品一覧')
        ->assertTableColumnVisible('stock')
        ->assertTableColumnDoesNotExist('reorder_flag')
        ->assertCanSeeTableRecords([$none, $high, $low], inOrder: true)
        ->assertSee(['株式会社ダンボール', '500'])
        ->sortTable('stock')
        ->assertCanSeeTableRecords([$low, $high], inOrder: true)
        ->sortTable('supplier_id')
        ->set('listView', 'all')
        ->assertTableColumnVisible('reorder_flag')
        ->assertSee('要発注')
        ->call('applyAppFilter', ['conditions' => [['field' => 'reorder_flag', 'operator' => 'in', 'values' => ['要発注']]]])
        ->assertCanSeeTableRecords([$low])
        ->assertCanNotSeeTableRecords([$high, $none])
        ->call('applyAppFilter', ['conditions' => [['field' => 'stock', 'operator' => 'gte', 'values' => ['100']]]])
        ->assertCanSeeTableRecords([$high])
        ->assertCanNotSeeTableRecords([$low, $none])
        ->call('applyAppFilter', ['conditions' => [['field' => 'stock', 'operator' => 'ne', 'values' => ['500']]]])
        ->assertCanSeeTableRecords([$low, $none])
        ->assertCanNotSeeTableRecords([$high]);
});

it('edits a row in the list and keeps the 変更履歴, comments and deletes with the movements', function () {
    publishedConsumableApp();
    makeConsumable(['name' => '既存']);
    $consumable = makeConsumable();
    moveStock($consumable, '10');
    $this->actingAs($this->admin);

    Livewire::test(ListConsumables::class)
        ->callTableAction('inlineEdit', $consumable)
        ->set('inlineData.name', '既存')
        ->callTableAction('inlineSave', $consumable)
        ->assertHasErrors(['inlineData.name' => 'unique'])
        ->set('inlineData.name', '段ボール A-2')
        ->set('inlineData.unit_price', '99.25')
        ->callTableAction('inlineSave', $consumable)
        ->assertHasNoErrors();
    expect($consumable->fresh()->unit_price)->toBe('99.2500')->and($consumable->fresh()->stock())->toBe('10.0000');

    Livewire::test(EditConsumable::class, ['record' => $consumable->getRouteKey()])->fillForm(['unit' => '束'])->call('save')->assertHasNoFormErrors();
    expect(RecordVersion::query()->where('record_type', $consumable->getMorphClass())->count())->toBeGreaterThanOrEqual(1)
        ->and(RecordVersion::query()->where('record_type', $consumable->getMorphClass())->latest('id')->first()->snapshot)->not->toHaveKey('stock');

    Livewire::test(ViewConsumable::class, ['record' => $consumable->getRouteKey()])->set('commentData.body', '在庫確認済み')->call('postComment')->assertHasNoErrors();
    expect(Livewire::test(ListConsumables::class)->instance()->canBulkDelete())->toBeFalse();

    $consumable->delete();
    expect(RecordComment::query()->count())->toBe(0)
        ->and(DB::table('consumable_stock_movements')->count())->toBe(0);
});

it('limits employees to what the app permissions allow, 入出庫 included', function () {
    $consumable = makeConsumable();
    $app = publishedConsumableApp();
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
    $viewer = grantDashboards(User::factory()->create(), 'erp');
    $app->spaces->first()?->users()->attach($viewer);
    $this->actingAs($viewer);

    $this->get(ConsumableResource::getUrl('index'))->assertOk()->assertSee('段ボール A-1');
    $this->get(ConsumableResource::getUrl('view', ['record' => $consumable]))->assertOk()->assertDontSee('入出庫を登録');
    $this->get(ConsumableResource::getUrl('create'))->assertForbidden();
    $this->get(ConsumableResource::getUrl('edit', ['record' => $consumable]))->assertForbidden();
});
