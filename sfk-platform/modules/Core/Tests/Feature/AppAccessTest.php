<?php

/*
 * App permissions (permission.md): ユーザー種別 first, then for employees the app's status, its space
 * (membership + dashboard) and the permission rows — 個人 > 従業員カテゴリ > 全員.
 */

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\Core\Access\AppAccess;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Filament\AppResource;
use Modules\Core\Models\AppPermission;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\Space;
use Modules\Core\Models\SpacePermission;

final class PartnerMasterTestResource extends AppResource
{
    protected static ?string $model = BusinessApp::class;

    public static function appCode(): string
    {
        return 'partner-master';
    }
}

function app_with_status(AppStatus $status, ?Space $space = null): BusinessApp
{
    $app = new BusinessApp(['code' => 'partner-master', 'name' => '01. 取引先マスタ']);
    $app->forceFill(['status' => $status])->save();
    if ($space !== null) {
        $app->spaces()->attach($space);
    }

    return $app;
}

/** @param  list<AppAbility>|list<string>  $abilities */
function permit(BusinessApp $app, PermissionSubject $subject, array $abilities, ?Model $target = null): AppPermission
{
    $columns = [];
    foreach (AppAbility::cases() as $ability) {
        $columns[$ability->column()] = in_array($ability, $abilities, true);
    }

    return $app->permissions()->create([
        'subject_type' => $subject,
        'employee_category_id' => $subject === PermissionSubject::Category ? $target?->getKey() : null,
        'user_id' => $subject === PermissionSubject::User ? $target?->getKey() : null,
        ...$columns,
    ]);
}

/** @return list<string> */
function abilities_of(User $user, BusinessApp $app): array
{
    return array_map(fn (AppAbility $ability): string => $ability->value, AppAccess::abilitiesFor($user, $app->fresh()));
}

beforeEach(function () {
    $this->space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業スペース']);
    $this->sales = EmployeeCategory::query()->where('name', '営業')->firstOrFail();

    $this->employee = User::factory()->inCategories($this->sales->id)->create(['dashboards' => ['erp']]);
    $this->space->users()->attach($this->employee);
});

it('gives super admins everything in every status, even before the app is registered', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    foreach (AppStatus::cases() as $status) {
        expect(abilities_of($superAdmin, app_with_status($status)))->toHaveCount(count(AppAbility::cases()));
        BusinessApp::query()->delete();
    }

    expect(AppAccess::can($superAdmin, 'not-registered', AppAbility::Delete))->toBeTrue()
        ->and(AppAccess::can($this->employee, 'not-registered', AppAbility::View))->toBeFalse();
});

it('gives admins everything once the app has left development', function () {
    $admin = User::factory()->admin()->create();

    foreach (AppStatus::cases() as $status) {
        $expected = $status->isSuperAdminOnly() ? 0 : count(AppAbility::cases());
        expect(abilities_of($admin, app_with_status($status)))->toHaveCount($expected);
        BusinessApp::query()->delete();
    }
});

it('lets the user row override the category row, which overrides 全員', function () {
    $app = app_with_status(AppStatus::Published, $this->space);
    permit($app, PermissionSubject::Everyone, [AppAbility::View, AppAbility::Create, AppAbility::Update, AppAbility::Delete]);

    expect(abilities_of($this->employee, $app))->toBe(['view', 'create', 'update', 'delete']);

    permit($app, PermissionSubject::Category, [AppAbility::View, AppAbility::Export], $this->sales);
    expect(abilities_of($this->employee, $app))->toBe(['view', 'export']);

    permit($app, PermissionSubject::User, [AppAbility::View, AppAbility::Import], $this->employee);
    expect(abilities_of($this->employee, $app))->toBe(['view', 'import']);

    // Someone else in the space still gets the 全員 row.
    $colleague = User::factory()->create(['dashboards' => ['erp']]);
    $this->space->users()->attach($colleague);
    expect(abilities_of($colleague, $app))->toBe(['view', 'create', 'update', 'delete']);
});

it('lets a row take rights away even when a lower row would give them', function () {
    $app = app_with_status(AppStatus::Published, $this->space);
    permit($app, PermissionSubject::Everyone, [AppAbility::View, AppAbility::Update]);
    permit($app, PermissionSubject::User, [], $this->employee);

    expect(abilities_of($this->employee, $app))->toBe([]);
});

it('requires space membership, dashboard access and a placed app for employees', function () {
    $app = app_with_status(AppStatus::Published, $this->space);
    permit($app, PermissionSubject::Everyone, [AppAbility::View]);

    $outsider = User::factory()->create(['dashboards' => ['erp']]);
    expect(abilities_of($outsider, $app))->toBe([]);

    $noDashboard = User::factory()->create();
    $this->space->users()->attach($noDashboard);
    expect(abilities_of($noDashboard, $app))->toBe([]);

    $app->spaces()->detach();
    $app->unsetRelation('spaces');
    expect(abilities_of($this->employee, $app))->toBe([]);
});

it('opens only 公開中 and メンテナンス中 apps to employees, read-only during maintenance', function () {
    $app = app_with_status(AppStatus::Published, $this->space);
    permit($app, PermissionSubject::Everyone, AppAbility::cases());

    $expected = [
        'developing' => [],
        'testing' => [],
        'ready' => [],
        'published' => ['view', 'create', 'update', 'delete', 'import', 'export', 'manage'],
        'maintenance' => ['view', 'export'],
        'stopped' => [],
    ];

    foreach (AppStatus::cases() as $status) {
        $app->forceFill(['status' => $status])->save();
        expect(abilities_of($this->employee, $app))->toBe($expected[$status->value]);
    }
});

it('drops every other right when 閲覧 is not granted, like the legacy system', function () {
    $app = app_with_status(AppStatus::Published, $this->space);
    $permission = permit($app, PermissionSubject::Everyone, [AppAbility::Create, AppAbility::Delete]);

    expect($permission->fresh()?->abilities())->toBe([]);
});

it('keeps a single 全員 row per app', function () {
    $app = app_with_status(AppStatus::Published, $this->space);
    permit($app, PermissionSubject::Everyone, [AppAbility::View]);

    permit($app, PermissionSubject::Everyone, [AppAbility::View]);
})->throws(ValidationException::class);

it('drives the CRUD and the sidebar of resources that implement the app', function () {
    $app = app_with_status(AppStatus::Published, $this->space);
    permit($app, PermissionSubject::Everyone, [AppAbility::View, AppAbility::Create]);
    $this->actingAs($this->employee);

    expect(PartnerMasterTestResource::canViewAny())->toBeTrue()
        ->and(PartnerMasterTestResource::canCreate())->toBeTrue()
        ->and(PartnerMasterTestResource::canEdit($app))->toBeFalse()
        ->and(PartnerMasterTestResource::canDelete($app))->toBeFalse()
        ->and(PartnerMasterTestResource::appCan(AppAbility::Export))->toBeFalse()
        ->and(PartnerMasterTestResource::shouldRegisterNavigation())->toBeTrue()
        ->and(PartnerMasterTestResource::getNavigationGroup())->toBe('営業スペース');

    $this->actingAs(User::factory()->create());
    expect(PartnerMasterTestResource::canViewAny())->toBeFalse()
        ->and(PartnerMasterTestResource::shouldRegisterNavigation())->toBeFalse();
});

/**
 * A row of a space's アクセス権 (スペースの権限).
 *
 * @param  list<AppAbility>  $abilities
 */
function space_permit(Space $space, PermissionSubject $subject, array $abilities, ?Model $target = null): SpacePermission
{
    $columns = [];
    foreach (AppAbility::cases() as $ability) {
        $columns[$ability->column()] = in_array($ability, $abilities, true);
    }

    return $space->permissions()->create([
        'subject_type' => $subject,
        'employee_category_id' => $subject === PermissionSubject::Category ? $target?->getKey() : null,
        'user_id' => $subject === PermissionSubject::User ? $target?->getKey() : null,
        ...$columns,
    ]);
}

it('gives a new space an empty permission setting, so placing an app grants nothing yet', function () {
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '新しいスペース']);
    $space->users()->attach($this->employee);
    $app = app_with_status(AppStatus::Published, $space);

    expect($space->permissions()->count())->toBe(0)
        ->and(abilities_of($this->employee, $app))->toBe([]);
});

it('gives the members of a space its 全員 rights in every app of the space', function () {
    space_permit($this->space, PermissionSubject::Everyone, [AppAbility::View, AppAbility::Update]);
    $partners = app_with_status(AppStatus::Published, $this->space);
    $orders = new BusinessApp(['code' => 'order-entry', 'name' => '04. 受注入力']);
    $orders->forceFill(['status' => AppStatus::Published])->save();
    $orders->spaces()->attach($this->space);

    expect(abilities_of($this->employee, $partners))->toBe(['view', 'update'])
        ->and(abilities_of($this->employee, $orders))->toBe(['view', 'update']);

    // not a member: nothing, whatever the rows say
    $outsider = User::factory()->create(['dashboards' => ['erp']]);
    space_permit($this->space, PermissionSubject::User, AppAbility::cases(), $outsider);
    expect(abilities_of($outsider, $partners))->toBe([]);
});

it('reads the rows of a space like the app rows: 個人 over 従業員カテゴリ over 全員', function () {
    $app = app_with_status(AppStatus::Published, $this->space);
    space_permit($this->space, PermissionSubject::Everyone, [AppAbility::View]);
    expect(abilities_of($this->employee, $app))->toBe(['view']);

    space_permit($this->space, PermissionSubject::Category, [AppAbility::View, AppAbility::Create], $this->sales);
    expect(abilities_of($this->employee, $app))->toBe(['view', 'create']);

    space_permit($this->space, PermissionSubject::User, [AppAbility::View, AppAbility::Delete], $this->employee);
    expect(abilities_of($this->employee, $app))->toBe(['view', 'delete']);

    // a member of another category gets the 全員 row; a member in two categories gets both category rows
    $factory = EmployeeCategory::createAtEnd('製造テスト');
    $worker = User::factory()->inCategories($factory->id)->create(['dashboards' => ['erp']]);
    $this->space->users()->attach($worker);
    expect(abilities_of($worker, $app))->toBe(['view']);

    space_permit($this->space, PermissionSubject::Category, [AppAbility::View, AppAbility::Export], $factory);
    $both = User::factory()->inCategories($this->sales->id, $factory->id)->create(['dashboards' => ['erp']]);
    $this->space->users()->attach($both);
    expect(abilities_of($both, $app))->toBe(['view', 'create', 'export']);
});

it('adds up the rights of every space of the user that holds the app (A 閲覧 + B 閲覧・編集 = 閲覧・編集)', function () {
    $a = $this->space;
    space_permit($a, PermissionSubject::Everyone, [AppAbility::View]);
    $b = Space::query()->create(['panel_id' => 'erp', 'name' => '製造スペース']);
    space_permit($b, PermissionSubject::Category, [AppAbility::View, AppAbility::Update], $this->sales);
    $app = app_with_status(AppStatus::Published, $a);
    $app->spaces()->attach($b);

    expect(abilities_of($this->employee, $app))->toBe(['view']);

    $b->users()->attach($this->employee);
    Space::forgetCached();
    expect(abilities_of($this->employee, $app))->toBe(['view', 'update']);

    // a 個人 row narrowing the user in A does not take away what B gives
    space_permit($a, PermissionSubject::User, [AppAbility::View], $this->employee);
    expect(abilities_of($this->employee, $app))->toBe(['view', 'update']);
});

it('adds the space rights to the app permission rows, which cannot take them away', function () {
    space_permit($this->space, PermissionSubject::Everyone, [AppAbility::View, AppAbility::Create]);
    $app = app_with_status(AppStatus::Published, $this->space);
    permit($app, PermissionSubject::Everyone, [AppAbility::View, AppAbility::Export]);

    expect(abilities_of($this->employee, $app))->toBe(['view', 'create', 'export']);

    permit($app, PermissionSubject::User, [], $this->employee);
    expect(abilities_of($this->employee, $app))->toBe(['view', 'create']);
});

it('counts only spaces of dashboards the user can open, and keeps the status rules for space rights', function () {
    $sns = Space::query()->create(['panel_id' => 'sns', 'name' => 'SNS']);
    space_permit($sns, PermissionSubject::Everyone, AppAbility::cases());
    $sns->users()->attach($this->employee);
    $app = app_with_status(AppStatus::Published, $sns);

    expect(abilities_of($this->employee, $app))->toBe([]);

    space_permit($this->space, PermissionSubject::Everyone, AppAbility::cases());
    $app->spaces()->attach($this->space);
    expect(abilities_of($this->employee, $app))->toHaveCount(count(AppAbility::cases()));

    $app->forceFill(['status' => AppStatus::Maintenance])->save();
    expect(abilities_of($this->employee, $app))->toBe(['view', 'export']);

    $app->forceFill(['status' => AppStatus::Ready])->save();
    expect(abilities_of($this->employee, $app))->toBe([]);
});

it('gives nothing from a space row without 閲覧, and keeps one 全員 row per space', function () {
    $row = space_permit($this->space, PermissionSubject::Everyone, [AppAbility::Create, AppAbility::Delete]);
    $app = app_with_status(AppStatus::Published, $this->space);

    expect($row->fresh()?->abilities())->toBe([])
        ->and(abilities_of($this->employee, $app))->toBe([]);

    space_permit($this->space, PermissionSubject::Everyone, [AppAbility::View]);
})->throws(ValidationException::class);
