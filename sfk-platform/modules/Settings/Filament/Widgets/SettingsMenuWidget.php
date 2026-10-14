<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Widgets;

use Filament\Widgets\Widget;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Position;
use Modules\Settings\Application\UserStatistics;
use Modules\Settings\Filament\Resources\Organizations\OrganizationResource;
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
                    'description' => 'ユーザーの登録・編集・削除、ロールやダッシュボード利用権限、在籍状況、所属組織を管理します。',
                    'icon' => 'heroicon-o-users',
                    'url' => UserResource::getUrl('index'),
                    'metric' => number_format($statistics->total()).' 名',
                    'action' => 'ユーザー管理を開く',
                ],
                [
                    'title' => '組織・役職',
                    'description' => '部署などの組織と、組織ごとの役職を作成・管理します。作成した組織と役職はユーザーに割り当てられます。',
                    'icon' => 'heroicon-o-building-office-2',
                    'url' => OrganizationResource::getUrl('index'),
                    'metric' => number_format(Organization::query()->count()).' 組織 / '.number_format(Position::query()->count()).' 役職',
                    'action' => '組織管理を開く',
                ],
            ],
        ];
    }
}
