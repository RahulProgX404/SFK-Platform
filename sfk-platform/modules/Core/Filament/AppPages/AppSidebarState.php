<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;
use Modules\Core\Filament\Portal\PortalPage;
use Modules\Core\Filament\Profile\ProfilePage;

/**
 * The sidebar on the app screens (user, 2026-11-09) and on the dashboard's home (PortalPage, e.g. 白石封筒; user,
 * 2026-11-11): they open with the sidebar collapsed (the floating button opens it), every time one of them is loaded. Filament keeps the sidebar's state in the browser (Alpine `$persist`,
 * localStorage `isOpen` / `isOpenDesktop`) and reads it when Alpine starts, so a script at the start of the body sets it
 * before that — no flash of the open sidebar. The state the user had before the first app screen is kept aside
 * (`sfk-sidebar-outside-apps`) and comes back on the first other screen (分析, 設定, ダッシュボード設定 …), so the apps
 * and the home do not close the sidebar there. The settings pages プロフィール and ユーザーの登録 / 編集 (expandOn(); user,
 * 2026-11-24) open with the sidebar open on computers the same way (phones keep theirs: there it would cover the page), and
 * the state from before comes back on the next other screen too. Drawn by the panels (BasePanelProvider, PanelsRenderHook::BODY_START); with
 * SPA navigation (wire:navigate) it runs again on every page and sets Filament's sidebar store as well.
 */
final class AppSidebarState
{
    public const SAVED_KEY = 'sfk-sidebar-outside-apps';

    /** @var list<class-string> more pages that open with the sidebar collapsed (the modules', e.g. メッセージ) */
    private static array $collapsedPages = [];

    /** @var list<class-string> more pages that open with the sidebar open (the modules', e.g. ユーザーの編集) */
    private static array $expandedPages = [];

    /**
     * A module's page that opens with the sidebar collapsed as well (メッセージ, user 2026-11-18: the messenger uses the whole
     * width; its 🏠 goes back to the dashboard).
     *
     * @param  class-string  $page
     */
    public static function collapseOn(string $page): void
    {
        if (! in_array($page, self::$collapsedPages, true)) {
            self::$collapsedPages[] = $page;
        }
    }

    /**
     * A module's page that opens with the sidebar open (ユーザーの登録 / 編集, user 2026-11-24: laid out like プロフィール).
     *
     * @param  class-string  $page
     */
    public static function expandOn(string $page): void
    {
        if (! in_array($page, self::$expandedPages, true)) {
            self::$expandedPages[] = $page;
        }
    }

    /** @param  array<int, string>  $scopes  the render hook scopes of the page (its class and its resource's) */
    public static function script(array $scopes): string
    {
        $key = json_encode(self::SAVED_KEY);
        // the user's own state is kept aside on the first such page and comes back on the next other screen
        $keep = "if(s.getItem({$key})===null){s.setItem({$key},s.getItem('isOpenDesktop')??'true')}";
        $body = match (true) {
            self::opensCollapsed($scopes) => "{$keep}s.setItem('isOpen','false');s.setItem('isOpenDesktop','false')",
            self::opensExpanded($scopes) => "{$keep}s.setItem('isOpenDesktop','true')",
            default => "const v=s.getItem({$key});if(v!==null){s.setItem('isOpenDesktop',v);s.setItem('isOpen',v);s.removeItem({$key})}",
        };

        // SPA navigation (wire:navigate): Alpine already runs and its sidebar store read the storage when it started, so the
        // state is also set on the store (on a full page load Alpine starts after this and reads the storage itself)
        $store = "const t=window.Alpine&&window.Alpine.store?window.Alpine.store('sidebar'):null;"
            ."if(t){t.isOpenDesktop=JSON.parse(s.getItem('isOpenDesktop')??'true');t.isOpen=JSON.parse(s.getItem('isOpen')??'true')}";

        return "<script>(()=>{try{const s=window.localStorage;{$body};{$store}}catch(e){}})()</script>";
    }

    /** @param  array<int, string>  $scopes  an app's screen, the dashboard's home or a page registered with collapseOn() */
    public static function opensCollapsed(array $scopes): bool
    {
        return in_array(PortalPage::class, $scopes, true)
            || array_intersect(self::$collapsedPages, $scopes) !== []
            || self::isAppScreen($scopes);
    }

    /** @param  array<int, string>  $scopes  プロフィール or a page registered with expandOn() */
    public static function opensExpanded(array $scopes): bool
    {
        return in_array(ProfilePage::class, $scopes, true) || array_intersect(self::$expandedPages, $scopes) !== [];
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
