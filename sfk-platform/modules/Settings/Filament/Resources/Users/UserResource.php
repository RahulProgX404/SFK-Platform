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
use Modules\Core\Access\UserType;
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
 * ユーザー管理. Who may create/edit/delete whom follows the ユーザー種別 hierarchy (super admin → admins and
 * employees, admin → employees; employees only view). The env-managed super admin is read-only here,
 * and nobody can delete themselves. UserAccountService enforces the same rules.
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

    public static function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /** @return list<UserType> types the signed-in user may create or assign */
    public static function assignableTypes(): array
    {
        return self::actor()?->user_type->manageableTypes() ?? [];
    }

    public static function canCreate(): bool
    {
        return self::assignableTypes() !== [];
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof User && (self::actor()?->canManageUser($record) ?? false);
    }

    public static function canDelete(Model $record): bool
    {
        return self::canEdit($record) && ! $record->is(self::actor());
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'last_name', 'first_name', 'employee_code', 'sales_staff_code', 'worker_code'];
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
            return $notification;
        }

        return $notification
            ->persistent()
            ->body('仮パスワード：<strong>'.e($generatedPassword).'</strong>');
    }
}
