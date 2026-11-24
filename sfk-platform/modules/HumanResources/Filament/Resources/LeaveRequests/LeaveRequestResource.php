<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\LeaveRequests;

use App\Models\User;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Access\AppAccess;
use Modules\Core\Application\Records\RecordRules;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\HumanResources\Application\LeaveRequests\LeaveRequestRules;
use Modules\HumanResources\Filament\Resources\LeaveRequests\Pages\EditLeaveRequest;
use Modules\HumanResources\Filament\Resources\LeaveRequests\Pages\LeaveRequestListViewSettings;
use Modules\HumanResources\Filament\Resources\LeaveRequests\Pages\LeaveRequestSettings;
use Modules\HumanResources\Filament\Resources\LeaveRequests\Pages\ListLeaveRequests;
use Modules\HumanResources\Filament\Resources\LeaveRequests\Pages\ViewLeaveRequest;
use Modules\HumanResources\Filament\Resources\LeaveRequests\QuickEntry\QuickLeaveRequest;
use Modules\HumanResources\Filament\Resources\LeaveRequests\Tables\LeaveRequestsTable;
use Modules\HumanResources\Models\LeaveRequest;
use UnitEnum;

/**
 * App 16. 申請管理 (SYSTEM_BRAIN APP_16) in the 白石封筒 dashboard: attendance requests with the approval 申請前 → 第一申請中 →
 * 承認済 (LeaveRequestWorkflow). Access follows the app's permissions and status (AppResource); like the legacy app
 * (「管理者以外は自身が申請したレコードのみ閲覧可能です。」), employees see only the requests they applied for or entered, and
 * those sent to them as 承認者 (user 2026-11-24) — which they may open and decide but not edit or delete.
 * 一括削除 starts switched off as in the legacy template.
 */
final class LeaveRequestResource extends AppResource
{
    protected static ?string $model = LeaveRequest::class;

    protected static ?string $slug = 'leave-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources->value;

    protected static ?string $navigationLabel = '申請管理';

    protected static ?string $modelLabel = '申請';

    protected static ?string $pluralModelLabel = '申請';

    protected static ?int $navigationSort = 16;

    public static function appCode(): string
    {
        return LeaveRequest::APP_CODE;
    }

    public static function appName(): string
    {
        return '16. 申請管理';
    }

    /** SYSTEM_BRAIN APP_16 README (app description in the legacy system). */
    public static function appDescription(): string
    {
        return '管理者以外は自身が申請したレコードのみ閲覧可能です。';
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    /** @return Builder<LeaveRequest> only the user's own requests for employees */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<LeaveRequest> $query */
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return $user instanceof User ? $query->visibleTo($user) : $query->whereRaw('1 = 0');
    }

    /** Changing a request stays with its 申請者 / 作成者 and the app's administrators — never its 承認者. */
    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && self::mayChange($record);
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && self::mayChange($record);
    }

    private static function mayChange(Model $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && $record instanceof LeaveRequest
            && (AppAccess::manages($user, LeaveRequest::APP_CODE) || $record->isOwnedBy($user));
    }

    /** What a record must be on every screen (user, 2026-11-09): LeaveRequestRules. */
    public static function recordRules(): RecordRules
    {
        return new LeaveRequestRules;
    }

    /** 新規 (list, 再利用): the quick entry over the list (user, 2026-11-09); there is no create page. */
    public static function quickCreateComponent(): string
    {
        return QuickLeaveRequest::class;
    }

    public static function form(Schema $schema): Schema
    {
        return LeaveRequestLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LeaveRequestLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return LeaveRequestsTable::configure($table);
    }

    /** 「2026-10-21 有給休暇」 */
    public static function titleOf(LeaveRequest $request): string
    {
        return $request->target_on->format('Y-m-d').' '.$request->category;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeaveRequests::route('/'),
            'views' => LeaveRequestListViewSettings::route('/views'),
            'settings' => LeaveRequestSettings::route('/settings'),
            'view' => ViewLeaveRequest::route('/{record}'),
            'edit' => EditLeaveRequest::route('/{record}/edit'),
        ];
    }
}
