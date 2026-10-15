<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\EmployeeCategories;

use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Modules\Core\Access\DashboardAccess;
use Modules\Core\Models\EmployeeCategory;
use Modules\Settings\Filament\NavigationGroup;
use Modules\Settings\Filament\Resources\EmployeeCategories\Pages\ManageEmployeeCategories;
use Modules\Settings\Filament\Support\OptionListResource;
use UnitEnum;

/**
 * 従業員カテゴリ (SYSTEM_BRAIN APP_02): the options of the user's 従業員カテゴリ dropdown and the dashboards each
 * category may open (DashboardAccess). 共通管理 is never offered here — it follows ユーザー種別.
 */
final class EmployeeCategoryResource extends OptionListResource
{
    protected static ?string $model = EmployeeCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Users->value;

    protected static ?string $navigationLabel = '従業員カテゴリ';

    protected static ?string $modelLabel = '従業員カテゴリ';

    protected static ?string $pluralModelLabel = '従業員カテゴリ';

    protected static ?int $navigationSort = 2;

    public static function optionLabel(): string
    {
        return 'カテゴリ';
    }

    public static function optionExample(): string
    {
        return '例：派遣';
    }

    public static function createOption(array $data): EmployeeCategory
    {
        $category = EmployeeCategory::createAtEnd((string) $data['name'], (bool) ($data['is_active'] ?? true));
        $category->update(['dashboards' => array_values((array) ($data['dashboards'] ?? []))]);

        return $category;
    }

    protected static function extraFormComponents(): array
    {
        return [
            CheckboxList::make('dashboards')
                ->label('利用可能なダッシュボード')
                ->options(fn (): array => DashboardAccess::grantableOptions())
                ->columns(2),
        ];
    }

    protected static function extraColumns(): array
    {
        return [
            TextColumn::make('dashboards')
                ->label('利用可能なダッシュボード')
                ->badge()
                ->color('info')
                ->formatStateUsing(fn (string $state): string => DashboardAccess::grantableOptions()[$state] ?? $state)
                ->placeholder('なし'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployeeCategories::route('/'),
        ];
    }
}
