<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\DailyReports;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\Production\Filament\Resources\DailyReports\Pages\CreateDailyReport;
use Modules\Production\Filament\Resources\DailyReports\Pages\DailyReportListViewSettings;
use Modules\Production\Filament\Resources\DailyReports\Pages\DailyReportSettings;
use Modules\Production\Filament\Resources\DailyReports\Pages\EditDailyReport;
use Modules\Production\Filament\Resources\DailyReports\Pages\ListDailyReports;
use Modules\Production\Filament\Resources\DailyReports\Pages\ViewDailyReport;
use Modules\Production\Filament\Resources\DailyReports\Tables\DailyReportsTable;
use Modules\Production\Models\DailyReport;
use UnitEnum;

/**
 * App 08. 日報入力 (SYSTEM_BRAIN APP_08) in the 白石封筒 dashboard: one record per task (作業日, 作業者, 注番, 機械名, …);
 * every save and delete recalculates the jobs' 製造済数量 / 社内原価 / 製造売上 (and status, BR-PR-06) and the box stock
 * (BR-PR-07). Access follows the app's permissions and status (AppResource). Like the legacy app, 一括削除 starts
 * switched off.
 */
final class DailyReportResource extends AppResource
{
    protected static ?string $model = DailyReport::class;

    protected static ?string $slug = 'daily-reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Production->value;

    protected static ?string $navigationLabel = '日報入力';

    protected static ?string $modelLabel = '日報';

    protected static ?string $pluralModelLabel = '日報';

    protected static ?int $navigationSort = 8;

    public static function appCode(): string
    {
        return 'daily-report';
    }

    public static function appName(): string
    {
        return '08. 日報入力';
    }

    /** SYSTEM_BRAIN APP_08 README (app description in the legacy system). */
    public static function appDescription(): string
    {
        return "日報入力アプリ\n製造担当者が入力するアプリです。\n*ひとつの作業に対して、ひとつのレコード登録となります。";
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    public static function form(Schema $schema): Schema
    {
        return DailyReportLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DailyReportLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return DailyReportsTable::configure($table);
    }

    /** 「日報入力ID 12」 */
    public static function titleOf(DailyReport $report): string
    {
        return DailyReportLayout::label('id').' '.$report->id;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDailyReports::route('/'),
            'create' => CreateDailyReport::route('/create'),
            'views' => DailyReportListViewSettings::route('/views'),
            'settings' => DailyReportSettings::route('/settings'),
            'view' => ViewDailyReport::route('/{record}'),
            'edit' => EditDailyReport::route('/{record}/edit'),
        ];
    }
}
