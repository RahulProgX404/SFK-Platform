<?php

use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Access\Roles;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Position;
use Modules\Settings\Filament\Resources\Organizations\Pages\CreateOrganization;
use Modules\Settings\Filament\Resources\Organizations\Pages\EditOrganization;
use Modules\Settings\Filament\Resources\Organizations\RelationManagers\PositionsRelationManager;

beforeEach(function () {
    $this->seed(CoreDatabaseSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(Roles::SUPER_ADMIN);
    $this->actingAs($admin);
    Filament::setCurrentPanel('settings');
});

it('lists organizations', function () {
    Organization::query()->create(['name' => '営業部', 'code' => 'S01']);

    $this->get('/settings/organizations')->assertOk()->assertSee('組織・役職')->assertSee('営業部');
});

it('creates an organization under a parent', function () {
    $head = Organization::query()->create(['name' => '本社']);

    Livewire::test(CreateOrganization::class)
        ->fillForm(['name' => '営業部', 'code' => 'S01', 'parent_id' => $head->id])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Organization::query()->where('code', 'S01')->value('parent_id'))->toBe($head->id);
});

it('does not allow an organization below itself', function () {
    $head = Organization::query()->create(['name' => '本社']);
    $sales = Organization::query()->create(['name' => '営業部', 'parent_id' => $head->id]);

    Livewire::test(EditOrganization::class, ['record' => $head->getRouteKey()])
        ->fillForm(['parent_id' => $sales->id])
        ->call('save')
        ->assertHasFormErrors(['parent_id']);
});

it('adds positions to an organization, unique per organization', function () {
    $sales = Organization::query()->create(['name' => '営業部']);
    $factory = Organization::query()->create(['name' => '製造部']);
    Position::query()->create(['organization_id' => $factory->id, 'name' => '部長']);

    $manager = Livewire::test(PositionsRelationManager::class, ['ownerRecord' => $sales, 'pageClass' => EditOrganization::class]);

    $manager->callTableAction('create', data: ['name' => '部長', 'rank' => 50])->assertHasNoTableActionErrors();
    $manager->callTableAction('create', data: ['name' => '部長'])->assertHasTableActionErrors(['name' => 'unique']);

    expect($sales->positions()->pluck('name')->all())->toBe(['部長']);
});
