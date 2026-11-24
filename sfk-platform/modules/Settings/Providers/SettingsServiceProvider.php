<?php

declare(strict_types=1);

namespace Modules\Settings\Providers;

use App\Modules\ModuleServiceProvider;
use Modules\Core\Filament\AppPages\AppSidebarState;
use Modules\Settings\Filament\Resources\Users\Pages\CreateUser;
use Modules\Settings\Filament\Resources\Users\Pages\EditUser;
use Modules\Settings\Filament\SettingsPanelProvider;

final class SettingsServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'settings';

    protected function registerModule(): void
    {
        // The extension's own Filament panel (/settings or its own domain).
        $this->app->register(SettingsPanelProvider::class);
    }

    protected function bootModule(): void
    {
        // ユーザーの登録 / 編集 open with the sidebar open, like プロフィール (user 2026-11-24)
        AppSidebarState::expandOn(CreateUser::class);
        AppSidebarState::expandOn(EditUser::class);
    }
}
