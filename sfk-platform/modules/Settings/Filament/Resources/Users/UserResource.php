<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users;

use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Settings\Filament\NavigationGroup;
use Modules\Settings\Filament\Resources\Users\Pages\CreateUser;
use Modules\Settings\Filament\Resources\Users\Pages\EditUser;
use Modules\Settings\Filament\Resources\Users\Pages\ListUsers;
use Modules\Settings\Filament\Resources\Users\Pages\ViewUser;
use Modules\Settings\Filament\Resources\Users\Schemas\UserForm;
use Modules\Settings\Filament\Resources\Users\Schemas\UserInfolist;
use Modules\Settings\Filament\Resources\Users\Tables\UsersTable;
use Modules\Settings\Filament\Resources\Users\Widgets\UserStatsOverview;
use UnitEnum;

/**
 * ユーザー管理. The env-managed super admin is read-only here, and nobody can delete themselves.
 */
final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Users->value;

    protected static ?string $navigationLabel = 'ユーザー';

    protected static ?string $modelLabel = 'ユーザー';

    protected static ?string $pluralModelLabel = 'ユーザー';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof User && ! $record->managed_by_env;
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof User
            && ! $record->managed_by_env
            && ! $record->is(auth()->user());
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'last_name', 'first_name', 'employee_code'];
    }

    public static function getWidgets(): array
    {
        return [
            UserStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * Success notification; when a password was generated it is shown once and stays until closed.
     */
    public static function credentialsNotification(string $title, ?string $generatedPassword): Notification
    {
        $notification = Notification::make()->success()->title($title);

        if ($generatedPassword === null) {
            return $notification->body('ユーザーは次回ログイン時にパスワードの変更が必要です。');
        }

        return $notification
            ->persistent()
            ->body(
                '仮パスワード：<strong>'.e($generatedPassword).'</strong><br>'
                .'この通知を閉じると再表示できません。安全な方法でユーザーへ伝えてください。'
                .'ユーザーは初回ログイン時にパスワードの変更が必要です。'
            );
    }
}
