<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\AppSettingsResource;
use Modules\Core\Models\BusinessApp;

/** @property BusinessApp $record */
final class EditAppSettings extends EditRecord
{
    protected static string $resource = AppSettingsResource::class;

    public function getTitle(): string
    {
        return "{$this->record->name} の権限";
    }

    protected function getHeaderActions(): array
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
