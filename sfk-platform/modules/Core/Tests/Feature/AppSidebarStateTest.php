<?php

/*
 * The sidebar on the app screens (user, 2026-11-09): an app opens with the sidebar collapsed; the other screens get back
 * the state the user had before the apps (AppSidebarState, drawn at the start of the body of every panel page).
 */

use App\Models\User;
use Filament\Facades\Filament;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\AppPages\AppSidebarState;
use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Core\Filament\AppResource;
use Modules\Core\Filament\Portal\PortalPage;

it('collapses the sidebar on the app screens and gives the other screens back their state', function () {
    expect(AppSidebarState::isAppScreen([AppListRecords::class]))->toBeTrue()
        ->and(AppSidebarState::isAppScreen([AppViewRecord::class, AppResource::class]))->toBeTrue()
        ->and(AppSidebarState::isAppScreen([PortalPage::class]))->toBeFalse()
        ->and(AppSidebarState::isAppScreen(['no-such-class']))->toBeFalse();

    $app = AppSidebarState::script([AppListRecords::class]);
    expect($app)->toContain("s.setItem('isOpen','false')", "s.setItem('isOpenDesktop','false')", '"sfk-sidebar-outside-apps"')
        ->not->toContain('removeItem');
    $other = AppSidebarState::script([PortalPage::class]);
    expect($other)->toContain('removeItem("sfk-sidebar-outside-apps")')
        ->not->toContain("'isOpenDesktop','false'");

    // drawn before Filament's scripts start Alpine (which reads the stored state)
    $this->actingAs(User::factory()->superAdmin()->create());
    $resource = collect(Filament::getPanel('erp')->getResources())
        ->first(fn (string $resource): bool => is_subclass_of($resource, AppResource::class) && $resource::hasPage('index'));
    $page = $this->get($resource::getUrl('index', panel: 'erp'))->assertOk()->getContent();
    expect($page)->toContain($app)
        ->and(strpos($page, $app))->toBeLessThan(strpos($page, '/livewire'));
    $this->get('/erp')->assertOk()->assertSee($other, false)->assertDontSee($app, false);
});
