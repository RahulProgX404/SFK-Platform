<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Filament\AppResource;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\Space;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case binding
|--------------------------------------------------------------------------
| Every test (application tests/ and module modules/<Name>/Tests/) boots the Laravel app.
| Feature tests also get a fresh database per test.
*/

pest()->extend(TestCase::class)->in('Unit', '../modules/*/Tests/Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../modules/*/Tests/Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/** Gives the user dashboards the way production does: through a 従業員カテゴリ that allows them. */
function grantDashboards(User $user, string ...$panelIds): User
{
    $category = EmployeeCategory::createAtEnd('テストカテゴリ '.Str::random(6));
    $category->update(['dashboards' => $panelIds]);
    $user->employeeCategories()->attach($category);

    return $user;
}

/**
 * Lets the users open an app — as 通知 recipients must (Notifier): registered and 公開中, in a space of the erp
 * dashboard they are members of, 閲覧 for 全員.
 */
function openAppTo(string $appCode, User ...$members): BusinessApp
{
    $resource = AppResource::forCode($appCode);
    $app = BusinessApp::query()->where('code', $appCode)->first()
        ?? new BusinessApp(['code' => $appCode, 'name' => $resource !== null ? $resource::appName() : $appCode]);
    $app->forceFill(['status' => AppStatus::Published])->save();

    $space = $app->spaces()->first() ?? Space::query()->create(['panel_id' => 'erp', 'name' => 'スペース '.Str::random(6)]);
    $app->spaces()->syncWithoutDetaching([$space->id]);
    $space->users()->syncWithoutDetaching(array_map(fn (User $member): int => $member->id, $members));
    if (! $app->permissions()->where('subject_type', PermissionSubject::Everyone)->exists()) {
        $app->permissions()->create(['subject_type' => PermissionSubject::Everyone, AppAbility::View->column() => true]);
    }
    foreach ($members as $member) {
        grantDashboards($member, 'erp');
    }

    BusinessApp::forgetCached();
    Space::forgetCached();

    return $app;
}

/**
 * The 従業員マスタ情報 fields that are required when creating a user, with unique codes.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function employeeMasterFields(array $overrides = []): array
{
    $suffix = Str::upper(Str::random(6));

    return [
        'sales_staff_code' => "S{$suffix}",
        'worker_code' => "W{$suffix}",
        'department_code' => 'D10',
        'employee_category_ids' => [EmployeeCategory::query()->where('is_active', true)->value('id')],
        'work_rate' => 1500,
        'overtime_rate' => 1875,
        ...$overrides,
    ];
}
