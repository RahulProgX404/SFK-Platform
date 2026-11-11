<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;
use Modules\Core\Filament\Portal\PortalPage;

/**
 * The sidebar on the app screens (user, 2026-11-09) and on the dashboard's home (PortalPage, e.g. 白石封筒; user,
 * 2026-11-11): they open with the sidebar collapsed (the floating button opens it), every time one of them is loaded. Filament keeps the sidebar's state in the browser (Alpine `$persist`,
 * localStorage `isOpen` / `isOpenDesktop`) and reads it when Alpine starts, so a script at the start of the body sets it
 * before that — no flash of the open sidebar. The state the user had before the first app screen is kept aside
 * (`sfk-sidebar-outside-apps`) and comes back on the first other screen (分析, 設定, ダッシュボード設定 …), so the apps
 * and the home do not close the sidebar there. Drawn by the panels (BasePanelProvider, PanelsRenderHook::BODY_START); with
 * SPA navigation (wire:navigate) it runs again on every page and sets Filament's sidebar store as well.
 */
final class AppSidebarState
{
    public const SAVED_KEY = 'sfk-sidebar-outside-apps';

    /** @param  array<int, string>  $scopes  the render hook scopes of the page (its class and its resource's) */
    public static function script(array $scopes): string
    {
        $key = json_encode(self::SAVED_KEY);
        $body = self::opensCollapsed($scopes)
            ? "if(s.getItem({$key})===null){s.setItem({$key},s.getItem('isOpenDesktop')??'true')}s.setItem('isOpen','false');s.setItem('isOpenDesktop','false')"
            : "const v=s.getItem({$key});if(v!==null){s.setItem('isOpenDesktop',v);s.setItem('isOpen',v);s.removeItem({$key})}";

        // SPA navigation (wire:navigate): Alpine already runs and its sidebar store read the storage when it started, so the
        // state is also set on the store (on a full page load Alpine starts after this and reads the storage itself)
        $store = "const t=window.Alpine&&window.Alpine.store?window.Alpine.store('sidebar'):null;"
            ."if(t){t.isOpenDesktop=JSON.parse(s.getItem('isOpenDesktop')??'true');t.isOpen=JSON.parse(s.getItem('isOpen')??'true')}";

        return "<script>(()=>{try{const s=window.localStorage;{$body};{$store}}catch(e){}})()</script>";
    }

    /** @param  array<int, string>  $scopes  an app's screen or the dashboard's home */
    public static function opensCollapsed(array $scopes): bool
    {
        return in_array(PortalPage::class, $scopes, true) || self::isAppScreen($scopes);
    }

    /** @param  array<int, string>  $scopes */
    public static function isAppScreen(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if (class_exists($scope) && in_array(HasAppChrome::class, class_uses_recursive($scope), true)) {
                return true;
            }
        }

        return false;
    }
}
