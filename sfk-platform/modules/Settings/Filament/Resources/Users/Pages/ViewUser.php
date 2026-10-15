<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users\Pages;

use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
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

    protected function getHeaderActions(): array
    {
        return [
            ResetPasswordAction::make(),
            EditAction::make()
                ->visible(fn (User $record): bool => UserResource::canEdit($record)),
        ];
    }
}
