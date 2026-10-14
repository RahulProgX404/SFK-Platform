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

    public function getSubheading(): ?string
    {
        return $this->record->managed_by_env
            ? 'このアカウントは環境変数で管理されるスーパー管理者のため、編集・削除できません。'
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            ResetPasswordAction::make(),
            EditAction::make(),
        ];
    }
}
