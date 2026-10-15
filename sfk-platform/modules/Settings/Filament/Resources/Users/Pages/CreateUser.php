<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Users\UserAccountService;
use Modules\Settings\Filament\Resources\Users\UserResource;

final class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'ユーザーを登録';

    /** Shown once in the success notification, never persisted or sent back to the browser state. */
    protected ?string $generatedPassword = null;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        $password = $data['password'] ?? null;
        unset($data['password']);

        $issued = app(UserAccountService::class)->create($data, $password, UserResource::actor());
        $this->generatedPassword = $issued->generatedPassword;

        return $issued->user;
    }

    protected function getCreatedNotification(): Notification
    {
        return UserResource::credentialsNotification('ユーザーを登録しました', $this->generatedPassword);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
