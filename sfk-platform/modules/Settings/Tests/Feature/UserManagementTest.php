<?php

use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Access\PanelPermission;
use Modules\Core\Access\Roles;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Position;
use Modules\Settings\Filament\Resources\Users\Pages\CreateUser;
use Modules\Settings\Filament\Resources\Users\Pages\EditUser;
use Modules\Settings\Filament\Resources\Users\Pages\ListUsers;
use Modules\Settings\Filament\Resources\Users\Pages\ViewUser;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(CoreDatabaseSeeder::class);

    $this->admin = User::factory()->create(['name' => '管理者']);
    $this->admin->assignRole(Roles::SUPER_ADMIN);
    $this->admin->forceFill(['managed_by_env' => true])->save();

    $this->actingAs($this->admin);
    Filament::setCurrentPanel('settings');
});

it('shows the headline figures and the user list', function () {
    $member = User::factory()->create(['name' => '佐藤 花子']);

    $this->get('/settings/users')
        ->assertOk()
        ->assertSee('ユーザー管理')
        ->assertSee('登録ユーザー')
        ->assertDontSee('過去30日にログイン')
        ->assertDontSee('在籍状況別のユーザー数')
        ->assertDontSee('組織別の所属人数')
        ->assertDontSee('PW変更待ち')
        ->assertDontSee('ユーザーの状況を確認し');

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$member, $this->admin]);
});

it('creates a user with only the required fields and a generated password', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => '鈴木 一郎',
            'email' => 'suzuki@example.test',
            'roles' => ['営業'],
            'employment_status' => EmploymentStatus::Active->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('ユーザーを登録しました');

    $user = User::query()->where('email', 'suzuki@example.test')->firstOrFail();
    expect($user->must_change_password)->toBeTrue()
        ->and($user->hasRole('営業'))->toBeTrue();
});

it('requires name, email, roles and working status', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(['name' => '', 'email' => '', 'roles' => [], 'employment_status' => null])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'email' => 'required',
            'roles' => 'required',
            'employment_status' => 'required',
        ]);
});

it('rejects the reserved super-admin role typed in the form', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'X',
            'email' => 'x@example.test',
            'roles' => ['super-admin'],
            'employment_status' => 'active',
        ])
        ->call('create')
        ->assertHasFormErrors(['roles.0']);
});

it('creates a full profile with dashboards and several organizations', function () {
    $sales = Organization::query()->create(['name' => '営業部']);
    $manager = Position::query()->create(['organization_id' => $sales->id, 'name' => '部長', 'rank' => 50]);
    $factory = Organization::query()->create(['name' => '製造部']);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => '田中',
            'email' => 'tanaka@example.test',
            'password' => 'Initial-123',
            'roles' => ['営業', '承認者'],
            'panels' => ['erp'],
            'employment_status' => 'active',
            'last_name' => '田中',
            'first_name' => '次郎',
            'birthday' => '1990-04-01',
            'address' => '東京都千代田区1-1',
            'phone' => '03-1234-5678',
            'organizationMemberships' => [
                ['organization_id' => $sales->id, 'position_id' => $manager->id, 'is_primary' => true],
                ['organization_id' => $factory->id, 'position_id' => null, 'is_primary' => false],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'tanaka@example.test')->firstOrFail();
    expect($user->organizations()->pluck('name')->sort()->values()->all())->toBe(['営業部', '製造部'])
        ->and($user->organizationMemberships()->where('organization_id', $sales->id)->value('position_id'))->toBe($manager->id)
        ->and($user->hasDirectPermission(PanelPermission::for('erp')))->toBeTrue()
        ->and($user->birthday?->format('Y-m-d'))->toBe('1990-04-01');
});

it('rejects a position that belongs to another organization', function () {
    $sales = Organization::query()->create(['name' => '営業部']);
    $factory = Organization::query()->create(['name' => '製造部']);
    $foreman = Position::query()->create(['organization_id' => $factory->id, 'name' => '班長']);

    $component = Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'X',
            'email' => 'x@example.test',
            'roles' => ['営業'],
            'employment_status' => 'active',
            'organizationMemberships' => [
                ['organization_id' => $sales->id, 'position_id' => $foreman->id, 'is_primary' => true],
            ],
        ])
        ->call('create');

    expect(collect($component->errors()->keys())->contains(fn (string $key): bool => str_ends_with($key, 'position_id')))->toBeTrue()
        ->and(User::query()->where('email', 'x@example.test')->exists())->toBeFalse();
});

it('edits a user and forces a password change when the admin sets a new password', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('営業', 'web'));

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertSchemaStateSet(['roles' => ['営業']])
        ->fillForm([
            'roles' => ['製造'],
            'employment_status' => EmploymentStatus::Retired->value,
            'password' => 'Reset-9876',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    expect($user->employment_status)->toBe(EmploymentStatus::Retired)
        ->and($user->getRoleNames()->all())->toBe(['製造'])
        ->and($user->must_change_password)->toBeTrue();
});

it('resets a password from the user page', function () {
    $user = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->callAction('resetPassword', data: ['password' => null])
        ->assertNotified('パスワードを再設定しました');

    expect($user->fresh()->must_change_password)->toBeTrue();
});

it('does not allow editing or deleting the env-managed super admin', function () {
    $this->get("/settings/users/{$this->admin->id}/edit")->assertForbidden();

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('edit', $this->admin)
        ->assertTableActionHidden('delete', $this->admin);
});

it('deletes a user', function () {
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)->callTableAction('delete', $user);

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});
