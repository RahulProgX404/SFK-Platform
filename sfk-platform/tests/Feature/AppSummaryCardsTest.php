<?php

/*
 * The summary cards of the app lists (AppListRecords::summaryCards(): 04. 受注入力, 06. 売上入力, 売掛管理, 買掛管理 …)
 * and of the 01. 取引先マスタ record page (RecordTabs: 売上合計, 入金合計, 売掛残高 …, and those of its tabs) are the
 * company's money: only users who see everyone's money — 財務分析を表示 with 財務分析の範囲 全体
 * (User::seesOverallFinance(); super admins always) — get them (user, 2026-11-11).
 */

use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Filament\Records\RecordTabs;
use Modules\MasterData\Filament\Resources\Partners\Pages\ViewPartner;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Filament\Resources\Orders\Pages\ListOrders;

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->overall = User::factory()->create(['show_financial_analysis' => true, 'financial_scope' => User::FINANCE_OVERALL]);
    $this->personal = User::factory()->create(['show_financial_analysis' => true, 'financial_scope' => User::FINANCE_PERSONAL]);
    $this->noFinance = User::factory()->create();
    $this->admin = User::factory()->admin()->create(); // an administrator without 財務分析を表示
});

it('shows the summary cards of an app list only to users who see everyone\'s money', function () {
    $app = openAppTo('order-entry', $this->overall, $this->personal, $this->noFinance);
    $app->permissions()->update([AppAbility::View->column() => true]);

    $this->actingAs($this->overall);
    Livewire::test(ListOrders::class)->assertSee(['sfk-app-summary', '対象件数', '利益率'], escape: false);
    $this->actingAs(User::factory()->superAdmin()->create());
    Livewire::test(ListOrders::class)->assertSee('対象件数');

    foreach ([$this->personal, $this->noFinance, $this->admin] as $user) {
        $this->actingAs($user);
        Livewire::test(ListOrders::class)
            ->assertOk()
            ->assertDontSee(['sfk-app-summary', '対象件数'], escape: false);
    }
});

it('shows the summary cards of a 取引先マスタ record and of its tabs only to users who see everyone\'s money', function () {
    $members = [$this->overall, $this->personal, $this->noFinance];
    foreach (['partner-master', 'sales-entry', 'invoice'] as $code) {
        openAppTo($code, ...$members);
    }
    $partner = Partner::query()->create([
        'name' => '株式会社カード', 'name_kana' => 'かぶしきがいしゃかーど', 'short_name' => 'カード', 'invoice_recipient' => '得意先名',
        'delivery_note_recipient' => '得意先名', ...Partner::roleColumns(['得意先']),
    ]);
    $tabs = app(RecordTabs::class);

    $this->actingAs($this->overall);
    Livewire::test(ViewPartner::class, ['record' => $partner->getRouteKey()])->assertSee(['sfk-profile-cards', '売上合計（税抜）'], escape: false);
    expect(array_column($tabs->cardsFor(ViewPartner::TABS, $partner), 'label'))->toContain('売上合計（税抜）')
        ->and(array_column($tabs->data(ViewPartner::TABS, 'invoices', $partner)['cards'], 'label'))->toContain('御請求額合計');

    foreach ([$this->personal, $this->noFinance, $this->admin] as $user) {
        $this->actingAs($user);
        Livewire::test(ViewPartner::class, ['record' => $partner->getRouteKey()])
            ->assertOk()
            ->assertSee('株式会社カード')
            ->assertDontSee(['sfk-profile-cards', '売上合計（税抜）'], escape: false);
        expect($tabs->cardsFor(ViewPartner::TABS, $partner))->toBe([])
            // the tab keeps its rows, without the cards
            ->and($tabs->data(ViewPartner::TABS, 'invoices', $partner))->toMatchArray(['cards' => []])->toHaveKey('rows');
    }
});
