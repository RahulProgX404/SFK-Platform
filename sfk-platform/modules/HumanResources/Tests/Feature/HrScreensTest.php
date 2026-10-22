<?php

/*
 * The screens of 16 申請管理, 17 安全衛生チェック and 18 アルコールチェック (SYSTEM_BRAIN APP_16 / APP_17 / APP_18): the apps
 * register with the switches of their legacy templates; a request goes 申請前 → 第一申請中 → 承認済 (or back with 差戻し)
 * and employees only see their own requests; a checklist keeps its rows and is reused for the next month; an alcohol check
 * records the checks before and after driving.
 */

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Models\AppListView;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;
use Modules\HumanResources\Application\LeaveRequests\LeaveRequestWorkflow;
use Modules\HumanResources\Domain\LeaveRequests\LeaveRequestStatus;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\AlcoholCheckResource;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages\CreateAlcoholCheck;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages\ListAlcoholChecks;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages\ViewAlcoholCheck;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestResource;
use Modules\HumanResources\Filament\Resources\LeaveRequests\Pages\CreateLeaveRequest;
use Modules\HumanResources\Filament\Resources\LeaveRequests\Pages\ListLeaveRequests;
use Modules\HumanResources\Filament\Resources\LeaveRequests\Pages\ViewLeaveRequest;
use Modules\HumanResources\Filament\Resources\SafetyChecks\Pages\CreateSafetyCheck;
use Modules\HumanResources\Filament\Resources\SafetyChecks\Pages\ListSafetyChecks;
use Modules\HumanResources\Filament\Resources\SafetyChecks\Pages\ViewSafetyCheck;
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

    Livewire::test(CreateLeaveRequest::class)
        ->assertSchemaStateSet(['applicant_id' => $this->employee->id])
        ->fillForm(['request_type' => '早退申請', 'category' => '早退(時間欠勤)', 'target_on' => '2026-10-23', 'total_time' => '01:15', 'reason' => '通院のため'])
        ->call('create')
        ->assertHasFormErrors(['total_time' => 'regex']) // 30 分単位
        ->fillForm(['total_time' => '01:30', 'clock_time' => '15:30'])
        ->call('create')
        ->assertHasNoFormErrors();

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
    $undoFake = Repeater::fake();

    Livewire::test(CreateSafetyCheck::class)
        ->fillForm(['department_machine' => '製袋機1号', 'department' => '製造', 'checked_on' => '2026-09-30', 'items' => [
            ['number' => '1', 'item' => '整理整頓ができている', 'answer' => 'はい', 'checked' => ['✓'], 'notes' => null],
            ['number' => '2', 'item' => '保護具を着用している', 'answer' => 'いいえ', 'checked' => ['✓'], 'notes' => '手袋の替えが必要'],
            ['number' => '3', 'item' => '通路が確保されている', 'answer' => 'はい', 'checked' => [], 'notes' => null],
        ]])
        ->call('create')
        ->assertHasFormErrors(['items.2.checked' => 'required']);

    Livewire::test(CreateSafetyCheck::class)
        ->fillForm(['department_machine' => '製袋機1号', 'department' => '製造', 'checked_on' => '2026-09-30', 'items' => [
            ['number' => '1', 'item' => '整理整頓ができている', 'answer' => 'はい', 'checked' => ['✓'], 'notes' => null],
            ['number' => '2', 'item' => '保護具を着用している', 'answer' => 'いいえ', 'checked' => ['✓'], 'notes' => '手袋の替えが必要'],
        ]])
        ->call('create')
        ->assertHasNoFormErrors();
    $undoFake();

    $check = SafetyCheck::query()->sole();
    expect($check->user_id)->toBe($this->employee->id)->and($check->items)->toHaveCount(2)->and($check->items[1]->checked)->toBeTrue();

    Livewire::test(ViewSafetyCheck::class, ['record' => $check->getRouteKey()])
        ->assertSeeInOrder(['日付', '2026-09-30', '名前', '申請 太郎', '部署', '製造', '部署・機械', '製袋機1号', '※営業は1～6・14・15・19を記入してください',
            'チェックリスト', '整理整頓ができている', 'はい', '✓', '保護具を着用している', 'いいえ', '✓', '手袋の替えが必要']);

    Livewire::test(ListSafetyChecks::class)
        ->assertCanSeeTableRecords([$check])
        ->set('listView', hrView('safety-check', '一覧 （先月分）'))
        ->assertCanSeeTableRecords([$check]);

    // レコードを再利用する: this month's check starts from the same items
    Livewire::withQueryParams(['duplicate' => $check->id])->test(CreateSafetyCheck::class)
        ->assertSchemaStateSet(['department' => '製造', 'checked_on' => '2026-10-21']);
});

it('starts a checklist with the items of the department (BR-HR-05)', function () {
    $this->actingAs($this->employee);
    $numbers = fn ($component): array => array_map(fn (array $row): int => (int) $row['number'], array_values($component->get('data.items')));

    $form = Livewire::test(CreateSafetyCheck::class);
    expect($numbers($form))->toBe(range(1, 19))
        ->and(array_values($form->get('data.items'))[0]['item'])->toBe('整理・整頓・清掃ができている');

    $form->fillForm(['department' => '営業']);
    expect($numbers($form))->toBe([1, 2, 3, 4, 5, 6, 14, 15, 19, 20, 21, 22, 23, 24, 25]);

    $form->fillForm(['department' => '製造']);
    expect($numbers($form))->toBe(range(1, 19));
});

it('records the alcohol check before and after driving', function () {
    $this->actingAs($this->employee);

    Livewire::test(CreateAlcoholCheck::class)
        ->assertSchemaStateSet(['before_instruction' => '安全点検確認して、安全運転、交通ルール守ること', 'after_instruction' => '駐車場に停車、安全点検確認', 'before_time' => '10:00'])
        ->fillForm(['checked_on' => '2026-10-21', 'before_time' => '08:05', 'before_vehicle' => 'な　2-10（トラック）', 'before_method' => '対面', 'before_detected' => '無',
            'before_checker_id' => $this->admin->id, 'after_time' => '17:20', 'after_vehicle' => 'な　2-10（トラック）', 'after_method' => '電話', 'after_detected' => '無',
            'after_checker_id' => $this->admin->id])
        ->call('create')
        ->assertHasNoFormErrors();

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
