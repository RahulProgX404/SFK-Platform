<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Employees;

use App\Models\User;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Modules\Core\Access\EmployeeDataAccess;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\MasterData\Filament\Resources\Employees\Pages\CreateEmployee;
use Modules\MasterData\Filament\Resources\Employees\Pages\EditEmployee;
use Modules\MasterData\Filament\Resources\Employees\Pages\EmployeeListViewSettings;
use Modules\MasterData\Filament\Resources\Employees\Pages\EmployeeSettings;
use Modules\MasterData\Filament\Resources\Employees\Pages\ListEmployees;
use Modules\MasterData\Filament\Resources\Employees\Pages\ViewEmployee;
use Modules\MasterData\Filament\Resources\Employees\Schemas\EmployeeInfolist;
use Modules\MasterData\Filament\Resources\Employees\Tables\EmployeesTable;
use UnitEnum;

/**
 * App 02. 従業員マスタ (SYSTEM_BRAIN APP_02) in the 白石封筒 dashboard. The employees are the platform users (APP_02
 * "Employee ↔ system user link"), maintained in ユーザー管理 of 共通管理: only administrators create or update them, and
 * ＋ / the pencil open the user's screen there (the app's create / edit pages redirect). Deleting stays in ユーザー管理
 * (UserAccountService protects accounts), so this app deletes nothing. Viewing follows the app's permissions and
 * status (AppResource); 作業単価 / 残業単価 only for administrators and the employee themselves (EmployeeDataAccess).
 */
final class EmployeeResource extends AppResource
{
    /** ユーザー管理 (共通管理) screens the create / edit pages redirect to. */
    public const SETTINGS_CREATE_ROUTE = 'filament.settings.resources.users.create';

    public const SETTINGS_EDIT_ROUTE = 'filament.settings.resources.users.edit';

    protected static ?string $model = User::class;

    protected static ?string $slug = 'employees';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MasterData->value;

    protected static ?string $navigationLabel = '従業員マスタ';

    protected static ?string $modelLabel = '従業員';

    protected static ?string $pluralModelLabel = '従業員';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function appCode(): string
    {
        return 'employee-master';
    }

    public static function appName(): string
    {
        return '02. 従業員マスタ';
    }

    /** SYSTEM_BRAIN APP_02 README (app description in the legacy system). */
    public static function appDescription(): string
    {
        return "従業員マスタアプリ\n従業員の情報を登録・管理するアプリです。\n担当者マスタと作業者マスタを統一させています。";
    }

    /**
     * The legacy template has 一括削除 off. 再利用 and 変更履歴 are off too: records are created and changed in
     * ユーザー管理, which copies nothing and keeps no versions.
     */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false, 'record_duplicate_enabled' => false, 'record_history_enabled' => false];
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeesTable::configure($table);
    }

    /** @return Builder<User> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<User> $query */
        $query = parent::getEloquentQuery();

        return $query->with('employeeCategories');
    }

    /** Only administrators (ユーザー種別), and only while ユーザー管理 is there to open. */
    public static function canCreate(): bool
    {
        return EmployeeDataAccess::isAdmin(self::actor()) && Route::has(self::SETTINGS_CREATE_ROUTE) && parent::canCreate();
    }

    /** Only administrators, for the users they may manage (super admins: admins and employees; admins: employees). */
    public static function canEdit(Model $record): bool
    {
        $actor = self::actor();

        return $record instanceof User
            && EmployeeDataAccess::isAdmin($actor)
            && $actor?->canManageUser($record) === true
            && Route::has(self::SETTINGS_EDIT_ROUTE)
            && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function settingsCreateUrl(): string
    {
        return route(self::SETTINGS_CREATE_ROUTE);
    }

    public static function settingsEditUrl(User $user): string
    {
        return route(self::SETTINGS_EDIT_ROUTE, ['record' => $user]);
    }

    public static function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sales_staff_code', 'worker_code'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'views' => EmployeeListViewSettings::route('/views'),
            'settings' => EmployeeSettings::route('/settings'),
            'view' => ViewEmployee::route('/{record}'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
