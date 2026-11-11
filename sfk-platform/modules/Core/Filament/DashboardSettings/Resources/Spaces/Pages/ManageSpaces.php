<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\Spaces\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Modules\Core\Filament\DashboardSettings\Concerns\HasSettingsChrome;
use Modules\Core\Filament\DashboardSettings\Resources\Spaces\SpaceResource;
use Modules\Core\Filament\DashboardSettings\Resources\Spaces\SpaceWizard;

/**
 * スペース of the dashboard, in the frame of the app screens. スペースを作成 and ✎ open SpaceWizard (drawn under the
 * list); `?space=<id>` opens it on that space (⚙ of a space in the dashboard's アプリ panel).
 */
final class ManageSpaces extends ManageRecords
{
    use HasSettingsChrome;

    protected static string $resource = SpaceResource::class;

    protected static ?string $title = 'スペース';

    public function settingsPageKind(): string
    {
        return 'list';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('スペースを作成')
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => SpaceResource::canCreate())
                ->action(fn () => $this->dispatch(SpaceWizard::OPEN_EVENT)),
        ];
    }

    public function getFooter(): View
    {
        return view('core::filament.space-wizard-host', ['space' => request()->integer('space') ?: null]);
    }

    /** A space saved in the wizard: the list shows it. */
    #[On(SpaceWizard::SAVED_EVENT)]
    public function refreshSpaces(): void
    {
        $this->resetTable();
    }
}
