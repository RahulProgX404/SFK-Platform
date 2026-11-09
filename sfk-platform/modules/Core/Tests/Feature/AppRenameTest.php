<?php

/*
 * アプリ名 (user, 2026-11-09): administrators rename an app in アプリの設定 → 設定 (AppLifecycle::rename()); the new name is
 * shown everywhere (AppResource::displayName(): sidebar, screens, notices) and kept when the registry follows the code,
 * until it is set back to the code's name.
 */

use App\Models\User;
use Modules\Core\Application\Apps\AppActionNotAllowed;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Models\BusinessApp;

it('renames an app for administrators, keeps the name through the sync and sets it back', function () {
    $lifecycle = app(AppLifecycle::class);
    $lifecycle->syncWithCode(['accident' => '14. 事故報告管理']);
    $app = BusinessApp::query()->where('code', 'accident')->sole();
    $admin = User::factory()->admin()->create();

    $lifecycle->rename($app, '  14.  事故報告  ', '14. 事故報告管理', $admin);
    expect($app->fresh()?->name)->toBe('14. 事故報告')->and($app->fresh()?->name_customized)->toBeTrue();

    // the code's name does not override it
    $lifecycle->syncWithCode(['accident' => '14. 事故報告管理（新）']);
    expect($app->fresh()?->name)->toBe('14. 事故報告');

    // blank — or the code's own name — sets it back; the registry follows the code again
    $lifecycle->rename($app->fresh(), '', '14. 事故報告管理（新）', $admin);
    expect($app->fresh()?->name)->toBe('14. 事故報告管理（新）')->and($app->fresh()?->name_customized)->toBeFalse();
    $lifecycle->syncWithCode(['accident' => '14. 事故報告管理']);
    expect($app->fresh()?->name)->toBe('14. 事故報告管理');
    $lifecycle->rename($app->fresh(), '14. 事故報告管理', '14. 事故報告管理', $admin);
    expect($app->fresh()?->name_customized)->toBeFalse();

    // employees may not
    expect(fn () => $lifecycle->rename($app->fresh(), '事故', '14. 事故報告管理', User::factory()->create()))->toThrow(AppActionNotAllowed::class);
    expect($app->fresh()?->name)->toBe('14. 事故報告管理');
});
