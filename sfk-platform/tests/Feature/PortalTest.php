<?php

/*
 * ポータル of 白石封筒 (legacy-style home): greeting, お知らせ and アプリショートカット (administrators edit),
 * スペース (joined / all) and アプリ (only those the user may view, filterable by space).
 */

use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Filament\Portal\PortalPage;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\DashboardPortal;
use Modules\Core\Models\Space;

function portal_app(string $code, string $name, Space $space, AppStatus $status = AppStatus::Published, ?string $iconText = null): BusinessApp
{
    $app = new BusinessApp(['code' => $code, 'name' => $name, 'space_id' => $space->id, 'icon_text' => $iconText]);
    $app->forceFill(['status' => $status])->save();

    return $app;
}

beforeEach(function () {
    $this->admin = grantDashboards(User::factory()->admin()->create(), 'erp');
    $this->employee = grantDashboards(User::factory()->create(['name' => '菊池 毅', 'employee_code' => '10']), 'erp');

    $this->sales = Space::query()->create(['panel_id' => 'erp', 'name' => '営業スペース']);
    $this->factory = Space::query()->create(['panel_id' => 'erp', 'name' => '製造スペース']);
    $this->sales->users()->attach($this->employee);

    $this->orders = portal_app('order-entry', '04. 受注入力', $this->sales, iconText: '受注');
    $this->orders->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
    $this->daily = portal_app('daily-report', '08. 日報入力', $this->factory);
    $this->daily->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
    $this->draft = portal_app('quotation', '09. 見積書管理', $this->sales, AppStatus::Developing);

    Filament::setCurrentPanel('erp');
});

it('is the home of 白石封筒, without a greeting', function () {
    $this->actingAs($this->employee)->get('/erp')
        ->assertOk()
        ->assertDontSee('今日も一日よろしくお願いします')
        ->assertSee('お知らせ')
        ->assertSee('スペース')
        ->assertSee('アプリ');
});

it('lists the joined spaces and the apps the employee may view', function () {
    $this->actingAs($this->employee);

    Livewire::test(PortalPage::class)
        ->assertSee('営業スペース')
        ->assertDontSee('製造スペース')
        ->assertSee('04. 受注入力')
        ->assertSee('受注')
        ->assertDontSee('08. 日報入力') // not a member of 製造スペース
        ->assertDontSee('09. 見積書管理') // 開発中
        ->assertSee('画面準備中');
});

it('shows every space to administrators and filters apps by space', function () {
    $this->actingAs($this->admin);

    Livewire::test(PortalPage::class)
        ->assertSet('spaceScope', 'all')
        ->assertSee('製造スペース')
        ->assertSee('08. 日報入力')
        ->assertSee('04. 受注入力')
        ->call('showSpace', $this->factory->id)
        ->assertSee('08. 日報入力')
        ->assertDontSee('04. 受注入力')
        ->set('spaceScope', 'joined')
        ->assertSee('参加中のスペースはありません。');
});

it('keeps employees on their joined spaces', function () {
    $this->actingAs($this->employee);

    Livewire::test(PortalPage::class)
        ->set('spaceScope', 'all')
        ->assertSet('spaceScope', 'joined')
        ->assertDontSee('製造スペース');
});

it('lets administrators edit the お知らせ, rendered for everyone', function () {
    $this->actingAs($this->admin);

    Livewire::test(PortalPage::class)
        ->callAction('editNotice', data: ['notice' => '<h2>※ お知らせ ※</h2><p>安全衛生チェックを毎月全員 1日 にお願いします。</p>'])
        ->assertHasNoActionErrors()
        ->assertNotified('お知らせを保存しました');

    expect(DashboardPortal::for('erp')->notice)->toContain('安全衛生チェック');

    $this->actingAs($this->employee)->get('/erp')->assertSee('安全衛生チェックを毎月全員 1日 にお願いします。');

    Livewire::test(PortalPage::class)->assertActionHidden('editNotice');
});

it('does not render script from the お知らせ', function () {
    DashboardPortal::for('erp')->fill(['notice' => '<p>注意</p><script>alert(1)</script>'])->save();

    $this->actingAs($this->employee)->get('/erp')
        ->assertSee('注意')
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});

it('shows the お知らせ in the text colours chosen in the editor', function () {
    DashboardPortal::for('erp')->fill(['notice' => '<p><span class="color" data-color="#e74c3c">赤い文字</span></p>'])->save();

    $this->actingAs($this->employee)->get('/erp')
        ->assertSee('赤い文字')
        ->assertSee('style="--color: #e74c3c', escape: false)
        ->assertSee('.sfk-notice-content .color { color: var(--color); }', escape: false);
});

it('links every space to its settings for administrators', function () {
    $this->actingAs($this->admin);

    Livewire::test(PortalPage::class)
        ->assertSee('スペースの設定（営業スペース）')
        ->assertSee('スペースの設定（製造スペース）')
        ->assertSee('tableAction=edit&amp;tableActionRecord='.$this->sales->id, escape: false)
        ->assertSee('tableActionRecord='.$this->factory->id, escape: false);

    $this->actingAs($this->employee);
    Livewire::test(PortalPage::class)->assertDontSee('スペースの設定');
});

it('lets administrators choose アプリショートカット, shown only when the user may view the app', function () {
    $this->actingAs($this->admin);

    Livewire::test(PortalPage::class)
        ->callAction('editShortcuts', data: ['shortcut_app_ids' => [$this->orders->id, $this->daily->id]])
        ->assertHasNoActionErrors();

    expect(DashboardPortal::for('erp')->shortcut_app_ids)->toBe([$this->orders->id, $this->daily->id]);

    $this->actingAs($this->employee);
    Livewire::test(PortalPage::class)
        ->assertSee('アプリショートカット')
        ->assertSee('受注入力')
        ->assertDontSee('日報入力')
        ->assertActionHidden('editShortcuts');
});

it('offers the + buttons to the right people', function () {
    $this->actingAs($this->employee);
    Livewire::test(PortalPage::class)
        ->assertDontSee('スペースを作成・管理')
        ->assertDontSee('title="アプリ設定"', escape: false);

    $this->actingAs($this->admin);
    Livewire::test(PortalPage::class)
        ->assertSee('スペースを作成・管理')
        ->assertDontSee('title="アプリ設定"', escape: false);

    $this->actingAs(User::factory()->superAdmin()->create());
    Livewire::test(PortalPage::class)
        ->assertSee('スペースを作成・管理')
        ->assertSee('title="アプリ設定"', escape: false)
        ->assertSee('09. 見積書管理')
        ->assertSee('開発中');
});

it('shows apps in every stage to super admins only, dimmed while not 公開中', function () {
    $ready = portal_app('ready-app', '05. 納品(仕入)入力', $this->sales, AppStatus::Ready);
    $ready->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);

    $this->actingAs(User::factory()->superAdmin()->create());
    Livewire::test(PortalPage::class)
        ->assertSee('09. 見積書管理')
        ->assertSee('05. 納品(仕入)入力')
        ->assertSee('（準備完了）')
        ->assertSeeHtml('class="sfk-row sfk-unpublished"');

    $this->actingAs($this->admin);
    Livewire::test(PortalPage::class)
        ->assertSee('04. 受注入力')
        ->assertDontSee('05. 納品(仕入)入力')
        ->assertDontSee('09. 見積書管理')
        ->assertDontSeeHtml('class="sfk-row sfk-unpublished"');

    $this->actingAs($this->employee);
    Livewire::test(PortalPage::class)->assertDontSee('05. 納品(仕入)入力');
});

it('lists apps built for this dashboard but not placed in a space for super admins', function () {
    $app = new BusinessApp(['code' => 'partner-master', 'name' => '01. 取引先マスタ（得意先・仕入先）']);
    $app->forceFill(['status' => AppStatus::Testing])->save();

    $this->actingAs(User::factory()->superAdmin()->create());
    Livewire::test(PortalPage::class)
        ->assertSee('01. 取引先マスタ（得意先・仕入先）')
        ->assertSee('（テスト中）');

    $this->actingAs($this->admin);
    Livewire::test(PortalPage::class)->assertDontSee('01. 取引先マスタ（得意先・仕入先）');
});
