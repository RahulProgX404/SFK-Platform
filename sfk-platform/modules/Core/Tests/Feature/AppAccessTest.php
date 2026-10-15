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
    $app = new BusinessApp(['code' => 'partner-master', 'name' => '01. 取引先マスタ', 'space_id' => $space?->id]);
    $app->forceFill(['status' => $status])->save();

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
        expect(abilities_of($superAdmin, app_with_status($status)))->toHaveCount(6);
        BusinessApp::query()->delete();
    }

    expect(AppAccess::can($superAdmin, 'not-registered', AppAbility::Delete))->toBeTrue()
        ->and(AppAccess::can($this->employee, 'not-registered', AppAbility::View))->toBeFalse();
});

it('gives admins everything once the app has left development', function () {
    $admin = User::factory()->admin()->create();

    foreach (AppStatus::cases() as $status) {
        $expected = $status->isSuperAdminOnly() ? 0 : 6;
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

    $app->update(['space_id' => null]);
    expect(abilities_of($this->employee, $app))->toBe([]);
});

it('opens only 公開中 and メンテナンス中 apps to employees, read-only during maintenance', function () {
    $app = app_with_status(AppStatus::Published, $this->space);
    permit($app, PermissionSubject::Everyone, AppAbility::cases());

    $expected = [
        'developing' => [],
        'testing' => [],
        'ready' => [],
        'published' => ['view', 'create', 'update', 'delete', 'import', 'export'],
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
