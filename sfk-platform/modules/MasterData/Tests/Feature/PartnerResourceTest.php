<?php

/*
 * App 01. 取引先マスタ（得意先・仕入先） (SYSTEM_BRAIN APP_01): legacy form/record/list, BR-MD-01 (unique name/code),
 * BR-MD-02 (roles), BR-MD-06 (document routing), and access through the app permissions.
 */

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\RecordVersion;
use Modules\Core\Models\Space;
use Modules\MasterData\Domain\Partners\DocumentRecipient;
use Modules\MasterData\Filament\Resources\Partners\Pages\CreatePartner;
use Modules\MasterData\Filament\Resources\Partners\Pages\EditPartner;
use Modules\MasterData\Filament\Resources\Partners\Pages\ListPartners;
use Modules\MasterData\Filament\Resources\Partners\Pages\PartnerListViewSettings;
use Modules\MasterData\Filament\Resources\Partners\Pages\PartnerSettings;
use Modules\MasterData\Filament\Resources\Partners\Pages\ViewPartner;
use Modules\MasterData\Filament\Resources\Partners\PartnerResource;
use Modules\MasterData\Filament\Resources\Partners\Tables\PartnersTable;
use Modules\MasterData\Models\Partner;

/** @return array<string, mixed> the legacy required fields */
function partnerData(array $overrides = []): array
{
    return [
        'name' => '株式会社テスト印刷',
        'name_kana' => 'かぶしきがいしゃてすといんさつ',
        'short_name' => 'テスト印刷',
        'roles' => ['得意先'],
        'postal_code' => '100-0001',
        'address' => '東京都千代田区1-1',
        'phone' => '03-1111-2222',
        'invoice_recipient' => '得意先名',
        'delivery_note_recipient' => '得意先名',
        ...$overrides,
    ];
}

function makePartner(array $overrides = []): Partner
{
    $data = partnerData($overrides);
    $roles = $data['roles'];
    unset($data['roles']);

    return Partner::query()->create([...$data, ...Partner::roleColumns($roles)]);
}

/** Publishes the app in a space of 白石封筒 and gives the employee the listed abilities through 全員. */
function employeeWith(array $abilities): User
{
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業スペース']);
    $app = new BusinessApp(['code' => 'partner-master', 'name' => '01. 取引先マスタ（得意先・仕入先）', 'space_id' => $space->id]);
    $app->forceFill(['status' => AppStatus::Published])->save();

    $columns = [];
    foreach (AppAbility::cases() as $ability) {
        $columns[$ability->column()] = in_array($ability, $abilities, true);
    }
    $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, ...$columns]);

    $employee = grantDashboards(User::factory()->create(), 'erp');
    $space->users()->attach($employee);

    return $employee;
}

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->admin = User::factory()->superAdmin()->create(['name' => '管理者']);
});

it('registers itself as an app from the code', function () {
    expect((new AppDiscovery([base_path('modules')]))->definitions())
        ->toHaveKey('partner-master', '01. 取引先マスタ（得意先・仕入先）');
});

it('creates a partner like the legacy form and shows the saved record', function () {
    $this->actingAs($this->admin);

    $component = Livewire::test(CreatePartner::class)
        ->fillForm(partnerData([
            'roles' => ['得意先', '仕入先'],
            'registration_number' => 'T1234567890123',
            'closing_day' => '20日',
            'collection_month' => '翌月',
            'collection_day' => '末日',
            'rounding' => '四捨五入',
            'purchase_category' => '05.外注加工',
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $partner = Partner::query()->where('name', '株式会社テスト印刷')->firstOrFail();
    $component->assertRedirect(ViewPartnerUrl($partner));

    expect($partner->is_customer)->toBeTrue()
        ->and($partner->code)->toBe('000001')
        ->and($partner->name_kana)->toBe('かぶしきがいしゃてすといんさつ')
        ->and($partner->is_supplier)->toBeTrue()
        ->and($partner->is_other)->toBeFalse()
        ->and($partner->closing_day?->value)->toBe('20日')
        ->and($partner->created_by)->toBe($this->admin->id)
        ->and($partner->updated_by)->toBe($this->admin->id);
});

function ViewPartnerUrl(Partner $partner): string
{
    return PartnerResource::getUrl('view', ['record' => $partner]);
}

it('requires the legacy required fields', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreatePartner::class)
        ->fillForm(['name' => '', 'name_kana' => '', 'short_name' => '', 'roles' => [], 'postal_code' => '', 'address' => '', 'phone' => ''])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'name_kana' => 'required',
            'short_name' => 'required',
            'roles' => 'required',
            'postal_code' => 'required',
            'address' => 'required',
            'phone' => 'required',
        ]);
});

it('keeps 取引先名 unique (BR-MD-01) and checks the T-number format', function () {
    makePartner();
    $this->actingAs($this->admin);

    Livewire::test(CreatePartner::class)
        ->fillForm(partnerData(['registration_number' => '1234']))
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique', 'registration_number']);
});

it('numbers 取引先コード with 6 digits on save, skipping codes already taken (BR-MD-01)', function () {
    makePartner(['code' => '000002']);

    $first = makePartner(['name' => '一社目']);
    $second = makePartner(['name' => '二社目']);

    expect($first->code)->toBe('000001')
        ->and($second->code)->toBe('000003');
});

it('copies the customer address to the supplier side when 仕入先住所も同様 is checked', function () {
    $partner = makePartner(['supplier_same_address' => true, 'fax' => '03-1111-3333']);

    expect($partner->getAttribute('supplier_address'))->toBe('東京都千代田区1-1')
        ->and($partner->getAttribute('supplier_postal_code'))->toBe('100-0001')
        ->and($partner->getAttribute('supplier_fax'))->toBe('03-1111-3333');
});

it('routes invoices and delivery notes to the parent company or the partner (BR-MD-06)', function () {
    $partner = makePartner([
        'parent_company' => '親会社株式会社',
        'parent_postal_code' => '200-0002',
        'parent_address' => '大阪府大阪市1-1',
        'invoice_recipient' => DocumentRecipient::ParentCompany,
    ]);

    expect($partner->invoiceAddressee())->toBe(['name' => '親会社株式会社', 'postal_code' => '200-0002', 'address' => '大阪府大阪市1-1'])
        ->and($partner->deliveryNoteAddressee()['name'])->toBe('株式会社テスト印刷');
});

it('edits the roles through 取引先カテゴリ', function () {
    $partner = makePartner();
    $this->actingAs($this->admin);

    Livewire::test(EditPartner::class, ['record' => $partner->getRouteKey()])
        ->assertSchemaStateSet(['roles' => ['得意先']])
        ->fillForm(['roles' => ['仕入先', 'その他']])
        ->call('save')
        ->assertHasNoFormErrors();

    $partner->refresh();
    expect($partner->is_customer)->toBeFalse()
        ->and($partner->is_supplier)->toBeTrue()
        ->and($partner->is_other)->toBeTrue()
        ->and($partner->scopeSuppliers(Partner::query())->count())->toBe(1);
});

it('shows the record like the legacy system, with the tabs above the fields and 作成者', function () {
    $partner = makePartner(['closing_day' => '末日']);
    $this->actingAs($this->admin);

    Livewire::test(ViewPartner::class, ['record' => $partner->getRouteKey()])
        ->assertSee('取引先名')
        ->assertSee('取引先名(ふりがな)')
        ->assertSee('住所/連絡先 その他')
        ->assertSeeInOrder(['基本情報', '仕入先情報', '見積履歴', '納品履歴', '請求履歴', '取引先コード'])
        ->assertSee('関連するレコードはありません。')
        ->assertActionVisible('edit')
        ->assertActionVisible('delete')
        ->assertSee('請求締日')
        ->assertSee('末日')
        ->assertSee('得意先ID')
        ->assertSee('作成者');
});

it('puts the pencil, 「…」 and ‹ › in the record toolbar, moving in 取引先コード order', function () {
    $last = makePartner(['name' => '三社目', 'code' => '000030']);
    $first = makePartner(['name' => '一社目', 'code' => '000010']);
    $middle = makePartner(['name' => '二社目', 'code' => '000020']);
    $this->actingAs($this->admin);

    Livewire::test(ViewPartner::class, ['record' => $middle->getRouteKey()])
        ->assertSee('レコードを編集する')
        ->assertSee('その他の操作')
        ->assertSee('前のレコード')
        ->assertSee(ViewPartnerUrl($first), false)
        ->assertSee('次のレコード')
        ->assertSee(ViewPartnerUrl($last), false);

    Livewire::test(ViewPartner::class, ['record' => $first->getRouteKey()])
        ->assertDontSee('前のレコード')
        ->assertSee(ViewPartnerUrl($middle), false);
});

it('hides the pencil and 削除する from employees who may only view', function () {
    $partner = makePartner();
    $this->actingAs(employeeWith([AppAbility::View]));

    Livewire::test(ViewPartner::class, ['record' => $partner->getRouteKey()])
        ->assertActionHidden('edit')
        ->assertActionHidden('delete')
        ->assertActionHidden('duplicate')
        ->assertActionVisible('print');
});

it('offers the legacy views 取引先一覧 and （すべて）', function () {
    makePartner(['code' => 'B2']);
    makePartner(['code' => 'A1', 'name' => '仕入先株式会社', 'roles' => ['仕入先']]);
    $this->actingAs($this->admin);

    Livewire::test(ListPartners::class)
        ->assertTableColumnVisible('code')
        ->assertTableColumnVisible('closing_day')
        ->assertTableColumnDoesNotExist('registration_number')
        ->assertCanSeeTableRecords(Partner::query()->orderBy('code')->get(), inOrder: true)
        ->set('listView', 'all')
        ->assertTableColumnVisible('registration_number')
        ->call('applyAppFilter', ['conditions' => [['field' => 'roles', 'operator' => 'in', 'values' => ['仕入先']]]])
        ->assertTableColumnVisible('registration_number')
        ->assertCanSeeTableRecords(Partner::query()->where('code', 'A1')->get())
        ->assertCanNotSeeTableRecords(Partner::query()->where('code', 'B2')->get());
});

it('exports the list as CSV with the legacy system headers', function () {
    makePartner(['code' => 'C0001']);
    Carbon::setTestNow('2026-10-16 09:00:00');
    $this->actingAs($this->admin);

    Livewire::test(ListPartners::class)
        ->callAction('export')
        ->assertFileDownloaded('取引先マスタ_20261016_090000.csv');
});

it('limits employees to what the app permissions allow', function () {
    $partner = makePartner();
    $viewer = employeeWith([AppAbility::View]);
    $this->actingAs($viewer);

    $this->get('/erp/partners')->assertOk()->assertSee('株式会社テスト印刷');
    $this->get('/erp/partners/create')->assertForbidden();
    $this->get("/erp/partners/{$partner->id}/edit")->assertForbidden();
    Livewire::test(ListPartners::class)->assertActionHidden('export')->assertDontSee('レコードを追加する');
});

it('lets employees with 追加・編集・書き出し work with partners', function () {
    $editor = employeeWith([AppAbility::View, AppAbility::Create, AppAbility::Update, AppAbility::Export]);
    $this->actingAs($editor);

    Livewire::test(ListPartners::class)
        ->assertActionVisible('export')
        ->assertSee('レコードを追加する')
        ->assertDontSee('アプリの設定');

    Livewire::test(CreatePartner::class)->fillForm(partnerData())->call('create')->assertHasNoFormErrors();

    expect(Partner::query()->value('created_by'))->toBe($editor->id);
});

it('keeps employees out while the app is not published', function () {
    $viewer = employeeWith([AppAbility::View]);
    BusinessApp::query()->update(['status' => AppStatus::Testing->value]);

    $this->actingAs($viewer)->get('/erp/partners')->assertForbidden();
});

it('is in the sidebar of super admins in every stage, with its stage as a badge', function () {
    $app = new BusinessApp(['code' => 'partner-master', 'name' => '01. 取引先マスタ（得意先・仕入先）']);
    $app->forceFill(['status' => AppStatus::Testing])->save();
    $this->actingAs($this->admin);

    expect(PartnerResource::shouldRegisterNavigation())->toBeTrue()
        ->and(PartnerResource::getNavigationBadge())->toBe('テスト中');

    $this->actingAs(User::factory()->admin()->create());
    expect(PartnerResource::shouldRegisterNavigation())->toBeFalse();

    $app->forceFill(['status' => AppStatus::Published])->save();
    $this->actingAs($this->admin);
    expect(PartnerResource::getNavigationBadge())->toBeNull();
});

it('hides the collapsed menu completely and lets the app use the full screen width', function () {
    $this->actingAs($this->admin);

    expect(Filament::getPanel('erp')->isSidebarFullyCollapsibleOnDesktop())->toBeTrue()
        ->and(Filament::getPanel('erp')->isSidebarCollapsibleOnDesktop())->toBeFalse();

    $this->get('/erp/partners')
        ->assertOk()
        ->assertSee('fi-body-has-sidebar-fully-collapsible-on-desktop', false)
        ->assertSee('fi-main fi-width-full', false);
});

it('frames the list like the legacy app: title bar, breadcrumb, description, views, paging', function () {
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業スペース']);
    $app = new BusinessApp(['code' => 'partner-master', 'name' => '01. 取引先マスタ（得意先・仕入先）', 'icon_text' => '取引先', 'space_id' => $space->id]);
    $app->forceFill(['status' => AppStatus::Published])->save();
    $staff = User::factory()->create(['name' => '山田太郎', 'sales_staff_code' => '0101']);
    makePartner(['name' => '一社目'])->staff()->attach($staff);
    makePartner(['name' => '二社目']);
    $this->actingAs($this->admin);

    $this->get('/erp/partners')
        ->assertOk()
        ->assertSee('sfk-app-titlebar', false)
        ->assertSeeInOrder(['取引先', '01. 取引先マスタ（得意先・仕入先）', 'スペース: 営業スペース', 'アプリ: 01. 取引先マスタ（得意先・仕入先）'])
        ->assertSee('得意先の情報を登録・管理するマスタアプリです。')
        ->assertSeeInOrder(['取引先一覧', '（すべて）'])
        ->assertSee('1 - 2（2件中）')
        ->assertSee('レコードを追加する')
        ->assertSee('アプリの設定')
        ->assertSee('ファイルに書き出す')
        ->assertSee('取引先名(ふりがな)')
        ->assertSee('【0101】山田太郎');
});

it('pages through the list 100 records at a time', function () {
    foreach (range(1, 101) as $number) {
        makePartner(['name' => "取引先{$number}"]);
    }
    $this->actingAs($this->admin);

    Livewire::test(ListPartners::class)
        ->assertSee('1 - 100（101件中）')
        ->call('nextAppPage')
        ->assertSee('101 - 101（101件中）')
        ->call('previousAppPage')
        ->assertSee('1 - 100（101件中）');
});

it('puts キャンセル and 保存 above the form, with the tabs above the fields', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreatePartner::class)
        ->assertSeeInOrder(['キャンセル', '保存', '基本情報', '仕入先情報', '見積履歴', '納品履歴', '請求履歴', '取引先コード'])
        ->assertFormFieldDisabled('code')
        ->callAction('save')
        ->assertHasFormErrors(['name' => 'required', 'name_kana' => 'required'])
        ->fillForm(partnerData())
        ->callAction('save')
        ->assertHasNoFormErrors();

    $partner = Partner::query()->sole();

    Livewire::test(EditPartner::class, ['record' => $partner->getRouteKey()])
        ->assertSeeInOrder(['キャンセル', '保存'])
        ->assertFormFieldDisabled('code')
        ->fillForm(['short_name' => '新略名'])
        ->callAction('saveRecord')
        ->assertHasNoFormErrors();

    expect($partner->refresh()->short_name)->toBe('新略名')
        ->and($partner->code)->toBe('000001');
});

/*
 * 絞り込む (legacy dialog, design_ref/filter1-3.png): 条件 per field type, すべて / いずれかの条件, ソート, 適用 for the
 * session and 保存 as a list view (administrators).
 */

it('opens 絞り込む from the funnel next to the views', function () {
    $this->actingAs($this->admin);

    $this->get('/erp/partners')
        ->assertOk()
        ->assertSeeInOrder(['取引先一覧', '絞り込む', 'レコードを追加する'])
        ->assertSee('sfkAppFilter', false)
        ->assertSeeInOrder(['条件', 'すべての条件を満たす', 'いずれかの条件を満たす', 'すべてのレコード', 'すべてクリア', 'ソート', 'キャンセル', '保存', '適用']);

    $state = Livewire::test(ListPartners::class)->instance()->appFilterState();
    expect($state['conditions'])->toBe([])
        ->and($state['sorts'])->toBe([['field' => 'code', 'direction' => 'asc']]);
});

it('applies keyword, list-box and number conditions and lights the funnel', function () {
    $match = makePartner(['name' => '株式会社テスト印刷', 'closing_day' => '5日', 'bill_site_days' => 90]);
    $otherDay = makePartner(['name' => 'テスト紙業', 'short_name' => '紙業', 'closing_day' => '10日', 'bill_site_days' => 90]);
    $noDay = makePartner(['name' => '山田商店', 'short_name' => '山田', 'bill_site_days' => 30]);
    $this->actingAs($this->admin);

    Livewire::test(ListPartners::class)
        ->call('applyAppFilter', ['match' => 'and', 'conditions' => [
            ['field' => 'name', 'operator' => 'like', 'values' => ['テスト']],
            ['field' => 'closing_day', 'operator' => 'in', 'values' => ['5日']],
        ]])
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$otherDay, $noDay])
        ->assertSee('sfk-app-tool is-active', false)
        ->assertSee('1 - 1（1件中）')
        ->call('applyAppFilter', ['conditions' => [['field' => 'closing_day', 'operator' => 'in', 'values' => ['']]]])
        ->assertCanSeeTableRecords([$noDay])
        ->assertCanNotSeeTableRecords([$match, $otherDay])
        ->call('applyAppFilter', ['conditions' => [['field' => 'closing_day', 'operator' => 'not_in', 'values' => ['5日']]]])
        ->assertCanSeeTableRecords([$otherDay, $noDay])
        ->assertCanNotSeeTableRecords([$match])
        ->call('applyAppFilter', ['conditions' => [['field' => 'bill_site_days', 'operator' => 'gte', 'values' => ['60']]]])
        ->assertCanSeeTableRecords([$match, $otherDay])
        ->assertCanNotSeeTableRecords([$noDay])
        ->call('applyAppFilter', ['conditions' => [['field' => 'name', 'operator' => 'one_of', 'values' => ['山田商店', 'テスト紙業']]]])
        ->assertCanSeeTableRecords([$otherDay, $noDay])
        ->assertCanNotSeeTableRecords([$match]);
});

it('combines conditions by すべての条件 or いずれかの条件 and sorts by the ソート keys', function () {
    $a = makePartner(['name' => '一社目', 'short_name' => '一', 'code' => '000001', 'closing_day' => '5日']);
    $b = makePartner(['name' => '二社目', 'short_name' => '二', 'code' => '000002', 'closing_day' => '末日']);
    $c = makePartner(['name' => '三社目', 'short_name' => '三', 'code' => '000003', 'closing_day' => '20日']);
    $this->actingAs($this->admin);

    $conditions = [
        ['field' => 'closing_day', 'operator' => 'in', 'values' => ['5日']],
        ['field' => 'name', 'operator' => 'eq', 'values' => ['二社目']],
    ];

    Livewire::test(ListPartners::class)
        ->call('applyAppFilter', ['match' => 'and', 'conditions' => $conditions])
        ->assertCountTableRecords(0)
        ->call('applyAppFilter', ['match' => 'or', 'conditions' => $conditions, 'sorts' => [['field' => 'code', 'direction' => 'desc']]])
        ->assertCanSeeTableRecords([$b, $a], inOrder: true)
        ->assertCanNotSeeTableRecords([$c]);
});

it('filters 取引先カテゴリ, 自社担当者 with ログインユーザー and 作成日時 by relative dates', function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $customer = makePartner(['name' => '得意先A', 'short_name' => 'A', 'roles' => ['得意先']]);
    $customer->staff()->attach($this->admin);
    $this->travelTo(Carbon::parse('2026-09-10 10:00'));
    $supplier = makePartner(['name' => '仕入先B', 'short_name' => 'B', 'roles' => ['仕入先']]);
    $this->travelTo(Carbon::parse('2026-10-16 15:00'));
    $this->actingAs($this->admin);

    Livewire::test(ListPartners::class)
        ->call('applyAppFilter', ['conditions' => [['field' => 'roles', 'operator' => 'in', 'values' => ['仕入先']]]])
        ->assertCanSeeTableRecords([$supplier])
        ->assertCanNotSeeTableRecords([$customer])
        ->call('applyAppFilter', ['conditions' => [['field' => 'roles', 'operator' => 'not_in', 'values' => ['仕入先']]]])
        ->assertCanSeeTableRecords([$customer])
        ->assertCanNotSeeTableRecords([$supplier])
        ->call('applyAppFilter', ['conditions' => [['field' => 'staff', 'operator' => 'in', 'values' => ['login_user']]]])
        ->assertCanSeeTableRecords([$customer])
        ->assertCanNotSeeTableRecords([$supplier])
        ->call('applyAppFilter', ['conditions' => [['field' => 'staff', 'operator' => 'in', 'values' => ['']]]])
        ->assertCanSeeTableRecords([$supplier])
        ->assertCanNotSeeTableRecords([$customer])
        ->call('applyAppFilter', ['conditions' => [['field' => 'created_at', 'operator' => 'eq', 'values' => [''], 'mode' => 'today']]])
        ->assertCanSeeTableRecords([$customer])
        ->assertCanNotSeeTableRecords([$supplier])
        ->call('applyAppFilter', ['conditions' => [['field' => 'created_at', 'operator' => 'eq', 'values' => [''], 'mode' => 'last_month', 'sub' => 'all']]])
        ->assertCanSeeTableRecords([$supplier])
        ->assertCanNotSeeTableRecords([$customer])
        ->call('applyAppFilter', ['conditions' => [['field' => 'created_at', 'operator' => 'lt', 'values' => ['2026-10-01'], 'mode' => 'specified']]])
        ->assertCanSeeTableRecords([$supplier])
        ->assertCanNotSeeTableRecords([$customer])
        ->call('applyAppFilter', ['conditions' => [['field' => 'created_at', 'operator' => 'eq', 'values' => ['2026-10-16'], 'mode' => 'specified', 'time' => '10:00']]])
        ->assertCanSeeTableRecords([$customer])
        ->assertCanNotSeeTableRecords([$supplier]);
});

it('drops an applied filter when another view is chosen', function () {
    $a = makePartner(['name' => '一社目', 'short_name' => '一']);
    $b = makePartner(['name' => '二社目', 'short_name' => '二']);
    $this->actingAs($this->admin);

    Livewire::test(ListPartners::class)
        ->call('applyAppFilter', ['conditions' => [['field' => 'name', 'operator' => 'eq', 'values' => ['一社目']]]])
        ->assertCanNotSeeTableRecords([$b])
        ->set('listView', 'all')
        ->assertSet('appFilter', null)
        ->assertCanSeeTableRecords([$a, $b])
        ->assertDontSee('sfk-app-tool is-active', false);
});

it('lets administrators save the filter as a list view with a 一覧名', function () {
    $a = makePartner(['name' => '一社目', 'short_name' => '一', 'closing_day' => '5日']);
    $b = makePartner(['name' => '二社目', 'short_name' => '二', 'closing_day' => '末日']);
    makePartner(['name' => '三社目', 'short_name' => '三']);
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業スペース']);
    $app = new BusinessApp(['code' => 'partner-master', 'name' => '01. 取引先マスタ（得意先・仕入先）', 'space_id' => $space->id]);
    $app->forceFill(['status' => AppStatus::Published])->save();
    $this->actingAs($this->admin);
    $filter = ['conditions' => [['field' => 'closing_day', 'operator' => 'in', 'values' => ['5日', '末日']]], 'sorts' => [['field' => 'code', 'direction' => 'desc']]];

    $component = Livewire::test(ListPartners::class);
    expect($component->instance()->saveAppFilter($filter, ''))->toBe('一覧名を入力してください。')
        ->and($component->instance()->saveAppFilter($filter, '取引先一覧'))->toBe('この一覧名はすでに使われています。');

    $component->call('saveAppFilter', $filter, '締日5日・末日')->assertNotified('一覧を保存しました');

    $view = $app->listViews()->where('name', '締日5日・末日')->sole();
    expect($view->columns)->toBe(PartnersTable::LIST_COLUMNS)
        ->and($view->created_by)->toBe($this->admin->id);

    $component->assertSet('listView', (string) $view->id)
        ->assertSet('appFilter', null)
        ->assertCanSeeTableRecords([$b, $a], inOrder: true)
        ->assertCountTableRecords(2);

    Livewire::withQueryParams(['view' => (string) $view->id])
        ->test(ListPartners::class)
        ->assertSeeInOrder(['取引先一覧', '締日5日・末日', '（すべて）'])
        ->assertCountTableRecords(2)
        ->assertSee('sfk-app-tool is-active', false);
});

it('does not let employees save list views', function () {
    makePartner();
    $employee = employeeWith([AppAbility::View]);
    $this->actingAs($employee);

    $component = Livewire::test(ListPartners::class);
    expect($component->instance()->filterDialog()['canSave'])->toBeFalse()
        ->and($component->instance()->saveAppFilter(['conditions' => []], '私の一覧'))->toBe('一覧を保存する権限がありません。')
        ->and(BusinessApp::query()->where('code', 'partner-master')->sole()->listViews()->pluck('name')->all())->toBe(['取引先一覧']);
});

/*
 * 一覧 (legacy アプリの設定 → 一覧): stored views with the topmost as default and （すべて） last, 一覧を追加する / 編集 /
 * 複製 / 削除 / ドラッグして並び替え, 表形式 columns or カレンダー形式, and each user's 表示件数.
 */

function publishedPartnerApp(): BusinessApp
{
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業スペース']);
    $app = new BusinessApp(['code' => 'partner-master', 'name' => '01. 取引先マスタ（得意先・仕入先）', 'space_id' => $space->id]);
    $app->forceFill(['status' => AppStatus::Published])->save();

    return $app;
}

it('stores the default view 取引先一覧 once and keeps （すべて） last', function () {
    $app = publishedPartnerApp();
    $this->actingAs($this->admin);

    $list = Livewire::test(ListPartners::class);
    expect(array_values($list->instance()->allListViews()))->toBe(['取引先一覧', '（すべて）'])
        ->and($app->listViews()->sole()->columns)->toBe(PartnersTable::LIST_COLUMNS)
        ->and($app->refresh()->list_views_seeded_at)->not->toBeNull();

    $app->listViews()->delete();

    expect(array_values(Livewire::test(ListPartners::class)->instance()->allListViews()))->toBe(['（すべて）']);
});

it('opens アプリの設定 → 一覧 from the gear, for administrators only', function () {
    $employee = employeeWith([AppAbility::View, AppAbility::Create, AppAbility::Update, AppAbility::Delete]);
    $this->actingAs($this->admin);

    $this->get('/erp/partners')->assertSee(PartnerListViewSettings::getUrl(), false);

    $this->get(PartnerListViewSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['アプリ: 01. 取引先マスタ（得意先・仕入先）', 'アプリの設定', '一覧', '設定', '作成済みの一覧', '一覧を追加する', 'ドラッグして並び替え', '取引先一覧', '編集', '複製', '削除', '（すべて）']);

    $this->actingAs($employee);
    $this->get(PartnerListViewSettings::getUrl())->assertForbidden();
    $this->get('/erp/partners')->assertOk()->assertDontSee(PartnerListViewSettings::getUrl(), false);
});

it('adds a 表形式 view with its own columns, 絞り込み and ソート', function () {
    $app = publishedPartnerApp();
    $tokyo = makePartner(['name' => '東京印刷', 'short_name' => '東京', 'code' => '000001', 'closing_day' => '末日', 'phone' => '03-1111-1111']);
    $osaka = makePartner(['name' => '大阪紙業', 'short_name' => '大阪', 'code' => '000002', 'closing_day' => '20日']);
    $this->actingAs($this->admin);

    $settings = Livewire::test(PartnerListViewSettings::class)->call('addView')->assertSee('レコード一覧の表示形式');
    expect($settings->instance()->saveView(['name' => '', 'type' => 'table', 'columns' => []]))->toBe([
        'name' => '一覧名を入力してください。',
        'columns' => '表示するフィールドを1つ以上配置してください。',
    ])
        ->and($settings->instance()->saveView(['name' => '（すべて）', 'columns' => ['name']]))->toBe(['name' => 'この一覧名はすでに使われています。'])
        ->and($settings->instance()->saveView(['name' => '取引先一覧', 'columns' => ['name']]))->toBe(['name' => 'この一覧名はすでに使われています。']);

    $settings->call('saveView', [
        'name' => '末締め',
        'type' => 'table',
        'columns' => ['phone', 'name', 'secret', 'phone'],
        'filter' => ['conditions' => [['field' => 'closing_day', 'operator' => 'in', 'values' => ['末日']]], 'sorts' => [['field' => 'code', 'direction' => 'desc']]],
    ])
        ->assertNotified('一覧を保存しました')
        ->assertSet('editing', null);

    $view = $app->listViews()->where('name', '末締め')->sole();
    expect($view->columns)->toBe(['phone', 'name'])
        ->and($view->filter['conditions'][0]['values'])->toBe(['末日']);

    Livewire::withQueryParams(['view' => (string) $view->id])->test(ListPartners::class)
        ->assertSeeInOrder(['取引先一覧', '末締め', '（すべて）'])
        ->assertTableColumnVisible('phone')
        ->assertTableColumnVisible('name')
        ->assertTableColumnDoesNotExist('code')
        ->assertSeeInOrder(['TEL(得意先)', '取引先名'])
        ->assertCanSeeTableRecords([$tokyo])
        ->assertCanNotSeeTableRecords([$osaka]);
});

it('shows a カレンダー形式 view as a month calendar by its 日付 and タイトル', function () {
    $app = publishedPartnerApp();
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    makePartner(['name' => '暦の取引先', 'short_name' => '暦']);
    $this->actingAs($this->admin);

    $settings = Livewire::test(PartnerListViewSettings::class)->call('addView');
    expect($settings->instance()->saveView(['name' => '登録カレンダー', 'type' => 'calendar']))->toBe([
        'dateField' => '日付を選択してください。',
        'titleField' => 'タイトルを選択してください。',
    ]);
    $settings->call('saveView', ['name' => '登録カレンダー', 'type' => 'calendar', 'dateField' => 'created_at', 'titleField' => 'name']);

    $view = $app->listViews()->where('name', '登録カレンダー')->sole();
    expect($view->type->value)->toBe('calendar')->and($view->columns)->toBeNull();

    $this->get('/erp/partners?view='.$view->id)
        ->assertOk()
        ->assertSee('2026年10月')
        ->assertSeeInOrder(['日', '月', '火', '水', '木', '金', '土'])
        ->assertSee('暦の取引先')
        ->assertSee(ViewPartnerUrl(Partner::query()->sole()), false)
        ->assertDontSee('件中）');

    Livewire::withQueryParams(['view' => (string) $view->id])->test(ListPartners::class)
        ->call('shiftCalendarMonth', 1)
        ->assertSet('calendarMonth', '2026-11')
        ->assertSee('2026年11月')
        ->assertDontSee('暦の取引先')
        ->call('showCurrentCalendarMonth')
        ->assertSee('暦の取引先');
});

it('edits, copies (「… - コピー」), deletes and reorders views; the topmost one is the default', function () {
    $app = publishedPartnerApp();
    $this->actingAs($this->admin);
    Livewire::test(PartnerListViewSettings::class); // stores the default view
    $default = $app->listViews()->sole();

    Livewire::test(PartnerListViewSettings::class)
        ->call('editView', $default->id)
        ->assertSet('editing', (string) $default->id)
        ->call('saveView', ['name' => '取引先一覧(電話)', 'type' => 'table', 'columns' => ['name', 'phone']])
        ->assertSet('editing', null)
        ->call('copyView', $default->id)
        ->call('copyView', $default->id)
        ->assertNotified('一覧を複製しました');

    expect($app->listViews()->pluck('name')->all())->toBe(['取引先一覧(電話)', '取引先一覧(電話) - コピー', '取引先一覧(電話) - コピー - コピー'])
        ->and($app->listViews()->where('name', '取引先一覧(電話) - コピー')->sole()->columns)->toBe(['name', 'phone']);

    $ids = $app->listViews()->pluck('id')->all();
    Livewire::test(PartnerListViewSettings::class)
        ->call('reorderViews', [$ids[2], $ids[0], $ids[1]])
        ->mountAction('deleteView', ['view' => $ids[1]])
        ->assertMountedActionModalSee('「取引先一覧(電話) - コピー」を削除しますか？')
        ->assertMountedActionModalDontSee('本当に実行しますか')
        ->callMountedAction()
        ->assertNotified('一覧を削除しました');

    expect($app->listViews()->pluck('name')->all())->toBe(['取引先一覧(電話) - コピー - コピー', '取引先一覧(電話)']);

    Livewire::test(ListPartners::class)->assertSet('listView', (string) $ids[2]);
});

it('lets each user choose 表示件数 for every app list', function () {
    foreach (range(1, 25) as $number) {
        makePartner(['name' => "取引先{$number}", 'short_name' => "略{$number}"]);
    }
    $this->actingAs($this->admin);

    Livewire::test(ListPartners::class)
        ->assertSee('1 - 25（25件中）')
        ->callAction('pageSize', data: ['size' => '20'])
        ->assertSee('1 - 20（25件中）');

    expect($this->admin->refresh()->list_page_size)->toBe(20);

    Livewire::test(ListPartners::class)->assertSee('1 - 20（25件中）')->assertCountTableRecords(25);
});

it('sorts by every column on a click of its header, switching between 昇順 and 降順', function () {
    $a = makePartner(['name' => 'あ商事', 'short_name' => 'あ', 'phone' => '03-0000-0002', 'roles' => ['仕入先']]);
    $b = makePartner(['name' => 'い商事', 'short_name' => 'い', 'phone' => '03-0000-0001', 'roles' => ['得意先']]);
    $staff = User::factory()->create(['name' => '佐藤']);
    $a->staff()->attach($staff);
    $this->actingAs($this->admin);

    $list = Livewire::test(ListPartners::class);
    expect($list->instance()->getTable()->getColumn('phone')->isSortable())->toBeTrue()
        ->and($list->instance()->getTable()->getColumn('closing_day')->isSortable())->toBeTrue();

    $list->call('sortTable', 'phone')
        ->assertSet('tableSort', 'phone:asc')
        ->assertCanSeeTableRecords([$b, $a], inOrder: true)
        ->call('sortTable', 'phone')
        ->assertSet('tableSort', 'phone:desc')
        ->assertCanSeeTableRecords([$a, $b], inOrder: true)
        ->call('sortTable', 'phone')
        ->assertSet('tableSort', 'phone:asc')
        ->call('sortTable', 'roles')
        ->assertCanSeeTableRecords([$b, $a], inOrder: true)
        ->call('sortTable', 'roles')
        ->assertCanSeeTableRecords([$a, $b], inOrder: true)
        ->call('sortTable', 'staff')
        ->assertSet('tableSort', 'staff:asc')
        ->assertCountTableRecords(2);
});

it('offers 200 and 300 件 in 表示件数', function () {
    $this->actingAs($this->admin);

    expect(AppListRecords::PAGE_SIZES)->toBe([20, 40, 60, 80, 100, 200, 300]);

    Livewire::test(ListPartners::class)->callAction('pageSize', data: ['size' => '300'])->assertHasNoActionErrors();

    expect($this->admin->refresh()->list_page_size)->toBe(300);
});

it('puts the search box on the left and one horizontal scroll at the end of the list', function () {
    $this->actingAs($this->admin);

    $this->get('/erp/partners')
        ->assertOk()
        ->assertSee('.sfk-app-page-list .fi-ta-header-toolbar > :not(.fi-ta-actions) { order: -1; margin-inline-start: 0; }', escape: false)
        ->assertSee('.sfk-app-page-list .fi-ta-main { overflow-x: auto; }', escape: false);
});

/*
 * Record features of the legacy app screens: アプリの設定 → 設定 (アクセス権, その他の設定), レコードを再利用する,
 * 印刷する, the ユーザー情報 popup and the 変更履歴 (versions, 更新内容を表示, このバージョンに戻す).
 */

it('reuses a record without its code, attachments and history (レコードを再利用する)', function () {
    $app = publishedPartnerApp();
    $staff = User::factory()->create(['name' => '佐藤']);
    $source = makePartner(['name' => '元の取引先', 'short_name' => '元', 'code' => '000005', 'roles' => ['仕入先'], 'contract_files' => ['partners/a.pdf'], 'phone' => '03-1234-5678']);
    $source->staff()->attach($staff);
    $this->actingAs($this->admin);

    Livewire::test(ViewPartner::class, ['record' => $source->getRouteKey()])
        ->assertActionVisible('duplicate')
        ->assertActionHasUrl('duplicate', PartnerResource::getUrl('create', ['duplicate' => $source->id]));

    Livewire::withQueryParams(['duplicate' => (string) $source->id])
        ->test(CreatePartner::class)
        ->assertFormSet(['name' => '元の取引先', 'phone' => '03-1234-5678', 'roles' => ['仕入先'], 'staff' => [$staff->id], 'code' => null])
        ->fillForm(['name' => '再利用した取引先'])
        ->call('create')
        ->assertHasNoFormErrors();

    $copy = Partner::query()->where('name', '再利用した取引先')->sole();
    expect($copy->code)->not->toBe('000005')
        ->and($copy->is_supplier)->toBeTrue()
        ->and($copy->contract_files)->toBeEmpty()
        ->and($copy->staff->pluck('id')->all())->toBe([$staff->id])
        ->and(RecordVersion::query()->where('record_id', $copy->id)->pluck('version')->all())->toBe([1]);

    $app->forceFill(['record_duplicate_enabled' => false])->save();

    Livewire::test(ViewPartner::class, ['record' => $source->getRouteKey()])->assertActionHidden('duplicate');
    Livewire::withQueryParams(['duplicate' => (string) $source->id])->test(CreatePartner::class)->assertFormSet(['name' => null]);
});

it('keeps the 変更履歴 of a record, shows a version with its changes and restores it', function () {
    publishedPartnerApp();
    $this->actingAs($this->admin);

    Livewire::test(CreatePartner::class)->fillForm(partnerData(['phone' => '03-1111-1111']))->call('create')->assertHasNoFormErrors();
    $partner = Partner::query()->sole();

    Livewire::test(EditPartner::class, ['record' => $partner->getRouteKey()])->fillForm(['phone' => '03-2222-2222'])->call('save')->assertHasNoFormErrors();
    Livewire::test(EditPartner::class, ['record' => $partner->getRouteKey()])->call('save'); // no change: no version

    $versions = RecordVersion::query()->where('record_id', $partner->id)->orderBy('version')->get();
    expect($versions->pluck('version')->all())->toBe([1, 2])
        ->and($versions[0]->event)->toBe('created')
        ->and($versions[1]->changed)->toBe(['phone'])
        ->and($versions[1]->user_id)->toBe($this->admin->id);

    Livewire::test(ViewPartner::class, ['record' => $partner->getRouteKey()])
        ->assertSeeInOrder(['変更履歴', 'バージョン 2', '更新', 'TEL(得意先)', '更新内容を表示', 'バージョン 1', '作成', '初版を表示', '初版に戻す'])
        ->assertSee('03-2222-2222')
        ->call('showVersion', 2)
        ->assertSee('sfk-history-changed', false)
        ->call('showVersion', 1)
        ->assertSee('03-1111-1111')
        ->assertSee('最新の内容を表示')
        ->call('showLatest')
        ->assertSee('03-2222-2222')
        ->call('showVersion', 2)
        ->callAction('restoreVersion', arguments: ['version' => 1])
        ->assertNotified('バージョン 1 に戻しました')
        ->assertSet('historyVersion', null)
        ->assertSee('03-1111-1111')
        ->assertDontSee('最新の内容を表示');

    expect($partner->refresh()->phone)->toBe('03-1111-1111');
    $restored = RecordVersion::query()->where('record_id', $partner->id)->where('version', 3)->sole();
    expect($restored->event)->toBe('restored')->and($restored->restored_from)->toBe(1)->and($restored->changed)->toBe(['phone']);

    $partner->delete();
    expect(RecordVersion::query()->where('record_id', $partner->id)->count())->toBe(0);
});

it('stops recording and hides the 変更履歴 when it is switched off in その他の設定', function () {
    $app = publishedPartnerApp();
    $partner = makePartner();
    $this->actingAs($this->admin);

    Livewire::test(PartnerSettings::class)
        ->assertSet('historyEnabled', true)
        ->set('historyEnabled', false)
        ->set('duplicateEnabled', false)
        ->call('saveMisc')
        ->assertNotified('設定を保存しました');

    expect($app->refresh()->record_history_enabled)->toBeFalse()->and($app->record_duplicate_enabled)->toBeFalse();

    Livewire::test(EditPartner::class, ['record' => $partner->getRouteKey()])->fillForm(['phone' => '03-9999-9999'])->call('save');
    expect(RecordVersion::query()->count())->toBe(0);

    Livewire::test(ViewPartner::class, ['record' => $partner->getRouteKey()])->assertDontSee('変更履歴');
});

it('opens アプリの設定 → 設定 with アクセス権 and その他の設定, for administrators only', function () {
    $employee = employeeWith([AppAbility::View]);
    $this->actingAs($this->admin);

    $this->get(PartnerSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['一覧', '設定', 'アクセス権', 'アプリ', '高度な設定', 'その他の設定', '「レコードの再利用」機能を有効にする', 'レコードの変更履歴を記録する', '保存']);

    $this->get('/erp/partners')->assertSeeInOrder(['アプリの設定メニュー', 'この一覧の設定を開く', '一覧', '設定', 'アクセス権', 'その他の設定']);

    $this->actingAs($employee);
    $this->get(PartnerSettings::getUrl())->assertForbidden();
});

it('prints a record without the header, menus and history (印刷する)', function () {
    publishedPartnerApp();
    $partner = makePartner(['name' => '印刷する取引先']);
    $this->actingAs($this->admin);

    Livewire::test(ViewPartner::class, ['record' => $partner->getRouteKey()])
        ->assertActionHasUrl('print', PartnerResource::getUrl('view', ['record' => $partner, 'print' => 1]));

    $this->get(PartnerResource::getUrl('view', ['record' => $partner, 'print' => 1]))
        ->assertOk()
        ->assertSee('sfk-app-page-print', false)
        ->assertSeeInOrder(['印刷', '閉じる', '印刷する取引先'])
        ->assertDontSee('id="sfk-history-title"', false)
        ->assertDontSee('レコードを編集する');
});

it('opens the ユーザー情報 popup from user names, with the profile link only for those who may open it', function () {
    $employee = employeeWith([AppAbility::View]);
    $staff = User::factory()->create(['name' => '佐藤 花子']);
    $partner = makePartner();
    $partner->staff()->attach($staff);
    $this->actingAs($this->admin);

    Livewire::test(ViewPartner::class, ['record' => $partner->getRouteKey()])
        ->assertSee('ユーザー情報（佐藤 花子）')
        ->assertSee('プロフィールを表示');

    Livewire::test(ListPartners::class)->assertSee('ユーザー情報（佐藤 花子）');

    $this->actingAs($employee);
    Livewire::test(ViewPartner::class, ['record' => $partner->getRouteKey()])
        ->assertSee('ユーザー情報（佐藤 花子）')
        ->assertDontSee('プロフィールを表示');
});
