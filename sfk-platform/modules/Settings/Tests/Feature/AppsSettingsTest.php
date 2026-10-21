<?php

/*
 * アプリ設定 board (permission.md + improvement): apps come from the code — added as 開発中 when their screens
 * are built, removed when deleted. Super admins move apps along the whole pipeline; admins only
 * 準備完了 → 公開中 and 公開中 ⇄ 停止中; employees cannot open it.
 */

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;
use Modules\Settings\Filament\Pages\AppsSettings;

beforeEach(function () {
    Filament::setCurrentPanel('settings');
    $this->superAdmin = User::factory()->superAdmin()->create();
    $this->admin = User::factory()->admin()->create();

    // The apps "built in code" for these tests.
    app()->instance(AppDiscovery::class, AppDiscovery::fixed([
        'partner-master' => '01. 取引先マスタ',
        'ready-app' => 'ready-app',
        'dev-app' => 'dev-app',
        'in-test' => 'in-test',
        'live-app' => 'live-app',
    ]));
});

function registered_app(AppStatus $status, string $code = 'partner-master'): BusinessApp
{
    $app = new BusinessApp(['code' => $code, 'name' => $code]);
    $app->forceFill(['status' => $status])->save();

    return $app;
}

it('adds apps built in code as 開発中 and removes apps whose code was deleted', function () {
    $space = Space::query()->create(['panel_id' => 'erp', 'name' => '営業']);
    $kept = registered_app(AppStatus::Published, 'live-app');
    $kept->update(['space_id' => $space->id]);
    $gone = registered_app(AppStatus::Published, 'deleted-from-code');
    $gone->permissions()->create(['subject_type' => 'everyone', 'can_view' => true]);
    $this->actingAs($this->superAdmin);

    Livewire::test(AppsSettings::class)->assertSee('01. 取引先マスタ');

    $added = BusinessApp::query()->where('code', 'partner-master')->firstOrFail();
    expect($added->status)->toBe(AppStatus::Developing)
        ->and($added->space_id)->toBeNull()
        ->and(BusinessApp::query()->whereKey($gone->id)->exists())->toBeFalse()
        ->and($kept->fresh()->status)->toBe(AppStatus::Published)
        ->and($kept->fresh()->space_id)->toBe($space->id)
        ->and($kept->fresh()->name)->toBe('live-app');
});

it('has no create or delete on the board', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(AppsSettings::class)
        ->assertActionDoesNotExist('createApp')
        ->assertActionDoesNotExist('deleteApp');
});

it('shows all six stages to admins', function () {
    $this->actingAs($this->admin);

    $page = Livewire::test(AppsSettings::class);
    foreach (AppStatus::cases() as $status) {
        $page->assertSee($status->label());
    }
    $page->assertSee('in-test');
});

it('moves apps along the pipeline as a super admin and rejects other moves', function () {
    $this->actingAs($this->superAdmin);
    $page = Livewire::test(AppsSettings::class);
    $app = BusinessApp::query()->where('code', 'partner-master')->firstOrFail();

    $page->call('moveApp', $app->id, 'published')->assertNotified('「開発中」から「公開中」へは移動できません。');
    expect($app->fresh()->status)->toBe(AppStatus::Developing);

    foreach (['testing', 'ready', 'published', 'maintenance', 'published', 'stopped', 'published'] as $to) {
        $page->call('moveApp', $app->id, $to);
        expect($app->fresh()->status->value)->toBe($to);
    }

    $page->call('moveApp', $app->id, 'developing');
    expect($app->fresh()->status)->toBe(AppStatus::Published);

    $app->forceFill(['status' => AppStatus::Maintenance])->save();
    $page->call('moveApp', $app->id, 'stopped');
    expect($app->fresh()->status)->toBe(AppStatus::Maintenance);
});

it('lets admins only publish ready apps and stop or restart published ones', function () {
    $ready = registered_app(AppStatus::Ready, 'ready-app');
    $developing = registered_app(AppStatus::Developing, 'dev-app');
    $this->actingAs($this->admin);
    $page = Livewire::test(AppsSettings::class);

    $page->call('moveApp', $ready->id, 'published');
    expect($ready->fresh()->status)->toBe(AppStatus::Published);

    $page->call('moveApp', $ready->id, 'maintenance');
    expect($ready->fresh()->status)->toBe(AppStatus::Published);

    $page->call('moveApp', $ready->id, 'stopped');
    $page->call('moveApp', $ready->id, 'published');
    expect($ready->fresh()->status)->toBe(AppStatus::Published);

    $page->call('moveApp', $developing->id, 'testing');
    expect($developing->fresh()->status)->toBe(AppStatus::Developing);
});

it('offers only the allowed moves on each card', function () {
    registered_app(AppStatus::Published, 'live-app');
    $this->actingAs($this->admin);

    Livewire::test(AppsSettings::class)
        ->assertSee('→ 停止中')
        ->assertDontSee('→ メンテナンス中');

    $this->actingAs($this->superAdmin);
    Livewire::test(AppsSettings::class)
        ->assertSee('→ 停止中')
        ->assertSee('→ メンテナンス中');
});

it('lets super admins set the icon text only', function () {
    $app = registered_app(AppStatus::Published, 'live-app');
    $this->actingAs($this->superAdmin);

    Livewire::test(AppsSettings::class)
        ->callAction(TestAction::make('editApp')->arguments(['app' => $app->id]), data: ['icon_text' => '受注'])
        ->assertHasNoActionErrors();

    expect($app->fresh()->icon_text)->toBe('受注');

    $this->actingAs($this->admin);
    Livewire::test(AppsSettings::class)
        ->assertActionHidden(TestAction::make('editApp')->arguments(['app' => $app->id]));
});

it('keeps employees out of the board', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/settings/apps')->assertForbidden();
});

it('discovers app screens in module folders, including disabled modules', function () {
    $root = sys_get_temp_dir().'/sfk-apps-'.Str::random(8);
    $namespace = 'AppDiscoveryFixture'.Str::random(8);
    mkdir("{$root}/Fake/Filament/Resources", recursive: true);
    $file = "{$root}/Fake/Filament/Resources/FakeOrderResource.php";
    file_put_contents($file, <<<PHP
        <?php

        namespace {$namespace};

        final class FakeOrderResource extends \\Modules\\Core\\Filament\\AppResource
        {
            public static function appCode(): string { return 'order-entry'; }

            public static function appName(): string { return '04. 受注入力'; }
        }
        PHP);
    require_once $file;

    expect((new AppDiscovery([$root]))->definitions())->toBe(['order-entry' => '04. 受注入力'])
        ->and((new AppDiscovery([base_path('modules')]))->definitions())->toMatchArray([
            'partner-master' => '01. 取引先マスタ（得意先・仕入先）',
            'employee-master' => '02. 従業員マスタ',
            'machine-master' => '03. 機械マスタ',
        ]);
});
