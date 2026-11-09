<?php

/*
 * The screens of 16 申請管理, 17 安全衛生チェック and 18 アルコールチェック (SYSTEM_BRAIN APP_16 / APP_17 / APP_18): the apps
 * register with the switches of their legacy templates; 新規 is a quick entry (Core QuickEntry, user 2026-11-09); a request
 * goes 申請前 → 第一申請中 → 承認済 (or back with 差戻し) and employees only see their own requests; a checklist keeps its rows
 * and is reused for the next month; an alcohol check records the checks before and after driving.
 */

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Application\Records\RecordRules;
use Modules\Core\Application\Records\RecordValidator;
use Modules\Core\Domain\Apps\AccessSubject;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\FieldAccessLevel;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Models\AppListView;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;
use Modules\HumanResources\Application\AlcoholChecks\AlcoholCheckRules;
use Modules\HumanResources\Application\LeaveRequests\LeaveRequestRules;
use Modules\HumanResources\Application\LeaveRequests\LeaveRequestWorkflow;
use Modules\HumanResources\Application\SafetyChecks\SafetyCheckRules;
use Modules\HumanResources\Domain\LeaveRequests\LeaveRequestStatus;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\AlcoholCheckResource;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages\EditAlcoholCheck;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages\ListAlcoholChecks;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages\ViewAlcoholCheck;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\QuickEntry\QuickAlcoholCheck;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestResource;
use Modules\HumanResources\Filament\Resources\LeaveRequests\Pages\EditLeaveRequest;
use Modules\HumanResources\Filament\Resources\LeaveRequests\Pages\LeaveRequestSettings;
use Modules\HumanResources\Filament\Resources\LeaveRequests\Pages\ListLeaveRequests;
use Modules\HumanResources\Filament\Resources\LeaveRequests\Pages\ViewLeaveRequest;
use Modules\HumanResources\Filament\Resources\LeaveRequests\QuickEntry\QuickLeaveRequest;
use Modules\HumanResources\Filament\Resources\SafetyChecks\Pages\ListSafetyChecks;
use Modules\HumanResources\Filament\Resources\SafetyChecks\Pages\ViewSafetyCheck;
use Modules\HumanResources\Filament\Resources\SafetyChecks\QuickEntry\QuickSafetyCheck;
use Modules\HumanResources\Filament\Resources\SafetyChecks\SafetyCheckResource;
use Modules\HumanResources\Models\AlcoholCheck;
use Modules\HumanResources\Models\LeaveRequest;
use Modules\HumanResources\Models\SafetyCheck;

/** The three HR apps, published in a space of 白石封筒 with 閲覧 / 追加 / 編集 for everyone in it. */
function publishHrApps(User ...$members): void
{
    $resources = [LeaveRequestResource::class, SafetyCheckResource::class, AlcoholCheckResource::class];
    $codes = [];
    $defaults = [];
    foreach ($resources as $resource) {
        $codes[$resource::appCode()] = $resource::appName();
        $defaults[$resource::appCode()] = $resource::appSettingDefaults();
    }

    app(AppLifecycle::class)->syncWithCode($codes, $defaults);
    $space = Space::query()->firstOrCreate(['panel_id' => 'erp', 'name' => '総務スペース']);
    $space->users()->attach(array_map(fn (User $member): int => $member->id, $members));
    BusinessApp::query()->whereIn('code', array_keys($codes))->get()->each(function (BusinessApp $app) use ($space): void {
        $app->forceFill(['status' => AppStatus::Published])->save();
        $app->spaces()->syncWithoutDetaching([$space->id]);
        $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true, AppAbility::Create->column() => true,
            AppAbility::Update->column() => true]);
    });
}

function hrView(string $code, string $name): string
{
    return (string) AppListView::query()->where('business_app_id', BusinessApp::query()->where('code', $code)->value('id'))->where('name', $name)->value('id');
}

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->travelTo(CarbonImmutable::parse('2026-10-21 10:00'));
    $this->admin = User::factory()->superAdmin()->create(['name' => '管理者']);
    $this->employee = grantDashboards(User::factory()->create(['name' => '申請 太郎']), 'erp');
    $this->colleague = grantDashboards(User::factory()->create(['name' => '同僚 花子']), 'erp');
    publishHrApps($this->employee, $this->colleague);
});

it('registers the three apps from the code, with 一括削除 off as in their legacy templates', function () {
    expect((new AppDiscovery([base_path('modules')]))->definitions())
        ->toHaveKey('leave-request', '16. 申請管理')
        ->toHaveKey('safety-check', '17. 安全衛生チェック')
        ->toHaveKey('alcohol-check', '18. アルコールチェック');

    expect(BusinessApp::query()->whereIn('code', ['leave-request', 'safety-check', 'alcohol-check'])->pluck('bulk_delete_enabled')->unique()->all())->toBe([false]);
});

it('takes a request through 申請する, 差戻し and 承認済, and shows employees only their own', function () {
    $this->actingAs($this->employee);

    // 新規: the quick entry (user, 2026-11-09) — taps move on, the times of an early leave in 30-minute steps (BR-HR-02)
    expect(LeaveRequestResource::hasPage('create'))->toBeFalse();
    Livewire::test(QuickLeaveRequest::class, ['open' => true])
        ->assertSee('申請を追加')
        ->assertSet('data.applicant_id', $this->employee->id)
        ->call('choose', 'request_type', '早退申請')
        ->assertSet('screen', 'category')
        ->call('choose', 'category', '早退(時間欠勤)')
        ->assertSet('screen', 'date')
        ->call('useChip', 'target_on', '2026-10-23')
        ->assertSet('screen', 'times')
        ->set('data.total_time', '01:15')
        ->call('next')
        ->assertHasErrors(['data.total_time' => 'regex'])
        ->set('data.total_time', '01:30')
        ->set('data.clock_time', '15:30')
        ->call('next')
        ->assertSet('screen', 'reason')
        ->call('next')
        ->assertHasErrors(['data.reason' => 'required'])
        ->set('data.reason', '通院のため')
        ->call('next')
        ->assertSet('screen', 'review')
        ->assertSee(['早退申請', '早退(時間欠勤)', '2026-10-23', '01:30', '15:30', '通院のため', '申請 太郎'])
        ->call('register')
        ->assertHasNoErrors();

    $request = LeaveRequest::query()->sole();
    $this->actingAs($this->colleague);
    $others = LeaveRequest::query()->create(['request_type' => '休暇申請', 'applicant_id' => $this->colleague->id, 'category' => '有給休暇', 'target_on' => '2026-10-30',
        'reason' => '私用']);
    $this->actingAs($this->employee);
    expect($request->status)->toBe(LeaveRequestStatus::Draft);

    // employees see their own requests only (legacy app description)
    Livewire::test(ListLeaveRequests::class)->assertSee('申請一覧')->assertCanSeeTableRecords([$request])->assertCanNotSeeTableRecords([$others]);
    $this->get(LeaveRequestResource::getUrl('view', ['record' => $others]))->assertNotFound();

    Livewire::test(ViewLeaveRequest::class, ['record' => $request->getRouteKey()])
        ->assertSeeInOrder(['ステータス', '申請前', '作業者', '申請 太郎', '申請区分', '早退申請', '申請分類', '早退(時間欠勤)', '対象日付', '2026-10-23', '申請者', '申請 太郎',
            '01:30', '15:30', '申請理由', '通院のため'])
        ->assertActionVisible(TestAction::make('submit'))
        ->assertActionHidden(TestAction::make('approve'))
        ->callAction(TestAction::make('submit'));
    expect($request->fresh()->status)->toBe(LeaveRequestStatus::Pending)
        ->and($this->admin->notifications()->count())->toBe(1);
    Livewire::test(ViewLeaveRequest::class, ['record' => $request->getRouteKey()])->assertActionHidden(TestAction::make('approve'))->assertActionHidden(TestAction::make('submit'));

    // the administrators decide: 差戻し, then 承認済 after it is sent again
    $this->actingAs($this->admin);
    Livewire::test(ListLeaveRequests::class)
        ->assertCanSeeTableRecords([$request, $others])
        ->set('listView', hrView('leave-request', '作業者が自分'))
        ->assertCanSeeTableRecords([$request])
        ->assertCanNotSeeTableRecords([$others])
        ->set('listView', hrView('leave-request', '休暇申請一覧'))
        ->assertCanSeeTableRecords([$others])
        ->assertCanNotSeeTableRecords([$request]);
    Livewire::test(ViewLeaveRequest::class, ['record' => $request->getRouteKey()])->callAction(TestAction::make('sendBack'));
    expect($request->fresh()->status)->toBe(LeaveRequestStatus::Draft)->and($this->employee->notifications()->count())->toBe(1);

    app(LeaveRequestWorkflow::class)->run($request->fresh(), $this->employee, '申請する', '16. 申請管理', 'http://localhost');
    Livewire::test(ViewLeaveRequest::class, ['record' => $request->getRouteKey()])->callAction(TestAction::make('approve'));
    expect($request->fresh()->status)->toBe(LeaveRequestStatus::Approved);

    Livewire::test(ListLeaveRequests::class)
        ->set('listView', hrView('leave-request', '未承認一覧'))
        ->assertCanNotSeeTableRecords([$request])
        ->assertCanSeeTableRecords([$others]);
});

it('keeps a checklist with its rows and reuses it for the next month', function () {
    $this->actingAs($this->employee);
    $rows = [
        ['number' => 1, 'item' => '整理整頓ができている', 'answer' => 'はい', 'checked' => true, 'notes' => ''],
        ['number' => 2, 'item' => '保護具を着用している', 'answer' => 'いいえ', 'checked' => true, 'notes' => '手袋の替えが必要'],
        ['number' => 3, 'item' => '通路が確保されている', 'answer' => 'はい', 'checked' => false, 'notes' => ''],
    ];

    // every row needs its チェック (legacy form): 次へ marks the row; 「すべてチェック」 checks them all
    expect(SafetyCheckResource::hasPage('create'))->toBeFalse();
    Livewire::test(QuickSafetyCheck::class, ['open' => true])
        ->set('data.checked_on', '2026-09-30')
        ->call('choose', 'department', '製造')
        ->assertSet('screen', 'machine')
        ->set('data.department_machine', '製袋機1号')
        ->call('next')
        ->assertSet('screen', 'checklist')
        ->set('data.items', $rows)
        ->call('next')
        ->assertHasErrors(['data.items.2.checked' => 'accepted'])
        ->assertSee('チェックされていない項目があります。')
        ->assertSet('screen', 'checklist')
        ->call('checkAll')
        ->call('next')
        ->assertSet('screen', 'review')
        ->assertSee(['製造', '製袋機1号', '3項目（✓ 3 / 3）', 'いいえ 1件', '2026-09-30', '申請 太郎'])
        ->call('register')
        ->assertHasNoErrors();

    $check = SafetyCheck::query()->sole();
    expect($check->user_id)->toBe($this->employee->id)->and($check->items)->toHaveCount(3)->and($check->items[1]->checked)->toBeTrue()
        ->and($check->items[1]->notes)->toBe('手袋の替えが必要')->and($check->items->pluck('number')->map(fn (mixed $no): int => (int) $no)->all())->toBe([1, 2, 3]);

    Livewire::test(ViewSafetyCheck::class, ['record' => $check->getRouteKey()])
        ->assertSeeInOrder(['日付', '2026-09-30', '名前', '申請 太郎', '部署', '製造', '部署・機械', '製袋機1号', '※営業は1～6・14・15・19を記入してください',
            'チェックリスト', '整理整頓ができている', 'はい', '✓', '保護具を着用している', 'いいえ', '✓', '手袋の替えが必要']);

    Livewire::test(ListSafetyChecks::class)
        ->assertCanSeeTableRecords([$check])
        ->set('listView', hrView('safety-check', '一覧 （先月分）'))
        ->assertCanSeeTableRecords([$check]);

    // レコードを再利用する: this month's check starts from the same items, unchecked
    Livewire::test(QuickSafetyCheck::class, ['open' => true, 'duplicate' => (string) $check->id])
        ->assertSet('screen', 'review')
        ->assertSet('data.department', '製造')
        ->assertSet('data.checked_on', '2026-10-21')
        ->assertSet('data.items.1.item', '保護具を着用している')
        ->assertSet('data.items.1.answer', 'はい')
        ->assertSet('data.items.1.checked', false);
});

it('starts a checklist with the items of the department (BR-HR-05) and adds 20–25 for drivers', function () {
    $this->actingAs($this->employee);
    $numbers = fn ($entry): array => array_map(fn (array $row): int => (int) $row['number'], array_values($entry->get('data.items')));

    $entry = Livewire::test(QuickSafetyCheck::class, ['open' => true]);
    expect($numbers($entry))->toBe(range(1, 19))
        ->and($entry->get('data.items.0.item'))->toBe('整理・整頓・清掃ができている');

    $entry->call('choose', 'department', '営業');
    expect($numbers($entry))->toBe([1, 2, 3, 4, 5, 6, 14, 15, 19, 20, 21, 22, 23, 24, 25])->and($entry->get('data.driving'))->toBeTrue();

    $entry->call('back')->call('choose', 'department', '製造');
    expect($numbers($entry))->toBe(range(1, 19))->and($entry->get('data.driving'))->toBeFalse();

    // 「運転をする」: 20–25 in their place, and out again
    $entry->call('next')->set('data.items.0.checked', true)->call('setDriving', true);
    expect($numbers($entry))->toBe(range(1, 25))->and($entry->get('data.items.0.checked'))->toBeTrue();
    $entry->call('setDriving', false);
    expect($numbers($entry))->toBe(range(1, 19));

    // once a row is checked, another 部署 keeps the rows
    $entry->call('goTo', 'department')->call('choose', 'department', '営業');
    expect($numbers($entry))->toBe(range(1, 19));
});

it('records the alcohol check before and after driving', function () {
    $this->actingAs($this->employee);

    expect(AlcoholCheckResource::hasPage('create'))->toBeFalse();
    Livewire::test(QuickAlcoholCheck::class, ['open' => true])
        ->assertSet('data.before_instruction', '安全点検確認して、安全運転、交通ルール守ること')
        ->assertSet('data.after_instruction', '駐車場に停車、安全点検確認')
        ->assertSet('data.before_time', '10:00')
        ->assertSet('screen', 'before_vehicle')
        ->assertSee('運転前')
        ->call('choose', 'before_vehicle', 'な　2-10（トラック）')
        ->call('choose', 'before_method', '対面')
        ->call('choose', 'before_detected', '無')
        ->call('choose', 'before_checker_id', (string) $this->admin->id)
        ->assertSet('screen', 'before_more')
        ->set('data.before_time', '08:05')
        ->call('next')
        // 運転後 starts from 運転前
        ->assertSet('screen', 'after_vehicle')
        ->assertSee(['運転後', '運転後はあとで入力'])
        ->assertSet('data.after_vehicle', 'な　2-10（トラック）')
        ->call('next')
        ->assertSet('data.after_method', '対面')
        ->call('choose', 'after_method', '電話')
        ->call('choose', 'after_detected', '無')
        ->assertSet('data.after_checker_id', $this->admin->id)
        ->call('next')
        ->assertSet('screen', 'after_more')
        ->assertSet('data.after_time', '10:00')
        ->set('data.after_time', '17:20')
        ->call('next')
        ->assertSet('screen', 'review')
        ->assertSee(['車両番号(運転前)', '車両番号(運転後)', '検査日', '2026-10-21', '氏名', '申請 太郎'])
        ->call('register')
        ->assertHasNoErrors();

    $check = AlcoholCheck::query()->sole();
    expect($check->driver_id)->toBe($this->employee->id);

    Livewire::test(ViewAlcoholCheck::class, ['record' => $check->getRouteKey()])
        ->assertSeeInOrder(['検査日', '2026-10-21', '氏名', '申請 太郎', '運転前', '検査時刻', '08:05', '車両番号', 'な　2-10（トラック）', '確認方法', '対面',
            '酒気帯び運転の有無', '無', '確認者', '管理者', '運転後', '17:20', '電話']);

    Livewire::test(ListAlcoholChecks::class)
        ->assertSee(['検査時刻(運転後)', '確認方法(運転後)'])
        ->assertCanSeeTableRecords([$check])
        ->sortTable('before_checker_id')
        ->call('applyAppFilter', ['conditions' => [['field' => 'after_method', 'operator' => 'in', 'values' => ['対面']]]])
        ->assertCanNotSeeTableRecords([$check]);
});

it('notifies the administrators at once of 酒気帯び運転 有 (BR-HR-04)', function () {
    $manager = User::factory()->admin()->create(['name' => '安全運転管理者']);
    $this->actingAs($this->employee);

    // 「運転後はあとで入力」: 運転前 only, 運転後 empty until the record is edited
    Livewire::test(QuickAlcoholCheck::class, ['open' => true])
        ->call('choose', 'before_vehicle', 'な　2-10（トラック）')
        ->call('choose', 'before_method', '対面')
        ->call('choose', 'before_detected', '有')
        ->call('choose', 'before_checker_id', (string) $this->admin->id)
        ->set('data.before_time', '08:05')
        ->call('next')
        ->call('skip')
        ->assertSet('screen', 'review')
        ->assertSet('data.after_vehicle', null)
        ->call('register')
        ->assertHasNoErrors();

    $check = AlcoholCheck::query()->sole();
    expect($check->after_vehicle)->toBeNull()->and($check->after_time)->toBeNull()->and($check->after_checker_id)->toBeNull();
    $notice = $manager->notifications()->sole();
    expect($notice->data['title'])->toBe(AlcoholCheckResource::displayName().': 2026-10-21 申請 太郎')
        ->and($notice->data['body'])->toBe('酒気帯び運転の有無: 有（運転前）')
        ->and($this->admin->notifications()->count())->toBe(1)
        ->and($this->employee->notifications()->count())->toBe(0);

    // saved again: not again; 運転後 becomes 有: once more
    $check->update(['before_notes' => '再検査済']);
    expect($manager->notifications()->count())->toBe(1);
    $check->update(['after_detected' => '有']);
    expect($manager->notifications()->count())->toBe(2)
        ->and($manager->notifications()->get()->pluck('data.body')->all())->toContain('酒気帯び運転の有無: 有（運転後）');
});

it('asks only what the user may edit, and keeps no answer of a step that is no longer asked (user, 2026-11-09)', function () {
    // フィールドのアクセス権: 確認方法(運転前) only to read → that step is not asked and keeps its default
    BusinessApp::query()->where('code', 'alcohol-check')->sole()->fieldPermissions()->create([
        'field' => 'before_method', 'sort_order' => 1, 'subject_type' => AccessSubject::Everyone, 'access' => FieldAccessLevel::View,
    ]);
    $this->actingAs($this->employee);
    Livewire::test(QuickAlcoholCheck::class, ['open' => true])
        ->call('choose', 'before_vehicle', 'つ　7-17')
        ->assertSet('screen', 'before_detected')
        ->call('choose', 'before_method', '電話')
        ->assertSet('data.before_method', null);

    // the times of a 遅刻: asked for 遅刻申請 — gone with 休暇申請 / 有給休暇
    Livewire::test(QuickLeaveRequest::class, ['open' => true])
        ->call('choose', 'request_type', '遅刻申請')
        ->call('choose', 'category', '遅刻(時間欠勤)')
        ->call('useChip', 'target_on', '2026-10-22')
        ->assertSet('screen', 'times')
        ->set('data.total_time', '01:00')
        // an absence by the hour needs both times (LeaveRequestRules)
        ->call('next')
        ->assertHasErrors(['data.clock_time' => 'required'])
        ->set('data.clock_time', '10:00')
        ->call('next')
        ->set('data.reason', '私用')
        ->call('next')
        ->assertSet('screen', 'review')
        ->call('goTo', 'type')
        ->call('choose', 'request_type', '休暇申請')
        ->assertSet('screen', 'review')
        ->assertSee('遅刻・早退_合計時間')
        ->call('goTo', 'category')
        ->call('choose', 'category', '有給休暇')
        ->assertDontSee('遅刻・早退_合計時間')
        ->call('register')
        ->assertHasNoErrors();

    $request = LeaveRequest::query()->sole();
    expect($request->category)->toBe('有給休暇')->and($request->total_time)->toBeNull()->and($request->status)->toBe(LeaveRequestStatus::Draft);
});

it('lets administrators rename the app in アプリの設定, shown in the sidebar and on its screens (user, 2026-11-09)', function () {
    $this->actingAs($this->employee);
    $this->get(LeaveRequestResource::getUrl('settings'))->assertForbidden();

    $admin = grantDashboards(User::factory()->admin()->create(), 'erp');
    $this->actingAs($admin);
    Livewire::test(LeaveRequestSettings::class)
        ->assertSet('appTitle', '16. 申請管理')
        ->assertDontSee('元の名前に戻す')
        ->set('appTitle', '16. 休暇・勤怠の申請')
        ->call('saveName')
        ->assertHasNoErrors()
        ->assertRedirect(LeaveRequestResource::getUrl('settings'));

    expect(LeaveRequestResource::displayName())->toBe('16. 休暇・勤怠の申請')
        ->and(LeaveRequestResource::getNavigationLabel())->toBe('16. 休暇・勤怠の申請')
        ->and(LeaveRequestResource::appName())->toBe('16. 申請管理');
    $this->get(LeaveRequestResource::getUrl('index'))->assertOk()->assertSee('16. 休暇・勤怠の申請');

    Livewire::test(LeaveRequestSettings::class)
        ->assertSee('元の名前に戻す')
        ->call('resetName');
    expect(LeaveRequestResource::displayName())->toBe('16. 申請管理');
});

/** The errors of values against an app's RecordRules, by path. */
function ruleErrors(RecordRules $rules, array $data, ?Model $record = null): array
{
    return RecordValidator::errors($rules, $data, $record);
}

it('requires a request\'s fields, the times of an absence by the hour, and one request per person, date and 申請分類 (user, 2026-11-09)', function () {
    $rules = new LeaveRequestRules;
    expect(array_keys(ruleErrors($rules, ['request_type' => null, 'category' => null, 'target_on' => null, 'reason' => ' ', 'applicant_id' => null])))
        ->toEqualCanonicalizing(['request_type', 'category', 'target_on', 'reason', 'applicant_id']);

    $request = ['request_type' => '遅刻申請', 'category' => '遅刻(時間欠勤)', 'target_on' => '2026-10-22', 'reason' => '通院', 'applicant_id' => $this->employee->id];
    // 時間欠勤: both times, in 30-minute steps (BR-HR-02), more than 0
    expect(ruleErrors($rules, $request))->toHaveKeys(['total_time', 'clock_time'])
        ->and(ruleErrors($rules, [...$request, 'total_time' => '01:15', 'clock_time' => '10:00']))->toHaveKey('total_time')
        ->and(ruleErrors($rules, [...$request, 'total_time' => '00:00', 'clock_time' => '10:00'])['total_time'])->toBe(['遅刻・早退の合計時間は30分以上で入力してください。'])
        ->and(ruleErrors($rules, [...$request, 'total_time' => '01:00:00', 'clock_time' => '10:30:00']))->toBe([])
        // a half day off needs no times; 申請区分 and 申請分類 combine freely
        ->and(ruleErrors($rules, [...$request, 'request_type' => '早退申請', 'category' => '半休(午後欠勤)']))->toBe([]);

    // one request per person (作業員名, else 申請者), date and 申請分類
    $own = LeaveRequest::query()->create([...$request, 'category' => '有給休暇', 'request_type' => '休暇申請']);
    $forThem = [...$request, 'category' => '有給休暇', 'request_type' => '休暇申請', 'applicant_id' => $this->admin->id, 'worker_id' => $this->employee->id];
    expect(ruleErrors($rules, $forThem)['target_on'])->toBe(["同じ人の同じ日付・申請分類の申請が既にあります（申請管理ID {$own->id}）。"])
        ->and(ruleErrors($rules, [...$forThem, 'category' => '半有給(午前有給)']))->toBe([])
        ->and(ruleErrors($rules, [...$forThem, 'worker_id' => $this->colleague->id]))->toBe([])
        ->and(ruleErrors($rules, $rules->values($own), $own))->toBe([]);

    // the same rules on the edit page
    $other = LeaveRequest::query()->create([...$request, 'category' => '有給休暇', 'request_type' => '休暇申請', 'target_on' => '2026-10-23']);
    $this->actingAs($this->admin);
    Livewire::test(EditLeaveRequest::class, ['record' => $other->getRouteKey()])
        ->fillForm(['target_on' => '2026-10-22'])
        ->call('save')
        ->assertHasErrors(['data.target_on'])
        ->assertSee("申請管理ID {$own->id}");
    expect($other->fresh()?->target_on->toDateString())->toBe('2026-10-23');
    Livewire::test(EditLeaveRequest::class, ['record' => $other->getRouteKey()])
        ->fillForm(['category' => '遅刻(時間欠勤)', 'total_time' => null, 'clock_time' => null])
        ->call('save')
        ->assertHasErrors(['data.total_time', 'data.clock_time']);
});

it('keeps one checklist per person and month, every item numbered once and checked, and no future date (user, 2026-11-09)', function () {
    $rules = new SafetyCheckRules;
    $row = fn (int $no, array $more = []): array => ['number' => $no, 'item' => '項目'.$no, 'answer' => 'はい', 'checked' => true, 'notes' => '', ...$more];
    $check = ['checked_on' => '2026-10-21', 'user_id' => $this->employee->id, 'department' => '製造', 'items' => [$row(1), $row(2)]];

    expect(ruleErrors($rules, $check))->toBe([])
        ->and(array_keys(ruleErrors($rules, ['checked_on' => null, 'user_id' => null, 'department' => null, 'items' => []])))
        ->toEqualCanonicalizing(['checked_on', 'user_id', 'department', 'items'])
        ->and(ruleErrors($rules, [...$check, 'checked_on' => '2026-10-22']))->toHaveKey('checked_on')
        // an empty row is not saved and needs nothing; a list of empty rows is no checklist
        ->and(ruleErrors($rules, [...$check, 'items' => [$row(1), ['number' => '', 'item' => '', 'answer' => 'はい', 'checked' => [], 'notes' => '']]]))->toBe([])
        ->and(ruleErrors($rules, [...$check, 'items' => [['number' => null, 'item' => null, 'answer' => 'はい', 'checked' => false, 'notes' => null]]])['items'])
        ->toBe(['チェックリストに項目を1つ以上入力してください。']);

    // each item: a whole No once, its 項目, its チェック (the record form's ['✓'] counts; 「2.00」 is 2)
    $errors = ruleErrors($rules, [...$check, 'items' => ['a' => $row(1), 'b' => $row(1, ['checked' => ['✓']]), 'c' => $row(3, ['number' => '1.5', 'item' => '', 'checked' => false]),
        'd' => $row(4, ['number' => '4.00', 'checked' => ['✓']])]]);
    expect(array_keys($errors))->toEqualCanonicalizing(['items.a.number', 'items.b.number', 'items.c.number', 'items.c.item', 'items.c.checked'])
        ->and($errors['items.a.number'])->toBe(['同じNoの項目が2つあります。'])
        ->and($errors['items.c.number'])->toBe(['Noは整数で入力してください。'])
        ->and($errors['items.c.checked'])->toBe(['チェックされていない項目があります。']);

    // one checklist per person (従業員名, else 名前) and month
    $this->actingAs($this->employee);
    $saved = SafetyCheck::query()->create(['checked_on' => '2026-10-05', 'user_id' => $this->employee->id, 'department' => '製造']);
    $saved->items()->create(['sort' => 1, 'number' => 1, 'item' => '整理', 'answer' => 'はい', 'checked' => true]);
    expect(ruleErrors($rules, $check)['checked_on'])->toBe(["この人の2026年10月の安全衛生チェックは既にあります（レコード番号 {$saved->id}）。"])
        ->and(ruleErrors($rules, [...$check, 'checked_on' => '2026-09-30']))->toBe([])
        ->and(ruleErrors($rules, [...$check, 'user_id' => $this->admin->id, 'employee_id' => $this->colleague->id]))->toBe([])
        ->and(ruleErrors($rules, $rules->values($saved), $saved))->toBe([]);

    // the quick entry stops at 追加情報 with the date taken
    Livewire::test(QuickSafetyCheck::class, ['open' => true])
        ->call('choose', 'department', '製造')
        ->call('next')
        ->call('checkAll')
        ->call('next')
        ->call('register')
        ->assertHasErrors(['data.checked_on'])
        ->assertSet('screen', 'details');
    expect(SafetyCheck::query()->count())->toBe(1);

    // インライン編集 of 日付 into a month already checked
    $september = SafetyCheck::query()->create(['checked_on' => '2026-09-10', 'user_id' => $this->employee->id, 'department' => '製造']);
    $september->items()->create(['sort' => 1, 'number' => 1, 'item' => '整理', 'answer' => 'はい', 'checked' => true]);
    Livewire::test(ListSafetyChecks::class)
        ->call('startInlineEdit', (string) $september->getKey())
        ->set('inlineData.checked_on', '2026-10-20')
        ->callTableAction('inlineSave', $september)
        ->assertHasErrors(['inlineData.checked_on']);
    expect($september->fresh()?->checked_on->toDateString())->toBe('2026-09-10');
});

it('requires the whole check before driving, a complete one after it, another confirmer and one record per driver and day (BR-HR-04, user 2026-11-09)', function () {
    $rules = new AlcoholCheckRules;
    $before = ['checked_on' => '2026-10-21', 'driver_id' => $this->employee->id, 'before_time' => '08:00', 'before_vehicle' => 'つ　7-17', 'before_method' => '対面',
        'before_detected' => '無', 'before_checker_id' => $this->admin->id];

    expect(ruleErrors($rules, $before))->toBe([])
        ->and(array_keys(ruleErrors($rules, ['checked_on' => '2026-10-21', 'driver_id' => $this->employee->id])))
        ->toEqualCanonicalizing(['before_time', 'before_vehicle', 'before_method', 'before_detected', 'before_checker_id'])
        ->and(ruleErrors($rules, [...$before, 'before_checker_id' => (string) $this->employee->id])['before_checker_id'])->toBe(['確認者には氏名と別の人を選んでください。'])
        ->and(ruleErrors($rules, [...$before, 'checked_on' => '2026-10-22']))->toHaveKey('checked_on')
        // 運転後: a time alone (the legacy default) is none; one result makes it required as a whole, later than 運転前
        ->and(ruleErrors($rules, [...$before, 'after_time' => '08:00']))->toBe([])
        ->and(array_keys(ruleErrors($rules, [...$before, 'after_detected' => '無'])))->toEqualCanonicalizing(['after_time', 'after_vehicle', 'after_method', 'after_checker_id'])
        ->and(ruleErrors($rules, [...$before, 'after_time' => '07:30:00', 'after_vehicle' => 'つ　7-17', 'after_method' => '電話', 'after_detected' => '無',
            'after_checker_id' => $this->admin->id])['after_time'])->toBe(['検査時刻(運転後)は検査時刻(運転前)より後の時刻を入力してください。'])
        ->and(ruleErrors($rules, [...$before, 'after_time' => '17:30:00', 'after_vehicle' => 'つ　7-17', 'after_method' => '電話', 'after_detected' => '無',
            'after_checker_id' => $this->admin->id]))->toBe([]);

    // one record per driver and day
    $this->actingAs($this->employee);
    $saved = AlcoholCheck::query()->create($before);
    expect(ruleErrors($rules, $before)['checked_on'])->toBe(["この人のこの日のアルコールチェックは既にあります（レコード番号 {$saved->id}）。"])
        ->and(ruleErrors($rules, $rules->values($saved), $saved))->toBe([]);

    // the quick entry: the driver is not offered as 確認者; 運転前 is not skipped; the day is taken
    Livewire::test(QuickAlcoholCheck::class, ['open' => true])
        ->call('choose', 'before_vehicle', 'つ　7-17')
        ->call('next')
        ->assertHasErrors(['data.before_method' => 'required'])
        ->call('choose', 'before_method', '対面')
        ->call('choose', 'before_detected', '無')
        ->assertDontSee('申請 太郎</span>', false)
        ->call('choose', 'before_checker_id', (string) $this->admin->id)
        ->call('next')
        ->call('skip')
        ->call('register')
        ->assertHasErrors(['data.checked_on'])
        ->assertSet('screen', 'details');

    // the edit page: 運転後 begun has to be completed
    Livewire::test(EditAlcoholCheck::class, ['record' => $saved->getRouteKey()])
        ->fillForm(['after_detected' => '無'])
        ->call('save')
        ->assertHasErrors(['data.after_vehicle', 'data.after_method', 'data.after_checker_id']);
    expect($saved->fresh()?->after_detected)->toBeNull();
});

it('ends each list row with one light 「…」 whose menu still runs Filament\'s 編集する / 削除する (speed, 2026-11-09)', function () {
    $this->actingAs($this->admin);
    $request = LeaveRequest::query()->create(['request_type' => '休暇申請', 'applicant_id' => $this->employee->id, 'category' => '有給休暇', 'target_on' => '2026-10-22',
        'reason' => '私用']);

    $list = Livewire::test(ListLeaveRequests::class)
        ->assertSee('data-sfk-row-menu="'.e(json_encode(['k' => (string) $request->id, 'items' => [
            ['l' => '編集する', 'u' => LeaveRequestResource::getUrl('edit', ['record' => $request]), 'i' => 'edit'],
            ['l' => '削除する', 'a' => 'delete', 'i' => 'delete', 'd' => true],
        ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'"', false)
        // no dropdown component and no page-wide ⌘/Ctrl+D per row
        ->assertDontSee('x-mousetrap.global.mod-d', false);
    expect(substr_count($list->html(), 'class="fi-dropdown"'))->toBeLessThan(5);

    $list->callTableAction('delete', $request)->callMountedAction();
    expect(LeaveRequest::query()->whereKey($request->id)->exists())->toBeFalse();
});
