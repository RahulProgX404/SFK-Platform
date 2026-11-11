<?php

/*
 * The home of 白石封筒: the dashboard (cards: modules/Analytics/Tests/Feature/DashboardTest.php), and アプリ — the panel over
 * the whole screen with よく使うアプリ (the five the user opens most), スペース (joined / all) and the apps (only those the
 * user may view, filterable by space) in the user's own order (drag; back to the default; administrators make theirs it).
 */

use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppUsageLog;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Filament\Portal\PortalPage;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\DashboardPortal;
use Modules\Core\Models\Space;
use Modules\Settings\Filament\Pages\SettingsDashboard;

function portal_app(string $code, string $name, Space $space, AppStatus $status = AppStatus::Published, ?string $iconText = null): BusinessApp
{
    $app = new BusinessApp(['code' => $code, 'name' => $name, 'icon_text' => $iconText]);
    $app->forceFill(['status' => $status])->save();
    $app->spaces()->attach($space);

    return $app;
}

beforeEach(function () {
    $this->admin = grantDashboards(User::factory()->admin()->create(), 'erp');
    $this->employee = grantDashboards(User::factory()->create(['name' => '菊池 毅', 'employee_code' => '10']), 'erp');

    $this->sales = Space::query()->create(['panel_id' => 'erp', 'name' => '営業スペース']);
    $this->factory = Space::query()->create(['panel_id' => 'erp', 'name' => '製造スペース']);
    $this->sales->users()->attach($this->employee);

    $this->orders = portal_app('sales-entry', '06. 売上入力', $this->sales, iconText: '売上');
    $this->orders->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
    $this->daily = portal_app('daily-report-legacy', '08. 日報入力', $this->factory);
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
        ->assertSee('06. 売上入力')
        ->assertSee('売上')
        ->assertDontSee('08. 日報入力') // not a member of 製造スペース
        ->assertDontSee('09. 見積書管理') // 開発中
        ->assertDontSee('画面準備中'); // 06 has its screens
});

it('shows every space to administrators and filters apps by space', function () {
    $this->actingAs($this->admin);

    Livewire::test(PortalPage::class)
        ->assertSet('spaceScope', 'all')
        ->assertSee('製造スペース')
        ->assertSee('08. 日報入力')
        ->assertSee('06. 売上入力')
        ->call('showSpace', $this->factory->id)
        ->assertSee('08. 日報入力')
        ->assertSee('画面準備中') // published, screens not built yet
        ->assertDontSee('06. 売上入力')
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

it('lets administrators edit the お知らせ, shown to everyone on the dashboard selection (/portal)', function () {
    $this->actingAs($this->admin);

    Livewire::test(PortalPage::class)
        ->callAction('editNotice', data: ['notice' => '<h2>※ お知らせ ※</h2><p>安全衛生チェックを毎月全員 1日 にお願いします。</p>'])
        ->assertHasNoActionErrors()
        ->assertNotified('お知らせを保存しました');

    expect(DashboardPortal::for('erp')->notice)->toContain('安全衛生チェック');

    $this->actingAs($this->employee)->get('/portal')->assertOk()->assertSee('安全衛生チェックを毎月全員 1日 にお願いします。');
    $this->get('/erp')->assertDontSee('安全衛生チェックを毎月全員 1日 にお願いします。');

    Livewire::test(PortalPage::class)->assertActionHidden('editNotice');
});

it('does not render script from the お知らせ', function () {
    DashboardPortal::for('erp')->fill(['notice' => '<p>注意</p><script>alert(1)</script>'])->save();

    $this->actingAs($this->employee)->get('/portal')
        ->assertSee('注意')
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});

it('shows the お知らせ in the text colours chosen in the editor', function () {
    DashboardPortal::for('erp')->fill(['notice' => '<p><span class="color" data-color="#e74c3c">赤い文字</span></p>'])->save();

    $this->actingAs($this->employee)->get('/portal')
        ->assertSee('赤い文字')
        ->assertSee('style="--color: #e74c3c', escape: false)
        ->assertSee('.sp-notice-content .color { color: var(--color); }', escape: false);
});

it('links every space to its settings for administrators', function () {
    $this->actingAs($this->admin);

    Livewire::test(PortalPage::class)
        ->assertSee('スペースの設定（営業スペース）')
        ->assertSee('スペースの設定（製造スペース）')
        ->assertSee('dashboard-settings/spaces?space='.$this->sales->id, escape: false)
        ->assertSee('dashboard-settings/spaces?space='.$this->factory->id, escape: false);

    $this->actingAs($this->employee);
    Livewire::test(PortalPage::class)->assertDontSee('スペースの設定');
});

it('shows よく使うアプリ in place of アプリショートカット: the five apps the user opens most, only those they may open', function () {
    $log = app(AppUsageLog::class);
    foreach (range(1, 3) as $visit) {
        $log->touch($this->admin, 'sales-entry');
    }
    $log->touch($this->admin, 'daily-report-legacy');
    foreach (range(1, 5) as $visit) {
        $log->touch($this->admin, 'quotation'); // 開発中: not for administrators
    }
    $log->touch($this->employee, 'daily-report-legacy'); // not in their spaces any more

    // 08 has no screen here and 09 is 開発中 (super admins only): left out
    $this->actingAs($this->admin);
    Livewire::test(PortalPage::class)
        ->assertSeeInOrder(['よく使うアプリ', 'sfk-shortcut', '売上入力', 'sfk-app-title'], escape: false)
        ->assertDontSee(['アプリショートカット', '09. 見積書管理']);
    $super = User::factory()->superAdmin()->create();
    $log->touch($super, 'sales-entry');
    $log->touch($super, 'quotation');
    $log->touch($super, 'quotation');
    $this->actingAs($super);
    Livewire::test(PortalPage::class)->assertSeeInOrder(['よく使うアプリ', '見積書管理', '売上入力', 'sfk-app-title'], escape: false);

    // nothing opened yet of the apps they may open (08 is not in their spaces): the apps everyone opens most, of those
    // they may open — 06 (opened by the administrator), never 09
    $this->actingAs($this->employee);
    Livewire::test(PortalPage::class)
        ->assertSeeInOrder(['よく使うアプリ', 'sfk-shortcut', '売上入力', 'sfk-app-title'], escape: false)
        ->assertDontSee(['アプリショートカット', '見積書管理']);
    expect($log->popular())->toBe(['quotation', 'sales-entry', 'daily-report-legacy'])
        ->and($log->mostUsedOrPopular($this->employee, ['sales-entry']))->toBe(['sales-entry'])
        ->and($log->mostUsedOrPopular($this->admin, ['sales-entry', 'daily-report-legacy']))->toBe(['sales-entry', 'daily-report-legacy']);
    $log->touch($this->employee, 'sales-entry');
    expect($log->mostUsedOrPopular($this->employee, ['sales-entry', 'quotation']))->toBe(['sales-entry']);

    // at most five, the most opened first (then the last opened)
    foreach (range(1, 7) as $index) {
        foreach (range(1, $index) as $visit) {
            $log->touch($this->employee, 'app-'.$index);
        }
    }
    expect($log->mostUsed($this->employee))->toBe(['app-7', 'app-6', 'app-5', 'app-4', 'app-3'])
        ->and($log->mostUsed($this->employee, ['app-1', 'app-2']))->toBe(['app-2', 'app-1']);
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

    // super admins too: the + to アプリ設定 is no longer in the アプリ panel (2026-11-09)
    $this->actingAs(User::factory()->superAdmin()->create());
    Livewire::test(PortalPage::class)
        ->assertSee('スペースを作成・管理')
        ->assertDontSee('title="アプリ設定"', escape: false)
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
        ->assertSee('06. 売上入力')
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

it('lets administrators change the order of the アプリ with the ⚙ (drag and drop)', function () {
    $this->actingAs($this->admin);

    Livewire::test(PortalPage::class)
        ->assertActionVisible('reorderApps')
        ->mountAction('reorderApps')
        ->assertMountedActionModalSee(['アプリの並び替え', 'ドラッグして並び替え', '06. 売上入力', '08. 日報入力'])
        ->assertMountedActionModalDontSee('09. 見積書管理')
        // 09. 見積書管理 (開発中) is not in the admin's list: it is ignored and keeps its place
        ->call('saveAppOrder', [$this->draft->id, $this->daily->id, $this->orders->id]);

    expect(BusinessApp::query()->orderBy('sort_order')->pluck('code')->all())->toBe(['daily-report-legacy', 'sales-entry', 'quotation']);
    Livewire::test(PortalPage::class)->assertSeeInOrder(['08. 日報入力', '06. 売上入力']);

    $this->actingAs($this->employee);
    Livewire::test(PortalPage::class)
        ->assertActionHidden('reorderApps')
        ->call('saveAppOrder', [$this->orders->id, $this->daily->id])
        ->assertForbidden();
    expect(BusinessApp::query()->orderBy('sort_order')->pluck('code')->first())->toBe('daily-report-legacy');
});

it('keeps the place of the apps an administrator does not see when reordering', function () {
    $super = User::factory()->superAdmin()->create();
    $this->actingAs($super);

    Livewire::test(PortalPage::class)->call('saveAppOrder', [$this->draft->id, $this->daily->id, $this->orders->id]);
    expect(BusinessApp::query()->orderBy('sort_order')->pluck('code')->all())->toBe(['quotation', 'daily-report-legacy', 'sales-entry']);

    $this->actingAs($this->admin);
    Livewire::test(PortalPage::class)->call('saveAppOrder', [$this->orders->id, $this->daily->id]);
    expect(BusinessApp::query()->orderBy('sort_order')->pluck('code')->all())->toBe(['quotation', 'sales-entry', 'daily-report-legacy'])
        ->and(BusinessApp::query()->orderBy('sort_order')->pluck('sort_order')->all())->toBe([1, 2, 3]);
});

it('shows the edit button of お知らせ to administrators only (not even disabled)', function () {
    DashboardPortal::for('erp')->fill(['notice' => '<p>お知らせ本文</p>'])->save();

    $this->actingAs($this->employee)->get('/portal')->assertOk()->assertSee('お知らせ本文')->assertDontSee('お知らせを編集');
    $this->actingAs($this->admin)->get('/portal')->assertOk()->assertSee('お知らせを編集')->assertSee('action=editNotice', false);
});

it('lets users order the apps for themselves only, and administrators change the default order', function () {
    // an employee: their own order (the apps they see only), the others keep the default
    $other = grantDashboards(User::factory()->create(), 'erp');
    $this->sales->users()->attach($other);
    portal_app('order-entry', '04. 受注入力', $this->sales)->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);

    $this->actingAs($this->employee);
    Livewire::test(PortalPage::class)
        ->assertSeeInOrder(['sfk-app-title', '06. 売上入力', '04. 受注入力'], escape: false)
        ->assertDontSee('既定の並びに戻す')
        ->call('saveMyAppOrder', [BusinessApp::query()->where('code', 'order-entry')->value('id'), $this->orders->id, $this->daily->id]);
    Livewire::test(PortalPage::class)
        ->assertSeeInOrder(['sfk-app-title', '04. 受注入力', '06. 売上入力'], escape: false)
        ->assertSee(['既定の並びに戻す', 'title="ドラッグして並び替え"'], escape: false);
    expect($this->employee->fresh()->app_order)->toBe([BusinessApp::query()->where('code', 'order-entry')->value('id'), $this->orders->id])
        ->and(BusinessApp::query()->orderBy('sort_order')->orderBy('id')->pluck('code')->first())->toBe('sales-entry');
    $this->actingAs($other);
    Livewire::test(PortalPage::class)->assertSeeInOrder(['sfk-app-title', '06. 売上入力', '04. 受注入力'], escape: false);

    // 既定の並びに戻す
    $this->actingAs($this->employee);
    Livewire::test(PortalPage::class)->call('resetMyAppOrder')->assertNotified('既定の並びに戻しました');
    expect($this->employee->fresh()->app_order)->toBeNull();

    // an administrator's drag changes the default (the registry) — for everyone without an order of their own
    $this->employee->forceFill(['app_order' => [$this->orders->id]])->save();
    $this->actingAs($this->admin);
    Livewire::test(PortalPage::class)
        ->assertSee('title="ドラッグして既定の並びを変更"', escape: false)
        ->call('saveMyAppOrder', [$this->daily->id, $this->orders->id]);
    expect($this->admin->fresh()->app_order)->toBeNull()
        ->and(BusinessApp::query()->orderBy('sort_order')->orderBy('id')->pluck('code')->take(2)->all())->toBe(['daily-report-legacy', 'sales-entry']);
    Livewire::test(PortalPage::class)
        ->assertSeeInOrder(['sfk-app-title', '08. 日報入力', '06. 売上入力'], escape: false)
        ->assertDontSee(['既定の並びに戻す', 'この並びを既定にする']);

    // an old own order of an administrator is not used: they always see (and change) the default
    $this->admin->forceFill(['app_order' => [$this->orders->id, $this->daily->id]])->save();
    Livewire::test(PortalPage::class)->assertSeeInOrder(['sfk-app-title', '08. 日報入力', '06. 売上入力'], escape: false);

    $this->actingAs($other);
    Livewire::test(PortalPage::class)->assertSeeInOrder(['sfk-app-title', '06. 売上入力', '04. 受注入力'], escape: false);
    $this->actingAs($this->employee);
    Livewire::test(PortalPage::class)->assertSeeInOrder(['sfk-app-title', '06. 売上入力', '04. 受注入力'], escape: false);
});

it('frames each よく使うアプリ on its own, with space between them', function () {
    $this->actingAs($this->admin)->get('/erp')
        ->assertOk()
        ->assertSee(['.sfk-shortcuts {', '.sfk-shortcut {', 'box-shadow: var(--sfk-ring);'], false);
});

it('lists 共通管理 first in ダッシュボード切替, each dashboard with its own icon, without a ダッシュボード entry', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Filament::setCurrentPanel('settings');

    $html = $this->actingAs($superAdmin)->get('/settings')->assertOk()->getContent();
    $menu = (string) str($html)->after('ダッシュボード切替')->before('ユーザー管理');

    expect(strpos($menu, '共通管理'))->toBeLessThan(strpos($menu, '白石封筒'))
        ->and(SettingsDashboard::shouldRegisterNavigation())->toBeFalse()
        // 共通管理: the gear, 白石封筒: the briefcase (Heroicons cog-6-tooth / briefcase), as on the portal's cards
        ->and(strpos($menu, 'M9.594 3.94'))->toBeLessThan(strpos($menu, 'M20.25 14.15'))
        ->and($menu)->not->toContain('M3.75 6A2.25');
});

it('colours text from a palette under the 文字色 button and offers 表を削除 in the お知らせ editor', function () {
    $this->actingAs($this->admin);

    Livewire::test(PortalPage::class)
        ->mountAction('editNotice')
        ->assertMountedActionModalSeeHtml('class="sfk-text-palette"')
        ->assertMountedActionModalSeeHtml('aria-label="文字色"')
        ->assertMountedActionModalSeeHtml('aria-label="表を削除"')
        ->assertMountedActionModalSeeHtml('setTextColor({ color })');
});
