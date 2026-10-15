{{--
    Dashboard name in the sidebar header / topbar (replaces the panel brand). With several dashboards it opens
    a dropdown of the dashboards the user may open (DashboardAccess); a single one is a plain link home.
    $place: "sidebar" or "topbar".
--}}
<div
    class="sfk-dashboard-switcher sfk-dashboard-switcher-{{ $place }}"
    @if ($place === 'sidebar') x-show="$store.sidebar.isOpen" @endif
>
    @if (count($dashboards) > 1)
        <x-filament::dropdown placement="bottom-start" teleport width="xs">
            <x-slot name="trigger">
                <button type="button" class="sfk-dashboard-switcher-btn" aria-label="ダッシュボードを切り替え（現在：{{ $currentLabel }}）">
                    <span class="sfk-dashboard-switcher-name">{{ $currentLabel }}</span>
                    <x-filament::icon icon="heroicon-m-chevron-down" class="sfk-dashboard-switcher-chevron" />
                </button>
            </x-slot>

            <x-filament::dropdown.header icon="heroicon-o-squares-2x2" color="gray">
                ダッシュボード
            </x-filament::dropdown.header>

            <x-filament::dropdown.list>
                @foreach ($dashboards as $dashboard)
                    @if ($dashboard->id === $current)
                        <x-filament::dropdown.list.item icon="heroicon-m-check" color="primary" tag="a" :href="$dashboard->url">
                            {{ $dashboard->label }}
                        </x-filament::dropdown.list.item>
                    @else
                        <x-filament::dropdown.list.item icon="heroicon-o-arrow-right" tag="a" :href="$dashboard->url">
                            {{ $dashboard->label }}
                        </x-filament::dropdown.list.item>
                    @endif
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    @else
        <a href="{{ $homeUrl }}" class="sfk-dashboard-switcher-btn">
            <span class="sfk-dashboard-switcher-name">{{ $currentLabel }}</span>
        </a>
    @endif
</div>
