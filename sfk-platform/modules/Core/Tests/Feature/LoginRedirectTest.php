<?php

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Access\Roles;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;

it('opens the dashboard selection portal after signing in on a panel login page', function () {
    $this->seed(CoreDatabaseSeeder::class);
    $user = User::factory()->create(['password' => 'Login-1234']);
    $user->assignRole(Roles::SUPER_ADMIN);
    Filament::setCurrentPanel('erp');

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'Login-1234'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect('/portal');

    $this->assertAuthenticatedAs($user);
});
