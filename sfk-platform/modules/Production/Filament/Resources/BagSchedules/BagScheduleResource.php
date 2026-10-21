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
use Modules\Production\Filament\Resources\BagSchedules\Pages\CreateBagSchedule;
use Modules\Production\Filament\Resources\BagSchedules\Pages\EditBagSchedule;
use Modules\Production\Filament\Resources\BagSchedules\Pages\ListBagSchedules;
use Modules\Production\Filament\Resources\BagSchedules\Pages\ViewBagSchedule;
use Modules\Production\Filament\Resources\BagSchedules\Tables\BagSchedulesTable;
use Modules\Production\Models\BagSchedule;
use UnitEnum;

/**
 * App 12. 製袋予定管理 (SYSTEM_BRAIN APP_12) in the 白石封筒 dashboard: machine bookings shown as Gantt charts by machine
 * (日次 / 週次 / 月次 / 年次, coloured by 使用状況) and as the list for the schedule print. Access follows the app's
 * permissions and status (AppResource). Like the legacy app, 一括削除 starts switched off.
 */
final class BagScheduleResource extends AppResource
{
    protected static ?string $model = BagSchedule::class;

    protected static ?string $slug = 'bag-schedules';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

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

    /** SYSTEM_BRAIN APP_12 README (app description in the legacy system). */
    public static function appDescription(): string
    {
        return "製袋予定管理アプリ\n機械別の製袋予定をガントチャート形式で表示します。（色による使用ステータス判別が可能です）";
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
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
            'create' => CreateBagSchedule::route('/create'),
            'views' => BagScheduleListViewSettings::route('/views'),
            'settings' => BagScheduleSettings::route('/settings'),
            'view' => ViewBagSchedule::route('/{record}'),
            'edit' => EditBagSchedule::route('/{record}/edit'),
        ];
    }
}
