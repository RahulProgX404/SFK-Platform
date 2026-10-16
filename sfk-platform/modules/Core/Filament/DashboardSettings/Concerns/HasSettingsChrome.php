<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Concerns;

use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Modules\Core\Filament\DashboardSettings\DashboardSettingsPlugin;

/**
 * The frame of the ダッシュボード設定 screens (スペース, アプリの権限) in the design of the app screens
 * (core::filament.settings-header): blue title bar, breadcrumb (home › ダッシュボード設定 › screen › record), the page
 * actions in the toolbar at the top, full width; lists look like app lists, forms like app record forms.
 */
trait HasSettingsChrome
{
    /** list | edit */
    abstract public function settingsPageKind(): string;

    public function getHeader(): View
    {
        $resource = static::getResource();

        return view('core::filament.settings-header', [
            'page' => $this,
            'title' => $this->getTitle(),
            'homeUrl' => Filament::getCurrentPanel()?->getUrl() ?? url('/'),
            'group' => DashboardSettingsPlugin::NAVIGATION_GROUP,
            'sectionLabel' => $resource::getNavigationLabel(),
            'sectionUrl' => $resource::getUrl('index'),
            'recordTitle' => $this->settingsRecordTitle(),
        ]);
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<string> */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'sfk-app-page', 'sfk-settings-page', 'sfk-app-page-'.$this->settingsPageKind()];
    }

    /** Last breadcrumb (the edited record), if any. */
    protected function settingsRecordTitle(): ?string
    {
        return null;
    }
}
