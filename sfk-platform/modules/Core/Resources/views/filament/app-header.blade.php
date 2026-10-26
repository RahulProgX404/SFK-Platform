{{-- Frame of an app screen (Modules\Core\Filament\AppPages\Concerns\HasAppChrome). --}}
@php
    use Filament\Support\Icons\Heroicon;

    use Modules\Core\Filament\Support\MenuButtons;

    $actions = $page->getCachedHeaderActions();
    // the app's legacy buttons (menuActions()): right of 一覧 / 絞り込む on a list, before 編集 on a record
    $menuActions = array_values(array_filter($actions, fn ($action): bool => MenuButtons::isMenuButton($action) && $action->isVisible()));
    $endActions = array_values(array_filter($actions, fn ($action): bool => ! MenuButtons::isMenuButton($action)));
    $menuHelp = in_array($kind, ['list', 'view'], true) && method_exists($page, 'menuHelp') && ! ($page->printMode ?? false) ? $page->menuHelp() : [];
@endphp

<header class="sfk-app-header">
    <div class="sfk-app-titlebar">
        <span class="sfk-app-icon" aria-hidden="true">{{ $chrome['icon'] }}</span>
        <h1 class="sfk-app-title">
            <a href="{{ $chrome['index_url'] }}">{{ $chrome['name'] }}</a>
        </h1>
    </div>

    <nav class="sfk-app-crumbs" aria-label="パンくずリスト">
        <a href="{{ $chrome['home_url'] }}" class="sfk-app-home" title="ポータル">
            <x-filament::icon :icon="Heroicon::Home" class="sfk-app-home-icon" />
        </a>
        @if (filled($chrome['space']))
            <span class="sfk-app-crumb-sep" aria-hidden="true">›</span>
            <span>スペース: {{ $chrome['space'] }}</span>
        @endif
        <span class="sfk-app-crumb-sep" aria-hidden="true">›</span>
        <a href="{{ $chrome['index_url'] }}">アプリ: {{ $chrome['name'] }}</a>
        @if ($kind === 'settings')
            <span class="sfk-app-crumb-sep" aria-hidden="true">›</span>
            <span>アプリの設定</span>
        @endif
    </nav>

    @if ($kind !== 'settings' && filled($chrome['description']))
        <div class="sfk-app-description">{{ $chrome['description'] }}</div>
    @endif

    <div class="sfk-app-toolbar">
        @if ($kind === 'list')
            {{-- keeps $store.sfkCheckedRows (rows checked in the table) current --}}
            <span x-data="sfkCheckedRowsWatcher" hidden></span>
            <div class="sfk-app-toolbar-start">
                @if (count($page->allListViews()) > 0)
                    <label class="sfk-app-view">
                        <span class="fi-sr-only">一覧</span>
                        <select wire:model.live="listView" class="sfk-app-view-select">
                            @foreach ($page->allListViews() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-filament::icon :icon="Heroicon::ChevronDown" class="sfk-app-view-chevron" />
                    </label>
                @endif

                @if ($page->filterFields() !== [])
                    <button
                        type="button"
                        class="sfk-app-tool sfk-app-filter-button"
                        x-on:click="$dispatch('sfk-app-filter-open', @js($page->appFilterState()))"
                        title="絞り込む"
                        aria-haspopup="dialog"
                    >
                        <x-filament::icon :icon="Heroicon::OutlinedFunnel" class="sfk-app-tool-icon" />
                        <span class="fi-sr-only">絞り込む</span>
                    </button>

                    @include('core::filament.app-filter', ['page' => $page])
                @endif

                @if ($menuActions)
                    <span class="sfk-app-menu-divider" aria-hidden="true"></span>
                    @include('core::filament.app-menu', ['actions' => $menuActions])
                @endif
            </div>

            <div class="sfk-app-toolbar-end">
                @if ($chrome['create_url'])
                    <a href="{{ $chrome['create_url'] }}" class="sfk-app-add" title="レコードを追加する">
                        <x-filament::icon :icon="Heroicon::Plus" class="sfk-app-add-icon" />
                        <span class="fi-sr-only">レコードを追加する</span>
                    </a>
                @endif

                @include('core::filament.app-settings-menu', ['chrome' => $chrome])

                @if ($endActions)
                    <x-filament::actions :actions="$endActions" class="sfk-app-actions" />
                @endif
            </div>
        @elseif ($kind === 'settings')
            {{-- アプリの設定: 一覧 (AppListViewSettings) | 設定 (AppSettings: アクセス権, その他の設定) --}}
            <nav class="sfk-settings-tabs" aria-label="アプリの設定">
                @foreach ($chrome['settings_tabs'] as $tab => [$tabLabel, $tabUrl])
                    <a href="{{ $tabUrl }}" @class(['sfk-settings-tab', 'is-active' => $page->settingsTab() === $tab]) @if ($page->settingsTab() === $tab) aria-current="page" @endif>{{ $tabLabel }}</a>
                @endforeach
            </nav>
        @elseif ($kind === 'create' || $kind === 'edit')
            <div class="sfk-app-toolbar-start">
                <x-filament::actions :actions="$actions" class="sfk-app-actions" />
            </div>
        @elseif ($page->printMode ?? false)
            {{-- 印刷する: print view --}}
            <div class="sfk-app-toolbar-start sfk-print-toolbar">
                <button type="button" class="sfk-filter-btn sfk-filter-btn-submit" x-on:click="window.print()">印刷</button>
                {{-- 閉じる: closes the print tab; when the browser keeps it open, back to the record --}}
                <button type="button" class="sfk-filter-btn"
                        x-on:click="window.close(); setTimeout(() => window.location.href = @js($page::getResource()::getUrl('view', ['record' => $page->record])), 300)">閉じる</button>
            </div>
        @else
            {{-- record: 編集 / 再利用 / 「…」 (印刷・削除), then gear and ‹ ›, all on the right --}}
            <div class="sfk-app-toolbar-end">
                @if ($menuActions)
                    @include('core::filament.app-menu', ['actions' => $menuActions])
                    <span class="sfk-app-menu-divider" aria-hidden="true"></span>
                @endif

                @if ($endActions)
                    <x-filament::actions :actions="$endActions" class="sfk-app-actions" />
                @endif

                @include('core::filament.app-settings-menu', ['chrome' => $chrome])

                @php($neighbours = $page->recordNeighbours())
                <nav class="sfk-app-pager" aria-label="レコードの移動">
                    @foreach (['previous' => ['前のレコード', Heroicon::ChevronLeft], 'next' => ['次のレコード', Heroicon::ChevronRight]] as $direction => [$label, $icon])
                        @if ($neighbours[$direction] !== null)
                            <a href="{{ $neighbours[$direction] }}" class="sfk-app-pager-btn" title="{{ $label }}" rel="{{ $direction === 'previous' ? 'prev' : 'next' }}">
                                <x-filament::icon :icon="$icon" class="sfk-app-pager-icon" />
                                <span class="fi-sr-only">{{ $label }}</span>
                            </a>
                        @else
                            <span class="sfk-app-pager-btn is-disabled" aria-hidden="true">
                                <x-filament::icon :icon="$icon" class="sfk-app-pager-icon" />
                            </span>
                        @endif
                    @endforeach
                </nav>
            </div>
        @endif
    </div>

    {{-- 帳票出力 in the 上部スペース --}}
    @if (in_array($kind, ['list', 'view'], true) && method_exists($page, 'documentBar') && ($documentBar = $page->documentBar()) !== [])
        @include('core::filament.documents.bar', ['bar' => $documentBar, 'kind' => $kind])
    @endif

    @if ($menuHelp)
        @include('core::filament.app-menu-help', ['help' => $menuHelp, 'key' => 'sfk-app-help:'.$page::class])
    @endif
</header>
