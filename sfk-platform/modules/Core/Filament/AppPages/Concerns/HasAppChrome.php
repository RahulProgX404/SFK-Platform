<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages\Concerns;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use LogicException;
use Modules\Core\Filament\AppPages\AppListRecords;
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

    /** @return array{name: string, icon: string, space: string|null, description: string|null, index_url: string, create_url: string|null, home_url: string, settings_url: string|null, settings_menu: list<array{label: string, url: string}>, settings_tabs: array<string, array{0: string, 1: string}>} */
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
            'settings_menu' => $this->appSettingsMenu($app),
            'settings_tabs' => $this->appSettingsTabs(),
        ];
    }

    /**
     * ▾ next to the gear (legacy shortcuts of アプリの設定, administrators): この一覧の設定を開く (a stored view of the
     * list), 一覧, 設定, アクセス権 and その他の設定.
     *
     * @return list<array{label: string, url: string}>
     */
    private function appSettingsMenu(BusinessApp $app): array
    {
        $resource = $this->appResource();
        if (! $this->isAppAdministrator() || ! $app->exists) {
            return [];
        }

        $menu = [];
        if ($resource::hasPage('views')) {
            $view = $this instanceof AppListRecords ? $this->currentView() : null;
            if ($view?->id !== null) {
                $menu[] = ['label' => 'この一覧の設定を開く', 'url' => $resource::getUrl('views', ['edit' => $view->id])];
            }
            $menu[] = ['label' => '一覧', 'url' => $resource::getUrl('views')];
        }
        if ($resource::hasPage('settings')) {
            $menu[] = ['label' => '設定', 'url' => $resource::getUrl('settings')];
        }
        if (($permissions = $this->appPermissionsUrl($app)) !== null) {
            $menu[] = ['label' => 'アクセス権', 'url' => $permissions];
        }
        if ($resource::hasPage('settings')) {
            $menu[] = ['label' => 'その他の設定', 'url' => $resource::getUrl('settings').'#misc'];
        }

        return $menu;
    }

    /** @return array<string, array{0: string, 1: string}> アプリの設定 tabs: key => [label, url] */
    private function appSettingsTabs(): array
    {
        $resource = $this->appResource();
        $tabs = [];
        if ($resource::hasPage('views')) {
            $tabs['views'] = ['一覧', $resource::getUrl('views')];
        }
        if ($resource::hasPage('settings')) {
            $tabs['settings'] = ['設定', $resource::getUrl('settings')];
        }

        return $tabs;
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
