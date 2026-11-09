<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;

/**
 * The sidebar on the app screens (user, 2026-11-09): an app opens with the sidebar collapsed (the floating button opens
 * it), every time one of its screens is loaded. Filament keeps the sidebar's state in the browser (Alpine `$persist`,
 * localStorage `isOpen` / `isOpenDesktop`) and reads it when Alpine starts, so a script at the start of the body sets it
 * before that — no flash of the open sidebar. The state the user had before the first app screen is kept aside
 * (`sfk-sidebar-outside-apps`) and comes back on the first screen that is not an app's (the dashboard, 分析, 設定 …), so
 * the apps do not close the sidebar there. Drawn by the panels (BasePanelProvider, PanelsRenderHook::BODY_START).
 */
final class AppSidebarState
{
    public const SAVED_KEY = 'sfk-sidebar-outside-apps';

    /** @param  array<int, string>  $scopes  the render hook scopes of the page (its class and its resource's) */
    public static function script(array $scopes): string
    {
        $key = json_encode(self::SAVED_KEY);
        $body = self::isAppScreen($scopes)
            ? "if(s.getItem({$key})===null){s.setItem({$key},s.getItem('isOpenDesktop')??'true')}s.setItem('isOpen','false');s.setItem('isOpenDesktop','false')"
            : "const v=s.getItem({$key});if(v!==null){s.setItem('isOpenDesktop',v);s.setItem('isOpen',v);s.removeItem({$key})}";

        return "<script>(()=>{try{const s=window.localStorage;{$body}}catch(e){}})()</script>";
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
