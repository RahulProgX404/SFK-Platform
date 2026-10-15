<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Widgets;

use Filament\Widgets\Widget;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\Position;
use Modules\Settings\Application\UserStatistics;
use Modules\Settings\Filament\Pages\AppsSettings;
use Modules\Settings\Filament\Resources\EmployeeCategories\EmployeeCategoryResource;
use Modules\Settings\Filament\Resources\Positions\PositionResource;
use Modules\Settings\Filament\Resources\Users\UserResource;

/**
 * Settings hub on the dashboard: one card per settings area. Add a card here when a new settings
 * area (resource or page) is added to the panel.
 */
final class SettingsMenuWidget extends Widget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'settings::widgets.settings-menu';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $statistics = app(UserStatistics::class);

        return [
            'sections' => [
                [
                    'title' => 'ユーザー管理',
                    'icon' => 'heroicon-o-users',
                    'url' => UserResource::getUrl('index'),
                    'metric' => number_format($statistics->total()).' 名',
                ],
                [
                    'title' => '従業員カテゴリ',
                    'icon' => 'heroicon-o-tag',
                    'url' => EmployeeCategoryResource::getUrl('index'),
                    'metric' => number_format(EmployeeCategory::query()->where('is_active', true)->count()).' カテゴリ',
                ],
                [
                    'title' => '役職',
                    'icon' => 'heroicon-o-identification',
                    'url' => PositionResource::getUrl('index'),
                    'metric' => number_format(Position::query()->where('is_active', true)->count()).' 役職',
                ],
                [
                    'title' => 'アプリ設定',
                    'icon' => 'heroicon-o-squares-2x2',
                    'url' => AppsSettings::getUrl(),
                    'metric' => number_format(BusinessApp::query()->count()).' アプリ / 公開中 '
                        .number_format(BusinessApp::query()->where('status', AppStatus::Published->value)->count()),
                ],
            ],
        ];
    }
}
