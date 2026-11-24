<?php

/*
 * The sidebar on the app screens (user, 2026-11-09) and on the dashboard's home (user, 2026-11-11): they open with the
 * sidebar collapsed; the other screens get back the state the user had before them (AppSidebarState, drawn at the start of the body of every panel page).
 */

use App\Models\User;
use Filament\Facades\Filament;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\AppPages\AppSidebarState;
use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Core\Filament\AppResource;
use Modules\Core\Filament\Portal\PortalPage;
use Modules\Core\Filament\Profile\ProfilePage;
use Modules\Settings\Filament\Resources\Users\Pages\CreateUser;
use Modules\Settings\Filament\Resources\Users\Pages\EditUser;
use Modules\Settings\Filament\Resources\Users\Pages\ListUsers;
use Modules\Settings\Filament\Resources\Users\UserResource;

it('collapses the sidebar on the app screens and gives the other screens back their state', function () {
    expect(AppSidebarState::isAppScreen([AppListRecords::class]))->toBeTrue()
        ->and(AppSidebarState::isAppScreen([AppViewRecord::class, AppResource::class]))->toBeTrue()
        ->and(AppSidebarState::isAppScreen([PortalPage::class]))->toBeFalse()
        ->and(AppSidebarState::isAppScreen(['no-such-class']))->toBeFalse();

    $app = AppSidebarState::script([AppListRecords::class]);
    expect($app)->toContain("s.setItem('isOpen','false')", "s.setItem('isOpenDesktop','false')", '"sfk-sidebar-outside-apps"')
        ->not->toContain('removeItem');
    $other = AppSidebarState::script([ListUsers::class, UserResource::class]);
    expect($other)->toContain('removeItem("sfk-sidebar-outside-apps")')
        ->not->toContain("'isOpenDesktop','false'");

    // drawn before Filament's scripts start Alpine (which reads the stored state)
    $this->actingAs(User::factory()->superAdmin()->create());
    $resource = collect(Filament::getPanel('erp')->getResources())
        ->first(fn (string $resource): bool => is_subclass_of($resource, AppResource::class) && $resource::hasPage('index'));
    $page = $this->get($resource::getUrl('index', panel: 'erp'))->assertOk()->getContent();
    expect($page)->toContain($app)
        ->and(strpos($page, $app))->toBeLessThan(strpos($page, '/livewire'));
    $this->get('/settings/users')->assertOk()->assertSee($other, false)->assertDontSee($app, false);
});

it('opens the home of the dashboard (白石封筒) with the sidebar collapsed (user 2026-11-11)', function () {
    expect(AppSidebarState::opensCollapsed([PortalPage::class]))->toBeTrue()
        ->and(AppSidebarState::opensCollapsed([ProfilePage::class]))->toBeFalse()
        ->and(AppSidebarState::script([PortalPage::class]))->toBe(AppSidebarState::script([AppListRecords::class]));

    $this->actingAs(User::factory()->superAdmin()->create());
    $page = $this->get('/erp')->assertOk()->getContent();
    $collapse = AppSidebarState::script([PortalPage::class]);
    expect($page)->toContain($collapse)
        ->and(strpos($page, $collapse))->toBeLessThan(strpos($page, '/livewire'));
});

it('opens プロフィール and ユーザーの登録 / 編集 with the sidebar open on computers (user 2026-11-24)', function () {
    foreach ([[ProfilePage::class], [CreateUser::class, UserResource::class], [EditUser::class, UserResource::class]] as $scopes) {
        expect(AppSidebarState::opensExpanded($scopes))->toBeTrue()
            ->and(AppSidebarState::opensCollapsed($scopes))->toBeFalse();
    }
    expect(AppSidebarState::opensExpanded([ListUsers::class, UserResource::class]))->toBeFalse();

    // the user's own state is kept aside (and comes back on the next other screen); phones keep theirs
    $open = AppSidebarState::script([ProfilePage::class]);
    expect($open)->toContain('"sfk-sidebar-outside-apps"', "s.setItem('isOpenDesktop','true')")
        ->not->toContain("s.setItem('isOpen',", 'removeItem');

    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $user = User::factory()->create();
    foreach (['/erp/profile', '/settings/profile', '/settings/users/create', "/settings/users/{$user->id}/edit"] as $url) {
        $page = $this->get($url)->assertOk()->getContent();
        // before Filament's scripts (which start Alpine)
        expect($page)->toContain($open)
            ->and(strpos($page, $open))->toBeLessThan(strpos($page, '/js/filament/'));
    }
});
