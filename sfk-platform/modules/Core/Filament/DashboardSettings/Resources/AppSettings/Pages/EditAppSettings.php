<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\DashboardSettings\Concerns\HasSettingsChrome;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\AppSettingsResource;
use Modules\Core\Models\BusinessApp;

/**
 * アクセス権 of one app, in the frame of the app screens: キャンセル / 保存 at the top, no buttons below the form.
 *
 * @property BusinessApp $record
 */
final class EditAppSettings extends EditRecord
{
    use HasSettingsChrome;

    protected static string $resource = AppSettingsResource::class;

    public function getTitle(): string
    {
        return "{$this->record->name} の権限";
    }

    public function settingsPageKind(): string
    {
        return 'edit';
    }

    protected function settingsRecordTitle(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()->label('キャンセル')->color('gray'),
            Action::make('saveSettings')->label('保存')->action(fn () => $this->save())->keyBindings(['mod+s']),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getSavedNotification(): Notification
    {
        return Notification::make()->success()->title('アクセス権を保存しました');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
