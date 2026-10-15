<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Access\UserType;
use Modules\Core\Application\Users\SuperAdminProvisioner;

beforeEach(function () {
    config([
        'core.super_admin.name' => 'システム管理者',
        'core.super_admin.email' => 'root@example.test',
        'core.super_admin.password' => 'Secret-123',
    ]);
});

it('creates the super admin from the environment', function () {
    $user = app(SuperAdminProvisioner::class)->sync();

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('root@example.test')
        ->and($user->user_type)->toBe(UserType::SuperAdmin)
        ->and($user->managed_by_env)->toBeTrue()
        ->and($user->must_change_password)->toBeFalse()
        ->and(Hash::check('Secret-123', $user->password))->toBeTrue();
});

it('follows password and name changes in the environment and stays idempotent', function () {
    app(SuperAdminProvisioner::class)->sync();
    config(['core.super_admin.password' => 'Changed-456', 'core.super_admin.name' => '管理者']);

    $user = app(SuperAdminProvisioner::class)->sync();
    app(SuperAdminProvisioner::class)->sync();

    expect(User::query()->count())->toBe(1)
        ->and($user->name)->toBe('管理者')
        ->and(Hash::check('Changed-456', $user->fresh()->password))->toBeTrue();
});

it('revokes super admin from the previous account when the configured email changes', function () {
    $old = app(SuperAdminProvisioner::class)->sync();
    config(['core.super_admin.email' => 'new-root@example.test']);

    app(SuperAdminProvisioner::class)->sync();

    $old->refresh();
    expect($old->user_type)->toBe(UserType::Employee)
        ->and($old->managed_by_env)->toBeFalse();
});

it('does nothing when the environment is not configured', function () {
    config(['core.super_admin.email' => null]);

    expect(app(SuperAdminProvisioner::class)->sync())->toBeNull()
        ->and(User::query()->count())->toBe(0);

    $this->artisan('core:super-admin')->assertFailed();
});

it('syncs through the artisan command', function () {
    $this->artisan('core:super-admin')->assertSuccessful();

    expect(User::query()->where('email', 'root@example.test')->exists())->toBeTrue();
});
