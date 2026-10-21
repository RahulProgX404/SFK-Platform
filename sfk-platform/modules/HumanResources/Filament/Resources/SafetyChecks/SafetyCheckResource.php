<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\SafetyChecks;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\HumanResources\Filament\Resources\SafetyChecks\Pages\CreateSafetyCheck;
use Modules\HumanResources\Filament\Resources\SafetyChecks\Pages\EditSafetyCheck;
use Modules\HumanResources\Filament\Resources\SafetyChecks\Pages\ListSafetyChecks;
use Modules\HumanResources\Filament\Resources\SafetyChecks\Pages\SafetyCheckListViewSettings;
use Modules\HumanResources\Filament\Resources\SafetyChecks\Pages\SafetyCheckSettings;
use Modules\HumanResources\Filament\Resources\SafetyChecks\Pages\ViewSafetyCheck;
use Modules\HumanResources\Filament\Resources\SafetyChecks\Tables\SafetyChecksTable;
use Modules\HumanResources\Models\SafetyCheck;
use UnitEnum;

/**
 * App 17. 安全衛生チェック (SYSTEM_BRAIN APP_17) in the 白石封筒 dashboard: monthly safety and health checklists per person;
 * the checklist sheet is printed with 印刷する. Access follows the app's permissions and status (AppResource). Like the
 * legacy app, 一括削除 starts switched off.
 */
final class SafetyCheckResource extends AppResource
{
    protected static ?string $model = SafetyCheck::class;

    protected static ?string $slug = 'safety-checks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources->value;

    protected static ?string $navigationLabel = '安全衛生チェック';

    protected static ?string $modelLabel = '安全衛生チェック';

    protected static ?string $pluralModelLabel = '安全衛生チェック';

    protected static ?int $navigationSort = 17;

    public static function appCode(): string
    {
        return 'safety-check';
    }

    public static function appName(): string
    {
        return '17. 安全衛生チェック';
    }

    /** SYSTEM_BRAIN APP_17 README (app description in the legacy system). */
    public static function appDescription(): string
    {
        return '安全衛生チェックアプリ';
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    public static function form(Schema $schema): Schema
    {
        return SafetyCheckLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SafetyCheckLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return SafetyChecksTable::configure($table);
    }

    /** 「2026-10-21 名前」 */
    public static function titleOf(SafetyCheck $check): string
    {
        return $check->checked_on->format('Y-m-d').' '.$check->user->name;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSafetyChecks::route('/'),
            'create' => CreateSafetyCheck::route('/create'),
            'views' => SafetyCheckListViewSettings::route('/views'),
            'settings' => SafetyCheckSettings::route('/settings'),
            'view' => ViewSafetyCheck::route('/{record}'),
            'edit' => EditSafetyCheck::route('/{record}/edit'),
        ];
    }
}
