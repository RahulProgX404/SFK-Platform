{{-- The スペース wizard under the list of ManageSpaces (opened by its event, or on `?space=<id>`). --}}
@livewire(\Modules\Core\Filament\DashboardSettings\Resources\Spaces\SpaceWizard::class, ['space' => $space], key('sfk-space-wizard'))
