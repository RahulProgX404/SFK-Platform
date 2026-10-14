<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Application\Users\UserAccountService;
use Modules\Settings\Filament\Resources\Users\UserResource;

/**
 * Administrator sets a temporary password. The user must change it at the next sign-in.
 */
final class ResetPasswordAction
{
    public static function make(): Action
    {
        return Action::make('resetPassword')
            ->label('パスワード再設定')
            ->icon(Heroicon::OutlinedKey)
            ->color('warning')
            ->visible(fn (User $record): bool => UserResource::canEdit($record))
            ->modalHeading(fn (User $record): string => "{$record->name} さんのパスワード再設定")
            ->modalDescription('再設定後、ユーザーは次回ログイン時にパスワードの変更が必要になります。')
            ->modalSubmitActionLabel('再設定する')
            ->schema([
                TextInput::make('password')
                    ->label('新しいパスワード')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->rule(Password::min(8)->letters()->numbers())
                    ->helperText('空欄の場合は仮パスワードを自動発行します。'),
            ])
            ->action(function (User $record, array $data): void {
                $issued = app(UserAccountService::class)->resetPassword($record, $data['password'] ?? null);

                UserResource::credentialsNotification('パスワードを再設定しました', $issued->generatedPassword)->send();
            });
    }
}
