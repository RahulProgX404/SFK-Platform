<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Access\UserType;
use Modules\Core\Application\Users\ProtectedAccountException;
use Modules\Core\Application\Users\UserAccountService;
use Modules\Core\Domain\People\EmploymentStatus;

function accounts(): UserAccountService
{
    return app(UserAccountService::class);
}

it('creates an employee with a generated temporary password', function () {
    $issued = accounts()->create(
        ['name' => '山田 太郎', 'email' => 'yamada@example.test', 'employment_status' => 'active', 'last_name' => '山田'],
    );

    $user = $issued->user->fresh();
    expect($issued->generatedPassword)->toBeString()->toHaveLength(12)
        ->and(Hash::check($issued->generatedPassword, $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->user_type)->toBe(UserType::Employee)
        ->and($user->employment_status)->toBe(EmploymentStatus::Active);
});

it('uses the password typed by the administrator without revealing it again', function () {
    $issued = accounts()->create(['name' => 'A', 'email' => 'a@example.test'], 'Given-1234');

    expect($issued->generatedPassword)->toBeNull()
        ->and(Hash::check('Given-1234', $issued->user->password))->toBeTrue()
        ->and($issued->user->must_change_password)->toBeTrue();
});

it('requires a password change again after an administrator resets it', function () {
    $user = accounts()->create(['name' => 'A', 'email' => 'a@example.test'], 'Given-1234')->user;
    accounts()->changeOwnPassword($user, 'Mine-5678');
    expect($user->fresh()->must_change_password)->toBeFalse();

    accounts()->resetPassword($user);
    expect($user->fresh()->must_change_password)->toBeTrue();

    accounts()->changeOwnPassword($user, 'Mine-9999');
    accounts()->update($user, ['name' => 'B'], 'Admin-0000');
    expect($user->fresh()->must_change_password)->toBeTrue();
});

it('keeps the password flag when an administrator edits other fields', function () {
    $user = accounts()->create(['name' => 'A', 'email' => 'a@example.test'], 'Given-1234')->user;
    accounts()->changeOwnPassword($user, 'Mine-5678');

    accounts()->update($user, ['name' => 'B']);

    $user = $user->fresh();
    expect($user->must_change_password)->toBeFalse()
        ->and($user->name)->toBe('B');
});

it('never assigns the super admin user type', function () {
    accounts()->create(['name' => 'A', 'email' => 'a@example.test', 'user_type' => 'super_admin']);
})->throws(InvalidArgumentException::class);

it('protects the env-managed super admin and the acting user', function () {
    $managed = User::factory()->create();
    $managed->forceFill(['managed_by_env' => true])->save();

    expect(fn () => accounts()->update($managed, ['name' => 'x']))->toThrow(ProtectedAccountException::class)
        ->and(fn () => accounts()->resetPassword($managed))->toThrow(ProtectedAccountException::class)
        ->and(fn () => accounts()->delete($managed))->toThrow(ProtectedAccountException::class);

    $me = User::factory()->create();
    expect(fn () => accounts()->delete($me, $me))->toThrow(ProtectedAccountException::class);
});
