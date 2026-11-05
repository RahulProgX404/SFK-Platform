{{--
    Toolbar of an app list (design_ref/app-index.png; Modules\Core\Filament\AppPages\AppListRecords::toolbarPart()).
    $part: start — after the search box: the column searched, 絞り込む and the switch showing the app's legacy buttons
           (with ボタンの説明 once they are shown);
           sort — 並び替え;
           settings — ⚙ (right of 並び替え);
           menu — the row of the legacy buttons under the toolbar ($store.sfkAppMenu);
           bar — the toolbar of a カレンダー形式 / カスタマイズ形式 view (no table): start, ⚙ and the legacy buttons.
--}}
@php
    use Filament\Support\Icons\Heroicon;
    use Illuminate\Support\Arr;
    use Illuminate\Support\Js;

    $tools = $page->toolbarActions();
    $help = $page->menuHelp();
    $menuKey = 'sfk-app-menu:'.$page::class;
@endphp

@if ($part === 'start' || $part === 'bar')
    @if ($part === 'bar')
        <div class="sfk-app-list-bar">
    @endif

    <div class="sfk-list-tools" x-data x-init="$store.sfkAppMenu.use(@js($menuKey))">
        @if ($part === 'start')
            @include('core::filament.app-search-column', ['page' => $page])
        @endif

        @if ($page->filterFields() !== [])
            <button
                type="button"
                @class(['sfk-list-tool', 'sfk-app-filter-button', 'is-active' => $page->activeFilter()->hasConditions()])
                x-on:click="$dispatch('sfk-app-filter-open', @js($page->appFilterState()))"
                aria-haspopup="dialog"
            >
                <x-filament::icon :icon="Heroicon::OutlinedFunnel" class="sfk-list-tool-icon" />
                <span>絞り込む</span>
            </button>
        @endif

        @if ($tools['menu'] !== [])
            <button
                type="button"
                class="sfk-list-tool sfk-list-tool-square sfk-app-menu-switch"
                x-on:click="$store.sfkAppMenu.toggle()"
                x-bind:class="{ 'is-active': $store.sfkAppMenu.open }"
                x-bind:aria-expanded="$store.sfkAppMenu.open ? 'true' : 'false'"
                x-bind:title="$store.sfkAppMenu.open ? 'ボタンを隠す' : 'ボタンを表示する'"
            >
                <x-filament::icon :icon="Heroicon::OutlinedSquares2x2" class="sfk-list-tool-icon" />
                <span class="fi-sr-only">ボタンを表示する</span>
            </button>

            @if ($help !== [])
                <button
                    type="button"
                    class="sfk-list-tool sfk-list-tool-square sfk-app-help-switch"
                    x-show="$store.sfkAppMenu.open"
                    x-cloak
                    x-on:click="$store.sfkAppMenu.help = ! $store.sfkAppMenu.help"
                    x-bind:class="{ 'is-active': $store.sfkAppMenu.help }"
                    title="ボタンの説明"
                >
                    <x-filament::icon :icon="Heroicon::OutlinedInformationCircle" class="sfk-list-tool-icon" />
                    <span class="fi-sr-only">ボタンの説明</span>
                </button>
            @endif
        @endif
    </div>

    @if ($part === 'bar')
            @if ($tools['settings'])
                <div class="sfk-list-tools sfk-list-tools-end">{{ $tools['settings'] }}</div>
            @endif
        </div>
        @include('core::filament.app-list-tools', ['page' => $page, 'part' => 'menu'])
    @endif
@elseif ($part === 'sort')
    @php
        $options = $page->sortOptions();
        $column = $page->getTableSortColumn();
        $direction = $page->getTableSortDirection() === 'desc' ? 'desc' : 'asc';
        $current = filled($column) ? ($options[$column] ?? null) : null;
    @endphp

    @if ($options !== [])
        <x-filament::dropdown placement="bottom-end" width="xs" max-height="24rem" :teleport="true" class="sfk-list-sort">
            <x-slot name="trigger">
                <button type="button" class="sfk-list-tool" aria-label="並び替え">
                    <x-filament::icon :icon="Heroicon::OutlinedBarsArrowDown" class="sfk-list-tool-icon" />
                    <span>並び替え：<span class="sfk-list-sort-value">{{ $current ?? '標準' }}</span></span>
                    @if ($current !== null)
                        <span class="sfk-list-sort-dir">{{ $direction === 'asc' ? '昇順' : '降順' }}</span>
                    @endif
                    <x-filament::icon :icon="Heroicon::ChevronDown" class="sfk-list-tool-chevron" />
                </button>
            </x-slot>

            @if ($current !== null)
                <div class="sfk-list-sort-dirs" role="group" aria-label="並び順">
                    @foreach (['asc' => '昇順', 'desc' => '降順'] as $value => $label)
                        <button type="button" @class(['sfk-list-sort-dir-btn', 'is-active' => $direction === $value])
                            wire:click="sortTable({{ Js::from($column) }}, '{{ $value }}')">{{ $label }}</button>
                    @endforeach
                </div>
            @endif

            <x-filament::dropdown.list>
                <x-filament::dropdown.list.item tag="button" wire:click="useDefaultSort"
                    :icon="$current === null ? 'heroicon-m-check' : null"
                    :class="Arr::toCssClasses(['sfk-search-column-item', 'is-selected' => $current === null])">標準</x-filament::dropdown.list.item>
                @foreach ($options as $name => $label)
                    <x-filament::dropdown.list.item tag="button" wire:click="sortTable({{ Js::from($name) }}, '{{ $column === $name ? $direction : 'asc' }}')"
                        :icon="$column === $name ? 'heroicon-m-check' : null"
                        :class="Arr::toCssClasses(['sfk-search-column-item', 'is-selected' => $column === $name])">{{ $label }}</x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    @endif
@elseif ($part === 'settings')
    @if ($tools['settings'])
        {{ $tools['settings'] }}
    @endif
@elseif ($part === 'menu')
    @if ($tools['menu'] !== [])
        <div class="sfk-app-menu-row" x-data x-show="$store.sfkAppMenu.open" x-cloak>
            @include('core::filament.app-menu', ['actions' => $tools['menu']])

            @if ($help !== [])
                @include('core::filament.app-menu-help', ['help' => $help])
            @endif
        </div>
    @endif
@endif
