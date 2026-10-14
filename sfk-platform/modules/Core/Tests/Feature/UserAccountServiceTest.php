<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Access\PanelPermission;
use Modules\Core\Application\Users\ProtectedAccountException;
use Modules\Core\Application\Users\UserAccountService;
use Modules\Core\Domain\People\EmploymentStatus;

function accounts(): UserAccountService
{
    return app(UserAccountService::class);
}

it('creates a user with typed roles, panel access and a generated temporary password', function () {
    $issued = accounts()->create(
        ['name' => '山田 太郎', 'email' => 'yamada@example.test', 'employment_status' => 'active', 'last_name' => '山田'],
        ['営業', ' 経理管理者 ', '営業'],
        ['erp'],
    );

    $user = $issued->user->fresh();
    expect($issued->generatedPassword)->toBeString()->toHaveLength(12)
        ->and(Hash::check($issued->generatedPassword, $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->getRoleNames()->sort()->values()->all())->toBe(['営業', '経理管理者'])
        ->and($user->hasDirectPermission(PanelPermission::for('erp')))->toBeTrue()
        ->and($user->employment_status)->toBe(EmploymentStatus::Active);
});

it('uses the password typed by the administrator without revealing it again', function () {
    $issued = accounts()->create(['name' => 'A', 'email' => 'a@example.test'], ['営業'], [], 'Given-1234');

    expect($issued->generatedPassword)->toBeNull()
        ->and(Hash::check('Given-1234', $issued->user->password))->toBeTrue()
        ->and($issued->user->must_change_password)->toBeTrue();
});

it('requires a password change again after an administrator resets it', function () {
    $user = accounts()->create(['name' => 'A', 'email' => 'a@example.test'], ['営業'], [], 'Given-1234')->user;
    accounts()->changeOwnPassword($user, 'Mine-5678');
    expect($user->fresh()->must_change_password)->toBeFalse();

    accounts()->resetPassword($user);
    expect($user->fresh()->must_change_password)->toBeTrue();

    accounts()->changeOwnPassword($user, 'Mine-9999');
    accounts()->update($user, ['name' => 'B'], ['営業'], [], 'Admin-0000');
    expect($user->fresh()->must_change_password)->toBeTrue();
});

it('keeps the password flag when an administrator edits other fields', function () {
    $user = accounts()->create(['name' => 'A', 'email' => 'a@example.test'], ['営業'], [], 'Given-1234')->user;
    accounts()->changeOwnPassword($user, 'Mine-5678');

    accounts()->update($user, ['name' => 'B'], ['製造'], ['erp', 'settings']);

    $user = $user->fresh();
    expect($user->must_change_password)->toBeFalse()
        ->and($user->getRoleNames()->all())->toBe(['製造'])
        ->and($user->hasDirectPermission(PanelPermission::for('settings')))->toBeTrue();
});

it('refuses the reserved super-admin role', function () {
    accounts()->create(['name' => 'A', 'email' => 'a@example.test'], ['Super-Admin']);
})->throws(InvalidArgumentException::class);

it('protects the env-managed super admin and the acting user', function () {
    $managed = User::factory()->create();
    $managed->forceFill(['managed_by_env' => true])->save();

    expect(fn () => accounts()->update($managed, ['name' => 'x'], ['営業']))->toThrow(ProtectedAccountException::class)
        ->and(fn () => accounts()->resetPassword($managed))->toThrow(ProtectedAccountException::class)
        ->and(fn () => accounts()->delete($managed))->toThrow(ProtectedAccountException::class);

    $me = User::factory()->create();
    expect(fn () => accounts()->delete($me, $me))->toThrow(ProtectedAccountException::class);
});
