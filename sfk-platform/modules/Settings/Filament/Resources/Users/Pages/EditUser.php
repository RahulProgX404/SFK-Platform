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
use Modules\Core\Access\EmployeeDataAccess;
use Modules\Core\Application\Users\UserAccountService;
use Modules\Settings\Filament\Resources\Users\Actions\ResetPasswordAction;
use Modules\Settings\Filament\Resources\Users\UserResource;

/** @property User $record */
final class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

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
                        app(UserAccountService::class)->delete($record, UserResource::actor());

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
        $data['employee_category_ids'] = $this->record->employeeCategories()->pluck('employee_categories.id')->all();

        // The rates are hidden from serialization; only administrators reach this page.
        foreach (EmployeeDataAccess::RATE_ATTRIBUTES as $attribute) {
            $data[$attribute] = $this->record->getAttribute($attribute);
        }

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $password = $data['password'] ?? null;
        unset($data['password']);

        return app(UserAccountService::class)->update($record, $data, $password, UserResource::actor())->user;
    }

    protected function getSavedNotification(): Notification
    {
        $notification = Notification::make()->success()->title('ユーザー情報を保存しました');

        return $notification;
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
