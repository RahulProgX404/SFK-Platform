<?php

/*
 * ダッシュボード設定 on business dashboards (permission.md "Dashboard" / "Space" / "App"): administrators
 * maintain spaces (members, apps) and app permissions; employees never see it; 共通管理 has none.
 */

use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Access\AppAccess;
use Modules\Core\Application\Apps\AppActionNotAllowed;
use Modules\Core\Application\Apps\SpaceService;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages\EditAppSettings;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages\ListAppSettings;
use Modules\Core\Filament\DashboardSettings\Resources\Spaces\Pages\ManageSpaces;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\Space;

function placed_app(string $code, AppStatus $status = AppStatus::Published, ?Space $space = null): BusinessApp
{
    $app = new BusinessApp(['code' => $code, 'name' => $code, 'space_id' => $space?->id]);
    $app->forceFill(['status' => $status])->save();

    return $app;
}

beforeEach(function () {
    $this->admin = grantDashboards(User::factory()->admin()->create(), 'erp');
    $this->employee = grantDashboards(User::factory()->create(['name' => '山田 花子']), 'erp');
    Filament::setCurrentPanel('erp');
});

it('shows ダッシュボード設定 to administrators only, and not on 共通管理', function () {
    $this->actingAs($this->admin)->get('/erp/dashboard-settings/spaces')->assertOk()->assertSee('スペース');
    $this->actingAs($this->admin)->get('/erp/dashboard-settings/apps')->assertOk();
    $this->actingAs($this->admin)->get('/erp')->assertSee('ダッシュボード設定');

    $this->actingAs($this->employee)->get('/erp/dashboard-settings/spaces')->assertForbidden();
    $this->actingAs($this->employee)->get('/erp/dashboard-settings/apps')->assertForbidden();

    $this->actingAs($this->admin)->get('/settings/dashboard-settings/spaces')->assertNotFound();
});

// Separate test: Filament builds the sidebar once per panel boot, i.e. once per request in production.
it('does not list ダッシュボード設定 in the sidebar of employees', function () {
    $this->actingAs($this->employee)->get('/erp')->assertOk()->assertDontSee('ダッシュボード設定');
});

it('creates a space with member employees and apps', function () {
    $this->actingAs($this->admin);
    $partners = placed_app('partner-master', AppStatus::Developing);
    $orders = placed_app('order-entry', AppStatus::Developing);

    Livewire::test(ManageSpaces::class)
        ->callAction('create', data: [
            'name' => '営業スペース',
            'user_ids' => [$this->employee->id],
            'app_ids' => [$partners->id, $orders->id],
        ])
        ->assertHasNoActionErrors();

    $space = Space::query()->where('name', '営業スペース')->firstOrFail();
    expect($space->panel_id)->toBe('erp')
        ->and($space->users()->pluck('users.id')->all())->toBe([$this->employee->id])
        ->and($space->apps()->pluck('code')->sort()->values()->all())->toBe(['order-entry', 'partner-master']);
});

it('keeps an app in one space only and frees it when its space is deleted', function () {
    $this->actingAs($this->admin);
    $sales = Space::query()->create(['panel_id' => 'erp', 'name' => '営業']);
    $app = placed_app('partner-master', space: $sales);
    $factory = Space::query()->create(['panel_id' => 'erp', 'name' => '製造']);

    // The dropdown does not even offer it…
    $component = Livewire::test(ManageSpaces::class)
        ->callTableAction('edit', $factory, data: ['name' => '製造', 'app_ids' => [$app->id]]);
    expect(collect($component->errors()->keys())->contains(fn (string $key): bool => str_contains($key, 'app_ids')))->toBeTrue()
        ->and($app->fresh()->space_id)->toBe($sales->id);

    // …and the service refuses it too.
    expect(fn () => app(SpaceService::class)->save($factory, 'erp', ['name' => '製造', 'app_ids' => [$app->id]], $this->admin))
        ->toThrow(AppActionNotAllowed::class, '他のスペースに配置済みのアプリは追加できません：partner-master');
    expect($app->fresh()->space_id)->toBe($sales->id);

    Livewire::test(ManageSpaces::class)->callTableAction('delete', $sales);
    expect($app->fresh()->space_id)->toBeNull();
});

it('accepts only employees as space members', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManageSpaces::class)
        ->callAction('create', data: ['name' => 'X', 'user_ids' => [$this->admin->id]])
        ->assertHasActionErrors(['user_ids']);
});

it('lists only the apps placed in this dashboard and edits their permissions', function () {
    $this->actingAs($this->admin);
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業']);
    $space->users()->attach($this->employee);
    $app = placed_app('partner-master', space: $space);
    $other = placed_app('elsewhere', space: Space::query()->create(['panel_id' => 'sns', 'name' => 'SNS']));
    $unplaced = placed_app('unplaced');
    $sales = EmployeeCategory::query()->where('name', '営業')->firstOrFail();

    Livewire::test(ListAppSettings::class)
        ->assertCanSeeTableRecords([$app])
        ->assertCanNotSeeTableRecords([$other, $unplaced]);

    Livewire::test(EditAppSettings::class, ['record' => $app->getRouteKey()])
        ->fillForm(['permissions' => [
            ['subject_type' => 'everyone', 'can_view' => true],
            ['subject_type' => 'category', 'employee_category_id' => $sales->id, 'can_view' => true, 'can_create' => true],
            ['subject_type' => 'user', 'user_id' => $this->employee->id, 'can_view' => false, 'can_delete' => true],
        ]])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('アクセス権を保存しました');

    $rows = $app->permissions()->get()->keyBy(fn ($row) => $row->subject_type->value);
    expect($rows)->toHaveCount(3)
        ->and($rows['category']->abilities())->toBe([AppAbility::View, AppAbility::Create])
        ->and($rows['user']->abilities())->toBe([]);

    // The employee's own (empty) row wins over the category and 全員 rows.
    expect(AppAccess::abilitiesFor($this->employee->fresh(), $app->fresh()))->toBe([]);
});

it('rejects a second 全員 row and duplicate subjects', function () {
    $this->actingAs($this->admin);
    $app = placed_app('partner-master', space: Space::query()->create(['panel_id' => 'erp', 'name' => '営業']));

    Livewire::test(EditAppSettings::class, ['record' => $app->getRouteKey()])
        ->fillForm(['permissions' => [
            ['subject_type' => 'everyone', 'can_view' => true],
            ['subject_type' => 'everyone', 'can_view' => true],
        ]])
        ->call('save')
        ->assertHasFormErrors(['permissions']);

    $component = Livewire::test(EditAppSettings::class, ['record' => $app->getRouteKey()])
        ->fillForm(['permissions' => [
            ['subject_type' => 'user', 'user_id' => $this->employee->id, 'can_view' => true],
            ['subject_type' => 'user', 'user_id' => $this->employee->id, 'can_view' => true],
        ]])
        ->call('save');

    expect(collect($component->errors()->keys())->contains(fn (string $key): bool => str_ends_with($key, 'user_id')))->toBeTrue()
        ->and($app->permissions()->count())->toBe(0);
});

it('labels permission subjects in Japanese', function () {
    expect(PermissionSubject::options())->toBe(['user' => '個人', 'category' => '従業員カテゴリ', 'everyone' => '全員']);
});
