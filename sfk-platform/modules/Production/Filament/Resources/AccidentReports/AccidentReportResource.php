<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\AccidentReports;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\Production\Filament\Resources\AccidentReports\Pages\AccidentReportListViewSettings;
use Modules\Production\Filament\Resources\AccidentReports\Pages\AccidentReportSettings;
use Modules\Production\Filament\Resources\AccidentReports\Pages\CreateAccidentReport;
use Modules\Production\Filament\Resources\AccidentReports\Pages\EditAccidentReport;
use Modules\Production\Filament\Resources\AccidentReports\Pages\ListAccidentReports;
use Modules\Production\Filament\Resources\AccidentReports\Pages\ViewAccidentReport;
use Modules\Production\Filament\Resources\AccidentReports\Tables\AccidentReportsTable;
use Modules\Production\Models\AccidentReport;
use UnitEnum;

/**
 * App 14. 事故報告管理 (SYSTEM_BRAIN APP_14) in the 白石封筒 dashboard: incidents with the job, the machine, what happened
 * and the cost of the damage. Access follows the app's permissions and status (AppResource). Like the legacy app,
 * 一括削除 starts switched off.
 */
final class AccidentReportResource extends AppResource
{
    protected static ?string $model = AccidentReport::class;

    protected static ?string $slug = 'accident-reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Production->value;

    protected static ?string $navigationLabel = '事故報告管理';

    protected static ?string $modelLabel = '事故報告';

    protected static ?string $pluralModelLabel = '事故報告';

    protected static ?int $navigationSort = 14;

    public static function appCode(): string
    {
        return 'accident-report';
    }

    public static function appName(): string
    {
        return '14. 事故報告管理';
    }

    /** SYSTEM_BRAIN APP_14 README (app description in the legacy system). */
    public static function appDescription(): string
    {
        return "事故報告管理アプリ\nなんらかの事故が発生した場合に使用するアプリです。";
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    public static function form(Schema $schema): Schema
    {
        return AccidentReportLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AccidentReportLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return AccidentReportsTable::configure($table);
    }

    /** 「レコード番号 12」 */
    public static function titleOf(AccidentReport $report): string
    {
        return AccidentReportLayout::label('id').' '.$report->id;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccidentReports::route('/'),
            'create' => CreateAccidentReport::route('/create'),
            'views' => AccidentReportListViewSettings::route('/views'),
            'settings' => AccidentReportSettings::route('/settings'),
            'view' => ViewAccidentReport::route('/{record}'),
            'edit' => EditAccidentReport::route('/{record}/edit'),
        ];
    }
}
