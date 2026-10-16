<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages\Concerns;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use LogicException;
use Modules\Core\Filament\AppResource;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\AppSettingsResource;
use Modules\Core\Models\BusinessApp;

/**
 * The frame of every app screen (list, create, edit, record), in place of Filament's page header: blue title bar
 * with the app icon, breadcrumb (home › スペース › アプリ), the app description and the page's toolbar
 * (core::filament.app-header). The app fills the whole content area: full width, no outer spacing, full height.
 */
trait HasAppChrome
{
    /** list | create | edit | view: selects the toolbar. */
    abstract public function appPageKind(): string;

    public function getHeader(): View
    {
        return view('core::filament.app-header', ['page' => $this, 'kind' => $this->appPageKind(), 'chrome' => $this->appChrome()]);
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<string> */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'sfk-app-page', 'sfk-app-page-'.$this->appPageKind()];
    }

    /** @return array{name: string, icon: string, space: string|null, description: string|null, index_url: string, create_url: string|null, home_url: string, settings_url: string|null} */
    public function appChrome(): array
    {
        $resource = $this->appResource();
        $app = $resource::app() ?? new BusinessApp(['name' => $resource::appName()]);

        return [
            'name' => $app->name,
            'icon' => $app->iconText(),
            'space' => $app->relationLoaded('space') ? $app->space?->name : null,
            'description' => filled($app->description) ? $app->description : $resource::appDescription(),
            'index_url' => $resource::getUrl('index'),
            'create_url' => $resource::hasPage('create') && $resource::canCreate() ? $resource::getUrl('create') : null,
            'home_url' => Filament::getCurrentPanel()?->getUrl() ?? url('/'),
            'settings_url' => $this->appSettingsUrl($app),
        ];
    }

    /** @return class-string<AppResource> */
    protected function appResource(): string
    {
        $resource = static::getResource();

        if (! is_subclass_of($resource, AppResource::class)) {
            throw new LogicException(static::class.' must belong to an AppResource.');
        }

        return $resource;
    }

    /** Gear button (administrators): アプリの設定 → 一覧 when the app has it, else the app's permissions. */
    private function appSettingsUrl(BusinessApp $app): ?string
    {
        $resource = $this->appResource();

        if ($resource::hasPage('views') && $this->isAppAdministrator() && $app->exists) {
            return $resource::getUrl('views');
        }

        return $this->appPermissionsUrl($app);
    }

    /** アクセス権 of the app (administrators): its permissions in this dashboard, else アプリ設定. */
    public function appPermissionsUrl(BusinessApp $app): ?string
    {
        if (! $this->isAppAdministrator() || ! $app->exists) {
            return null;
        }

        $panel = Filament::getCurrentPanel();
        if ($panel !== null
            && in_array(AppSettingsResource::class, $panel->getResources(), true)
            && AppSettingsResource::getEloquentQuery()->whereKey($app->getKey())->exists()) {
            return AppSettingsResource::getUrl('edit', ['record' => $app]);
        }

        return Route::has('filament.settings.pages.apps') ? route('filament.settings.pages.apps') : null;
    }

    private function isAppAdministrator(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->user_type->isAdministrator();
    }
}
