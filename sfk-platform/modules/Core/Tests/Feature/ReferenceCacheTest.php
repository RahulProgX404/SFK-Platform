<?php

/*
 * ReferenceCache (speed, user 2026-11-11): the registry, access rights, 一覧 and 帳票 come from the cache between requests
 * — and every change of them is seen on the next request (the version is bumped by model events and by the services
 * that write without them). Off in the other tests; switched on here, each "request" a new Request instance.
 */

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Access\AppAccess;
use Modules\Core\Access\DashboardAccess;
use Modules\Core\Access\FieldAccess;
use Modules\Core\Application\Apps\FieldPermissionService;
use Modules\Core\Application\Apps\SpaceService;
use Modules\Core\Application\Cache\DataCache;
use Modules\Core\Application\Cache\ReferenceCache;
use Modules\Core\Application\Users\UserAccountService;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\FieldAccessLevel;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\Space;

/** A new request: the per-request memos start empty, only the cache is left. */
function nextRequest(): void
{
    app()->instance('request', Request::create('/erp'));
}

beforeEach(function () {
    config(['core.reference_cache' => true]);
    $this->admin = User::factory()->admin()->create();
    $this->employee = grantDashboards(User::factory()->create(), 'erp');
    $this->space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業']);
    $this->space->users()->attach($this->employee);
    $this->partners = new BusinessApp(['code' => 'partner-master', 'name' => '01. 取引先マスタ']);
    $this->partners->forceFill(['status' => AppStatus::Published])->save();
    $this->partners->spaces()->attach($this->space);
    Space::forgetCached();
    nextRequest();
});

it('serves the access from the cache and sees every change on the next request', function () {
    $abilities = fn (): array => AppAccess::abilities($this->employee, 'partner-master');
    expect($abilities())->toBe([]);

    // a row of the app's アクセス権 (model event)
    $row = $this->partners->permissions()->create(['subject_type' => PermissionSubject::Everyone, 'can_view' => true]);
    nextRequest();
    expect($abilities())->toBe([AppAbility::View]);

    // the cache answers without the database: a change behind its back is not seen …
    DB::table('app_permissions')->where('id', $row->id)->update(['can_update' => true]);
    nextRequest();
    expect($abilities())->toBe([AppAbility::View]);
    // … until something bumps the version
    ReferenceCache::bump();
    nextRequest();
    expect($abilities())->toBe([AppAbility::View, AppAbility::Update]);

    // the space's アクセス権 through SpaceService (bulk delete + create)
    app(SpaceService::class)->savePermissions($this->space, [['subject_type' => 'everyone', 'can_view' => true, 'can_delete' => true]], $this->admin);
    nextRequest();
    expect($abilities())->toBe([AppAbility::View, AppAbility::Update, AppAbility::Delete]);

    // leaving the space (pivot, SpaceMembership)
    app(SpaceService::class)->save($this->space, 'erp', ['name' => '営業', 'user_ids' => [], 'app_ids' => [$this->partners->id]], $this->admin);
    nextRequest();
    expect($abilities())->toBe([]);
    app(SpaceService::class)->save($this->space, 'erp', ['name' => '営業', 'user_ids' => [$this->employee->id], 'app_ids' => [$this->partners->id]], $this->admin);
    nextRequest();
    expect($abilities())->not->toBe([]);

    // the app's status (model event)
    $this->partners->forceFill(['status' => AppStatus::Stopped])->save();
    nextRequest();
    expect($abilities())->toBe([]);
});

it('sees changes of 従業員カテゴリ, dashboards and フィールドのアクセス権 on the next request', function () {
    // dashboards follow the categories (UserAccountService)
    expect(DashboardAccess::allows($this->employee, 'erp'))->toBeTrue();
    app(UserAccountService::class)->update($this->employee, ['employee_category_ids' => []]);
    nextRequest();
    expect(DashboardAccess::allows($this->employee->fresh(), 'erp'))->toBeFalse();
    $sales = EmployeeCategory::query()->where('name', '営業')->firstOrFail();
    $sales->update(['dashboards' => ['erp']]);
    app(UserAccountService::class)->update($this->employee, ['employee_category_ids' => [$sales->id]]);
    nextRequest();
    expect(DashboardAccess::allows($this->employee->fresh(), 'erp'))->toBeTrue();

    // フィールドのアクセス権 (FieldPermissionService: bulk delete + create)
    $this->partners->permissions()->create(['subject_type' => PermissionSubject::Everyone, 'can_view' => true]);
    expect(FieldAccess::levels($this->employee, $this->partners))->toBe([]);
    app(FieldPermissionService::class)->save($this->partners, [['field' => 'name', 'entries' => [['subject_type' => 'everyone', 'access' => 'view']]]], ['name' => '取引先名'], $this->admin);
    nextRequest();
    expect(FieldAccess::levels($this->employee, $this->partners))->toBe(['name' => FieldAccessLevel::View]);
});

it('drops an app removed from the registry on the next request', function () {
    expect(BusinessApp::forCode('partner-master'))->not->toBeNull();
    $this->partners->delete();
    nextRequest();
    expect(BusinessApp::forCode('partner-master'))->toBeNull();
});

it('serves the dashboard figures from the cache until any record changes (DataCache)', function () {
    $reads = 0;
    $figure = function () use (&$reads): int {
        return DataCache::remember('test-figure', function () use (&$reads): int {
            $reads++;

            return BusinessApp::query()->count();
        });
    };

    expect($figure())->toBe(1)->and($figure())->toBe(1)->and($reads)->toBe(1);

    // a record saved anywhere: added up afresh (at once, or at the end of the request when bumped a moment ago)
    $other = new BusinessApp(['code' => 'order-entry', 'name' => '04. 受注入力']);
    $other->forceFill(['status' => AppStatus::Published])->save();
    DataCache::flush();
    nextRequest();
    expect($figure())->toBe(2)->and($reads)->toBe(2);

    // the versions live in the database (shared by local development and Render)
    expect(DB::table('cache_versions')->pluck('name')->sort()->values()->all())->toBe(['data', 'reference']);
});
