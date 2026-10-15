<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings;

use App\Models\User;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\AppSettingsResource;
use Modules\Core\Filament\DashboardSettings\Resources\Spaces\SpaceResource;

/**
 * ダッシュボード設定 (permission.md "Dashboard"): added by BasePanelProvider to every dashboard except the
 * administrator-only ones (共通管理). Administrators maintain the dashboard's spaces (members and apps)
 * and each app's permissions; employees never see it.
 */
final class DashboardSettingsPlugin implements Plugin
{
    public const NAVIGATION_GROUP = 'ダッシュボード設定';

    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'dashboard-settings';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            SpaceResource::class,
            AppSettingsResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}

    public static function currentPanelId(): string
    {
        return (string) Filament::getCurrentPanel()?->getId();
    }

    public static function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function actorIsAdministrator(): bool
    {
        return self::actor()?->user_type->isAdministrator() ?? false;
    }
}
