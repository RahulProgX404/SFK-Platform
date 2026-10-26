<?php

/*
 * Deleting records: 「…」 → 選択したレコードを削除 (users with 削除, while rows are checked) asks twice; a record other
 * records still use (e.g. a 取引先 with sales) is kept with a message naming what uses it — from any screen; the
 * parts of a record go with it.
 */

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Application\Records\RecordDeletion;
use Modules\Core\Filament\Support\ThemeAssets;
use Modules\MasterData\Filament\Resources\Partners\Pages\ListPartners;
use Modules\MasterData\Filament\Resources\Partners\Pages\ViewPartner;
use Modules\MasterData\Filament\Resources\Partners\PartnerResource;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Models\Sale;

function deletionPartner(string $name): Partner
{
    return Partner::query()->create([
        'name' => $name, 'name_kana' => 'てすと', 'short_name' => mb_substr($name, 0, 4), 'is_customer' => true,
        'postal_code' => '100-0001', 'address' => '東京都', 'phone' => '03-0000-0000',
        'invoice_recipient' => '得意先名', 'delivery_note_recipient' => '得意先名',
    ]);
}

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->actingAs(User::factory()->superAdmin()->create());
});

it('deletes only the checked records with 選択したレコードを削除, after two confirmations', function () {
    $first = deletionPartner('削除する取引先A');
    $second = deletionPartner('削除する取引先B');
    $kept = deletionPartner('残す取引先');

    Livewire::test(ListPartners::class)
        ->set('selectedTableRecords', [(string) $first->id, (string) $second->id])
        ->mountAction('bulkDelete')
        ->assertMountedActionModalSee('選択した2件のレコードを削除しますか？')
        ->callMountedAction()
        // the second question replaces the first
        ->assertActionMounted(TestAction::make('confirmBulkDelete')->arguments(['keys' => [$first->id, $second->id]]))
        ->assertMountedActionModalSee('本当に2件のレコードを削除しますか？')
        ->callMountedAction()
        ->assertNotified('2件のレコードを削除しました');

    expect(Partner::query()->pluck('name')->all())->toBe(['残す取引先'])
        ->and($kept->exists)->toBeTrue();
});

it('keeps a record other records still use, saying what uses it', function () {
    $customer = deletionPartner('売上のある取引先');
    Sale::query()->create(['customer_id' => $customer->id, 'sold_on' => '2026-10-05', 'delivery_due_on' => '2026-10-06', 'product_name' => '長3封筒', 'quantity' => '1', 'unit_price' => '10', 'amount' => 10]);

    expect(app(RecordDeletion::class)->blocker($customer))->toBe('このレコードは次で使われているため削除できません：06. 売上入力（1件）')
        ->and(app(RecordDeletion::class)->blocker(deletionPartner('使われていない取引先')))->toBeNull();

    // the record page's 削除する
    Livewire::test(ViewPartner::class, ['record' => $customer->getRouteKey()])
        ->callAction('delete')
        ->assertNotified('削除できません');
    expect($customer->fresh())->not->toBeNull();

    // 選択したレコードを削除: the others are deleted, this one is kept and reported
    $free = deletionPartner('削除できる取引先');
    Livewire::test(ListPartners::class)
        ->callAction(TestAction::make('confirmBulkDelete')->arguments(['keys' => [$customer->id, $free->id]]))
        ->assertNotified('1件のレコードは他のレコードで使われているため削除できませんでした');

    expect($customer->fresh())->not->toBeNull()->and($free->fresh())->toBeNull();
});

it('shows 選択したレコードを削除 only to users who may delete, and no selection bar above the list', function () {
    deletionPartner('取引先');

    $this->get('/erp/partners')->assertOk()->assertSee('選択したレコードを削除')->assertSee('$store.sfkCheckedRows > 0', false);

    $this->actingAs(grantDashboards(User::factory()->create(), 'erp'));
    expect(PartnerResource::canDeleteAny())->toBeFalse();

    expect(ThemeAssets::bundle()['css'])->toContain('.sfk-app-page-list .fi-ta-selection-indicator {')
        ->and(ThemeAssets::bundle()['js'])->toContain("Alpine.store('sfkCheckedRows', 0)");
});
