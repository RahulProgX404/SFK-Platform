<?php

/*
 * ユーザー管理 → スペース (docs/permissions.md "Space members from ユーザー管理"): after 利用可能なダッシュボード the form
 * lists the spaces of the dashboards the employee will open; the chosen ones make them a member, whose space アクセス権
 * then applies in the space's apps (AppAccess).
 */

use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Access\AppAccess;
use Modules\Core\Access\UserType;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\Space;
use Modules\Settings\Filament\Resources\Users\Pages\CreateUser;
use Modules\Settings\Filament\Resources\Users\Pages\EditUser;
use Modules\Settings\Filament\Resources\Users\Pages\ViewUser;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create(['name' => '管理者']);
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('settings');

    $this->category = EmployeeCategory::createAtEnd('スペーステスト');
    $this->sales = Space::query()->create(['panel_id' => 'erp', 'name' => '営業']);
    $this->sales->permissions()->create(['subject_type' => 'everyone', 'can_view' => true, 'can_update' => true]);
    $this->factory = Space::query()->create(['panel_id' => 'erp', 'name' => '製造']);
    $this->sns = Space::query()->create(['panel_id' => 'sns', 'name' => 'SNS']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function new_employee_data(EmployeeCategory $category, array $overrides = []): array
{
    return [
        'name' => '鈴木 一郎',
        'email' => 'suzuki@example.test',
        'employment_status' => EmploymentStatus::Active->value,
        'user_type' => UserType::Employee->value,
        ...employeeMasterFields(['employee_category_ids' => [$category->id]]),
        ...$overrides,
    ];
}

it('shows the spaces of the ticked dashboards after 利用可能なダッシュボード and makes the employee a member', function () {
    $app = new BusinessApp(['code' => 'partner-master', 'name' => '01. 取引先マスタ']);
    $app->forceFill(['status' => AppStatus::Published])->save();
    $app->spaces()->attach($this->sales);

    Livewire::test(CreateUser::class)
        ->fillForm(new_employee_data($this->category))
        ->assertFormFieldHidden('space_ids')
        ->fillForm(['dashboards' => ['erp']])
        ->assertFormFieldVisible('space_ids')
        ->assertSee('営業')
        ->assertSee('製造')
        ->assertDontSee('SNS')
        ->fillForm(['space_ids' => [$this->sales->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'suzuki@example.test')->firstOrFail();
    expect($user->spaces()->pluck('spaces.id')->all())->toBe([$this->sales->id])
        ->and(AppAccess::abilitiesFor($user, $app->fresh()))->toBe([AppAbility::View, AppAbility::Update]);
});

it('offers the spaces of the dashboards of the chosen 従業員カテゴリ too', function () {
    $this->category->update(['dashboards' => ['erp']]);

    Livewire::test(CreateUser::class)
        ->fillForm(new_employee_data($this->category))
        ->assertFormFieldVisible('space_ids')
        ->fillForm(['space_ids' => [$this->factory->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'suzuki@example.test')->firstOrFail()->spaces()->pluck('spaces.id')->all())
        ->toBe([$this->factory->id]);
});

it('names the dashboard of each space when several dashboards are ticked', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(new_employee_data($this->category, ['dashboards' => ['erp', 'sns']]))
        ->assertFormFieldVisible('space_ids')
        ->assertSee('：営業')
        ->assertSee('：SNS');
});

it('unticks the spaces of a dashboard that is unticked', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(new_employee_data($this->category, ['dashboards' => ['erp']]))
        ->fillForm(['space_ids' => [$this->sales->id]])
        ->fillForm(['dashboards' => []])
        ->assertFormSet(['space_ids' => []])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'suzuki@example.test')->firstOrFail()->spaces()->count())->toBe(0);
});

it('offers no spaces to administrators', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(new_employee_data($this->category, ['dashboards' => ['erp']]))
        ->assertFormFieldVisible('space_ids')
        ->fillForm(['user_type' => UserType::Admin->value])
        ->assertFormFieldHidden('space_ids');
});

it('edits the spaces, keeps those of other dashboards and shows them on the user page', function () {
    $user = User::factory()->inCategories($this->category->id)->create(['dashboards' => ['erp']]);
    $user->spaces()->attach([$this->sales->id, $this->sns->id]); // SNS: a dashboard the user does not open

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertFormSet(['space_ids' => [$this->sales->id]])
        ->fillForm(['space_ids' => [$this->factory->id], ...employeeMasterFields(['employee_category_ids' => [$this->category->id]])])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->spaces()->orderBy('spaces.id')->pluck('spaces.id')->all())->toBe([$this->factory->id, $this->sns->id]);

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->assertSee('スペース')
        ->assertSee('製造');
});

it('takes a user out of every space when they stop being an employee', function () {
    $user = User::factory()->inCategories($this->category->id)->create(['dashboards' => ['erp']]);
    $user->spaces()->attach($this->sales);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['user_type' => UserType::Admin->value, ...employeeMasterFields(['employee_category_ids' => [$this->category->id]])])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->spaces()->count())->toBe(0);
});
