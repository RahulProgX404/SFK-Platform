<?php

/*
 * Screens ask for the registry row of their app many times per page (sidebar entries, list columns, field access):
 * BusinessApp::forCode() and Space::hasMember() read once per request, and saving drops what they remember.
 */

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Apps\SpaceService;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;

function registry_app(string $code): BusinessApp
{
    $app = new BusinessApp(['code' => $code, 'name' => $code]);
    $app->forceFill(['status' => AppStatus::Published])->save();

    return $app;
}

/** @return list<string> */
function queries_on(string $table, Closure $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    DB::disableQueryLog();

    return array_values(array_filter(
        array_column(DB::getQueryLog(), 'query'),
        fn (string $sql): bool => str_contains($sql, "from \"{$table}\""),
    ));
}

it('reads the whole registry once per request', function () {
    registry_app('orders');
    registry_app('invoices');

    $queries = queries_on('business_apps', function () {
        expect(BusinessApp::forCode('orders')?->code)->toBe('orders')
            ->and(BusinessApp::forCode('orders'))->toBe(BusinessApp::forCode('orders'))
            ->and(BusinessApp::forCode('invoices')?->code)->toBe('invoices')
            ->and(BusinessApp::forCode('not-registered'))->toBeNull();
    });

    expect($queries)->toHaveCount(1);
});

it('forgets the registry when an app or a space is saved', function () {
    registry_app('orders');
    expect(BusinessApp::forCode('orders')?->status)->toBe(AppStatus::Published);

    BusinessApp::query()->where('code', 'orders')->firstOrFail()->forceFill(['status' => AppStatus::Maintenance])->save();
    expect(BusinessApp::forCode('orders')?->status)->toBe(AppStatus::Maintenance);

    registry_app('invoices');
    expect(BusinessApp::forCode('invoices'))->not->toBeNull();

    Space::query()->create(['panel_id' => 'erp', 'name' => '営業スペース']);
    expect(BusinessApp::forCode('orders')?->spaces)->toBeEmpty();
});

it('reads the spaces of a user once per request and sees the changes made through the space settings', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create();
    $app = registry_app('orders');
    $sales = Space::query()->create(['panel_id' => 'erp', 'name' => '営業スペース']);
    $plant = Space::query()->create(['panel_id' => 'erp', 'name' => '工場スペース']);
    $sales->users()->attach($employee);

    $queries = queries_on('spaces', function () use ($sales, $plant, $employee) {
        expect($sales->hasMember($employee))->toBeTrue()
            ->and($plant->hasMember($employee))->toBeFalse();
    });
    expect($queries)->toHaveCount(1);

    app(SpaceService::class)->save($plant, 'erp', ['name' => '工場スペース', 'user_ids' => [$employee->id], 'app_ids' => [$app->id]], $admin);

    expect($plant->hasMember($employee))->toBeTrue()
        ->and(BusinessApp::forCode('orders')?->spaces->pluck('id')->all())->toBe([$plant->id]);
});
