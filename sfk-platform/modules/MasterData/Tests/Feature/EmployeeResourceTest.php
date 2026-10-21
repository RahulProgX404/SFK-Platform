<?php

/*
 * App 02. 従業員マスタ (SYSTEM_BRAIN APP_02): the employees are the platform users ("Employee ↔ system user link"),
 * shown in the legacy record / list (view 在籍中の従業員一覧, 作業単価 confidential — 11_PERMISSIONS §3). Only
 * administrators create or update them, in ユーザー管理: ＋ and the pencil redirect there.
 */

use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\RecordComment;
use Modules\Core\Models\Space;
use Modules\MasterData\Filament\Resources\Employees\EmployeeResource;
use Modules\MasterData\Filament\Resources\Employees\Pages\EmployeeSettings;
use Modules\MasterData\Filament\Resources\Employees\Pages\ListEmployees;
use Modules\MasterData\Filament\Resources\Employees\Pages\ViewEmployee;

/** Publishes 02. 従業員マスタ in a space of 白石封筒, as アプリ設定 would add it (registry sync). */
function publishedEmployeeApp(): BusinessApp
{
    app(AppLifecycle::class)->syncWithCode(['employee-master' => '02. 従業員マスタ'], ['employee-master' => EmployeeResource::appSettingDefaults()]);
    $space = Space::query()->firstOrCreate(['panel_id' => 'erp', 'name' => '総務スペース']);
    $app = BusinessApp::query()->where('code', 'employee-master')->sole();
    $app->forceFill(['status' => AppStatus::Published, 'space_id' => $space->id])->save();

    return $app;
}

/** An employee (ユーザー種別 従業員) with every app right, so only the administrator rule stops them. */
function employeeAppUser(array $attributes = []): User
{
    $app = publishedEmployeeApp();
    $columns = [];
    foreach (AppAbility::cases() as $ability) {
        $columns[$ability->column()] = true;
    }
    $app->permissions()->firstOrCreate(['subject_type' => PermissionSubject::Everyone], $columns);

    $employee = grantDashboards(User::factory()->create($attributes), 'erp');
    $app->space?->users()->attach($employee);

    return $employee;
}

function makeEmployee(array $attributes = []): User
{
    return User::factory()->create(['name' => '山田太郎', 'last_name_kana' => 'ヤマダ', 'first_name_kana' => 'タロウ', ...$attributes]);
}

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->superAdmin = User::factory()->superAdmin()->create(['name' => 'スーパー管理者']);
    $this->admin = grantDashboards(User::factory()->admin()->create(['name' => '管理者']), 'erp');
});

it('registers itself as an app from the code, with 一括削除, 再利用 and 変更履歴 off', function () {
    expect((new AppDiscovery([base_path('modules')]))->definitions())->toHaveKey('employee-master', '02. 従業員マスタ');

    $app = publishedEmployeeApp();
    expect($app->bulk_delete_enabled)->toBeFalse()
        ->and($app->record_duplicate_enabled)->toBeFalse()
        ->and($app->record_history_enabled)->toBeFalse()
        ->and($app->record_comments_enabled)->toBeTrue()
        ->and($app->inline_edit_enabled)->toBeTrue();

    $this->actingAs($this->admin);
    $this->get(EmployeeSettings::getUrl())->assertOk();
});

it('lists the users in the legacy view 在籍中の従業員一覧 (newest first) and （すべて）', function () {
    publishedEmployeeApp();
    $category = EmployeeCategory::query()->where('name', '製造')->sole();
    $old = makeEmployee(['sales_staff_code' => 'S01', 'worker_code' => 'W01', 'department_code' => 'D10', 'notes' => '夜勤']);
    $old->employeeCategories()->attach($category);
    $new = makeEmployee(['name' => '佐藤花子', 'last_name_kana' => 'サトウ', 'first_name_kana' => 'ハナコ', 'work_rate' => 1500]);
    $left = makeEmployee(['name' => '退職者', 'employment_status' => EmploymentStatus::Resigned]);
    $this->actingAs($this->admin);

    Livewire::test(ListEmployees::class)
        ->assertSee('在籍中の従業員一覧')
        ->assertTableColumnVisible('employee_categories')
        ->assertTableColumnVisible('name_kana')
        ->assertTableColumnDoesNotExist('id')
        ->assertTableColumnDoesNotExist('work_rate')
        ->assertCanSeeTableRecords([$new, $old], inOrder: true)
        ->assertCanNotSeeTableRecords([$left])
        ->assertSee(['ヤマダ タロウ', '製造', 'S01', '在籍中'])
        ->set('listView', 'all')
        ->assertTableColumnVisible('id')
        ->assertTableColumnVisible('notes')
        ->assertTableColumnVisible('work_rate')
        ->assertSee('¥1,500')
        ->assertCanSeeTableRecords([$left])
        ->call('applyAppFilter', ['conditions' => [['field' => 'employee_categories', 'operator' => 'in', 'values' => [(string) $category->id]]]])
        ->assertCanSeeTableRecords([$old])
        ->assertCanNotSeeTableRecords([$new, $left])
        ->call('applyAppFilter', ['conditions' => [['field' => 'name_kana', 'operator' => 'like', 'values' => ['サトウ ハ']]]])
        ->assertCanSeeTableRecords([$new])
        ->assertCanNotSeeTableRecords([$old])
        ->sortTable('employee_categories')
        ->sortTable('name_kana')
        ->assertHasNoErrors();
});

it('shows the legacy record with コメント, and 作業単価 only to administrators and the employee themselves', function () {
    publishedEmployeeApp();
    $employee = makeEmployee(['sales_staff_code' => 'S01', 'work_rate' => 1500, 'overtime_rate' => 1875]);
    $this->actingAs($this->admin);

    Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])
        ->assertSeeInOrder(['従業員名', '山田太郎', 'ふりがな', 'ヤマダ タロウ', '従業員カテゴリ', '担当者コード', 'S01', '作業員コード',
            '部課コード', '在籍状況', '在籍中', '備考', '従業員ID', '作成日時', '更新日時', '作業単価', '¥1,500', '残業単価', '¥1,875'])
        ->assertActionHidden('duplicate')
        ->assertActionHidden('delete')
        ->set('commentData.body', '住所が変わりました')
        ->call('postComment')
        ->assertHasNoErrors();
    expect(RecordComment::query()->sole()->record_type)->toBe($employee->getMorphClass());

    $colleague = employeeAppUser();
    $this->actingAs($colleague);
    Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])->assertSee('S01')->assertDontSee('作業単価')->assertDontSee('¥1,500');
    Livewire::test(ListEmployees::class)->set('listView', 'all')->assertTableColumnDoesNotExist('work_rate');

    $this->actingAs($employee);
    publishedEmployeeApp()->space?->users()->attach($employee);
    grantDashboards($employee, 'erp');
    Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])->assertSee(['作業単価', '¥1,500']);

    $employee->delete();
    expect(RecordComment::query()->count())->toBe(0);
});

it('sends administrators to ユーザー管理 to create or update an employee', function () {
    publishedEmployeeApp();
    $employee = makeEmployee();
    $otherAdmin = User::factory()->admin()->create();
    $this->actingAs($this->admin);

    $this->get(EmployeeResource::getUrl('create'))->assertRedirect(route('filament.settings.resources.users.create'));
    $this->get(EmployeeResource::getUrl('edit', ['record' => $employee]))->assertRedirect(route('filament.settings.resources.users.edit', ['record' => $employee]));
    $this->get(EmployeeResource::getUrl('index'))->assertOk()->assertSee(EmployeeResource::getUrl('create'), false);

    Livewire::test(ListEmployees::class)
        ->assertTableActionVisible('inlineEdit', $employee)
        ->assertTableActionHasUrl('inlineEdit', EmployeeResource::getUrl('edit', ['record' => $employee]), $employee)
        ->assertTableActionHidden('delete', $employee)
        ->assertActionHidden('bulkDelete');
    Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])
        ->assertActionVisible('edit')
        ->assertActionHasUrl('edit', EmployeeResource::getUrl('edit', ['record' => $employee]));

    // an admin manages employees only (ユーザー種別 hierarchy); a super admin also manages admins
    $this->get(EmployeeResource::getUrl('edit', ['record' => $otherAdmin]))->assertForbidden();
    Livewire::test(ListEmployees::class)->set('listView', 'all')->assertTableActionHidden('inlineEdit', $otherAdmin);

    $this->actingAs($this->superAdmin);
    $this->get(EmployeeResource::getUrl('edit', ['record' => $otherAdmin]))->assertRedirect(route('filament.settings.resources.users.edit', ['record' => $otherAdmin]));
    $this->get(EmployeeResource::getUrl('edit', ['record' => $this->superAdmin]))->assertForbidden();
});

it('lets employees view but never create or update, whatever the app permissions', function () {
    $employee = makeEmployee();
    $viewer = employeeAppUser();
    $this->actingAs($viewer);

    $this->get(EmployeeResource::getUrl('index'))->assertOk()->assertSee('山田太郎')->assertDontSee(EmployeeResource::getUrl('create'), false);
    $this->get(EmployeeResource::getUrl('view', ['record' => $employee]))->assertOk();
    $this->get(EmployeeResource::getUrl('create'))->assertForbidden();
    $this->get(EmployeeResource::getUrl('edit', ['record' => $employee]))->assertForbidden();
    Livewire::test(ListEmployees::class)->assertTableActionHidden('inlineEdit', $employee)->assertTableActionHidden('delete', $employee);
    Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])->assertActionHidden('edit');

    $this->actingAs(grantDashboards(User::factory()->create(), 'erp'));
    $this->get(EmployeeResource::getUrl('index'))->assertForbidden();
});
