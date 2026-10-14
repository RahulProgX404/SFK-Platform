<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users\Pages;

use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Users\UserAccountService;
use Modules\Settings\Filament\Resources\Users\Actions\ResetPasswordAction;
use Modules\Settings\Filament\Resources\Users\UserResource;

/** @property User $record */
final class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected bool $passwordChanged = false;

    public function getTitle(): string
    {
        return "{$this->record->name} さんの編集";
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            ActionGroup::make([
                ResetPasswordAction::make(),
                DeleteAction::make()
                    ->visible(fn (User $record): bool => UserResource::canDelete($record))
                    ->using(function (User $record): bool {
                        $actor = auth()->user();
                        app(UserAccountService::class)->delete($record, $actor instanceof User ? $actor : null);

                        return true;
                    }),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->record->roles->pluck('name')->all();
        $data['panels'] = app(UserAccountService::class)->panelAccess($this->record);

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $roles = (array) ($data['roles'] ?? []);
        $panels = (array) ($data['panels'] ?? []);
        $password = $data['password'] ?? null;
        unset($data['roles'], $data['panels'], $data['password']);

        $this->passwordChanged = filled($password);

        return app(UserAccountService::class)->update(
            $record,
            $data,
            UserAccountService::normalizeRoles($roles),
            array_values(array_map('strval', $panels)),
            $password,
        )->user;
    }

    protected function getSavedNotification(): Notification
    {
        $notification = Notification::make()->success()->title('ユーザー情報を保存しました');

        return $this->passwordChanged
            ? $notification->body('パスワードを変更しました。ユーザーは次回ログイン時にパスワードの変更が必要です。')
            : $notification;
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
