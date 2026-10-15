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
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;
use Modules\MasterData\Domain\Partners\DocumentRecipient;
use Modules\MasterData\Filament\Resources\Partners\Pages\CreatePartner;
use Modules\MasterData\Filament\Resources\Partners\Pages\EditPartner;
use Modules\MasterData\Filament\Resources\Partners\Pages\ListPartners;
use Modules\MasterData\Filament\Resources\Partners\Pages\ViewPartner;
use Modules\MasterData\Filament\Resources\Partners\PartnerResource;
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

it('offers the legacy views 取引先一覧 and （すべて）', function () {
    makePartner(['code' => 'B2']);
    makePartner(['code' => 'A1', 'name' => '仕入先株式会社', 'roles' => ['仕入先']]);
    $this->actingAs($this->admin);

    Livewire::test(ListPartners::class)
        ->assertTableColumnVisible('code')
        ->assertTableColumnVisible('closing_day')
        ->assertTableColumnHidden('registration_number')
        ->assertCanSeeTableRecords(Partner::query()->orderBy('code')->get(), inOrder: true)
        ->set('listView', 'all')
        ->assertTableColumnVisible('registration_number')
        ->filterTable('role', '仕入先')
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
