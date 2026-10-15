<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Models\EmployeeCategory;
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
