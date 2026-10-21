{{--
    ダッシュボード切替: the first group of the sidebar menu, drawn like the other groups (e.g. マスタ) — collapsible, its
    items the dashboards the user may open (DashboardAccess), the current one active.
--}}
<ul class="fi-sidebar-nav-groups sfk-dashboard-menu">
    <x-filament-panels::sidebar.group label="ダッシュボード切替" :items="$items" />
</ul>
