<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\BagSchedules;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\Production\Filament\Resources\BagSchedules\Pages\BagScheduleListViewSettings;
use Modules\Production\Filament\Resources\BagSchedules\Pages\BagScheduleSettings;
use Modules\Production\Filament\Resources\BagSchedules\Pages\EditBagSchedule;
use Modules\Production\Filament\Resources\BagSchedules\Pages\ListBagSchedules;
use Modules\Production\Filament\Resources\BagSchedules\Pages\ViewBagSchedule;
use Modules\Production\Filament\Resources\BagSchedules\Tables\BagSchedulesTable;
use Modules\Production\Models\BagSchedule;
use UnitEnum;

/**
 * App 12. 製袋予定管理 (SYSTEM_BRAIN APP_12) in the 白石封筒 dashboard: the 製袋予定表 (production-schedule.js, user
 * 2026-11-06) — the machine lines × days in 「1日」「7日」「30日」, schedules added, edited, moved and copied in place, 注番
 * filled from 06. 売上入力, holidays from the 会社カレンダー — and the list for the schedule print. Access follows the
 * app's permissions and status (AppResource). Like the legacy app, 一括削除 starts switched off.
 */
final class BagScheduleResource extends AppResource
{
    protected static ?string $model = BagSchedule::class;

    protected static ?string $slug = 'bag-schedules';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Production->value;

    protected static ?string $navigationLabel = '製袋予定管理';

    protected static ?string $modelLabel = '製袋予定';

    protected static ?string $pluralModelLabel = '製袋予定';

    protected static ?int $navigationSort = 12;

    public static function appCode(): string
    {
        return 'bag-schedule';
    }

    public static function appName(): string
    {
        return '12. 製袋予定管理';
    }

    /** The app's description (the legacy one, SYSTEM_BRAIN APP_12 README, spoke of the Gantt charts the 製袋予定表 replaced). */
    public static function appDescription(): string
    {
        return "製袋予定管理アプリ\n機械のラインごとに、日別の製袋予定を表示します。";
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    /** The 製袋予定表 is the app's one screen: schedules are added on it, there is no 新規 / create page (user 2026-11-06). */
    public static function isSingleScreen(): bool
    {
        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return BagScheduleLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BagScheduleLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return BagSchedulesTable::configure($table);
    }

    /** 「製袋予定ID 12」 */
    public static function titleOf(BagSchedule $schedule): string
    {
        return BagScheduleLayout::label('id').' '.$schedule->id;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBagSchedules::route('/'),
            'views' => BagScheduleListViewSettings::route('/views'),
            'settings' => BagScheduleSettings::route('/settings'),
            'view' => ViewBagSchedule::route('/{record}'),
            'edit' => EditBagSchedule::route('/{record}/edit'),
        ];
    }
}
