<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users\Pages;

use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Core\Filament\Users\UserDetailWidgets;
use Modules\Settings\Filament\Resources\Users\Actions\ResetPasswordAction;
use Modules\Settings\Filament\Resources\Users\UserResource;

/** @property User $record */
final class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return "{$this->record->name} さん";
    }

    /** Cards of other modules about this person (Core UserDetailWidgets, e.g. 個人の概要), above the record. */
    protected function getHeaderWidgets(): array
    {
        return app(UserDetailWidgets::class)->for($this->record);
    }

    public function getHeaderWidgetsColumns(): int
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            ResetPasswordAction::make(),
            EditAction::make()
                ->visible(fn (User $record): bool => UserResource::canEdit($record)),
        ];
    }
}
