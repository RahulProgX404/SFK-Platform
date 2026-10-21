{{--
    Dashboard name in the sidebar header / topbar (replaces the panel brand): a link to the dashboard's home (its
    ポータル). Changing the dashboard is the menu item ダッシュボード切替 (core::filament.dashboard-menu).
    $place: "sidebar" or "topbar".
--}}
<div
    class="sfk-dashboard-switcher sfk-dashboard-switcher-{{ $place }}"
    @if ($place === 'sidebar') x-show="$store.sidebar.isOpen" @endif
>
    <a href="{{ $homeUrl }}" class="sfk-dashboard-switcher-btn">
        <span class="sfk-dashboard-switcher-name">{{ $currentLabel }}</span>
    </a>
</div>
