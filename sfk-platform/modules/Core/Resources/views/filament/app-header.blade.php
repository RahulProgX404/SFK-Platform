{{--
    Header of an app screen (Modules\Core\Filament\AppPages\Concerns\HasAppChrome; design_ref/app-index.png and
    add-invoice.png): only the app name — on a list after the home link, on a record / form after ← (back to the list) —
    with (i) opening the app's description in a popup. The breadcrumb home › app (› step) stays on every screen.
    list: エクスポート (帳票出力) and 新規 on the right; the summary cards (summaryCards(), to users who see everyone's money); the 一覧 as tabs; the toolbar
          of a view without a table (calendar / custom). An app of one screen (AppResource::isSingleScreen): ⚙ in place
          of 新規, no tabs, no 絞り込む, no toolbar.
    view: 編集, 再利用, 「…」 (印刷・削除), エクスポート, ⚙ and ‹ › on the right; the app's legacy buttons under the name.
    create / edit: nothing more (キャンセル / 保存 are in the footer of the form).
    settings: the tabs of アプリの設定.
--}}
@php
    use Filament\Support\Icons\Heroicon;
    use Modules\Core\Filament\Support\MenuButtons;

    $actions = $page->getCachedHeaderActions();
    $visible = fn ($action): bool => $action->isVisible();
    // the app's legacy buttons (menuActions()) and エクスポート; the rest are the page's own (編集, 「…」 …)
    $menuActions = array_values(array_filter($actions, fn ($action): bool => MenuButtons::isMenuButton($action) && $visible($action)));
    $export = array_values(array_filter($actions, fn ($action): bool => MenuButtons::isExport($action) && $visible($action)))[0] ?? null;
    $own = fn ($action): bool => ! MenuButtons::isMenuButton($action) && ! MenuButtons::isExport($action) && ! MenuButtons::isSettings($action);
    // on a record: 編集, 再利用 and 「…」 at the top right; the page's other buttons (e.g. 申請する) go under the name
    $isStandard = fn ($action): bool => $action instanceof \Filament\Actions\ActionGroup || in_array($action->getName(), ['edit', 'duplicate'], true);
    $endActions = array_values(array_filter($actions, fn ($action): bool => $own($action) && $isStandard($action)));
    $extraActions = array_values(array_filter($actions, fn ($action): bool => $own($action) && ! $isStandard($action) && $visible($action)));
    $printing = (bool) ($page->printMode ?? false);
    $menuHelp = $kind === 'view' && ! $printing ? $page->menuHelp() : [];
    // the last crumb of a record screen
    $step = ['create' => 'レコードの追加', 'edit' => 'レコードの編集', 'view' => 'レコードの詳細'][$kind] ?? null;
@endphp

<header @class(['sfk-app-header', 'sfk-app-header-'.$kind])>
    @if ($kind === 'list')
        {{-- keeps $store.sfkCheckedRows (rows checked in the table) current --}}
        <span x-data="sfkCheckedRowsWatcher" hidden></span>
    @endif

    <div class="sfk-app-head">
        <div class="sfk-app-head-title" x-data="{ info: false }">
            @unless ($printing)
                <a href="{{ $chrome['home_url'] }}" class="sfk-app-home" title="ホーム">
                    <x-filament::icon :icon="Heroicon::OutlinedHome" class="sfk-app-home-icon" />
                    <span class="fi-sr-only">ホーム</span>
                </a>
                <x-filament::icon :icon="Heroicon::ChevronRight" class="sfk-app-crumb-sep" />
            @endunless

            <h1 class="sfk-app-title">
                <a href="{{ $chrome['index_url'] }}">{{ $chrome['name'] }}</a>
            </h1>

            @if ($kind === 'settings' || ($step !== null && ! $printing))
                <x-filament::icon :icon="Heroicon::ChevronRight" class="sfk-app-crumb-sep" />
                <span class="sfk-app-subtitle" aria-current="page">{{ $step ?? 'アプリの設定' }}</span>
            @endif
            @if ($kind !== 'settings' && filled($chrome['description']) && ! $printing)
                <button type="button" class="sfk-app-info" x-on:click="info = ! info" x-bind:class="{ 'is-active': info }"
                    x-bind:aria-expanded="info ? 'true' : 'false'" title="アプリの説明">
                    <x-filament::icon :icon="Heroicon::OutlinedInformationCircle" class="sfk-app-info-icon" />
                    <span class="fi-sr-only">アプリの説明</span>
                </button>
                <div class="sfk-app-info-panel" x-show="info" x-cloak x-transition.opacity x-on:click.outside="info = false"
                    x-on:keydown.escape.window="info = false" role="dialog" aria-label="アプリの説明">
                    <button type="button" class="sfk-app-info-close" x-on:click="info = false" title="閉じる">
                        <x-filament::icon :icon="Heroicon::XMark" class="sfk-app-info-close-icon" />
                        <span class="fi-sr-only">閉じる</span>
                    </button>
                    <div class="sfk-app-info-text">{{ $chrome['description'] }}</div>
                </div>
            @endif
        </div>

        <div class="sfk-app-head-actions">
            @if ($kind === 'list')
                @if ($export)
                    @include('core::filament.app-export', ['export' => $export])
                @endif
                @if ($chrome['single_screen'])
                    {{-- an app of one screen: ⚙ (アプリの権限) in place of 新規 --}}
                    @include('core::filament.app-settings-menu', ['chrome' => $chrome])
                @elseif ($chrome['create_url'])
                    <a href="{{ $chrome['create_url'] }}" class="sfk-app-new" title="レコードを追加する"
                        @if ($chrome['create_app']) x-on:click.prevent="$dispatch(@js(\Modules\Core\Filament\AppResource::QUICK_CREATE_EVENT), { app: @js($chrome['create_app']) })" @endif>
                        <x-filament::icon :icon="Heroicon::PlusCircle" class="sfk-app-new-icon" />
                        <span>新規</span>
                    </a>
                @endif
            @elseif ($kind === 'view' && $printing)
                {{-- 印刷する: print view --}}
                <div class="sfk-print-toolbar">
                    <button type="button" class="sfk-filter-btn sfk-filter-btn-submit" x-on:click="window.print()">印刷</button>
                    {{-- 閉じる: closes the print tab; when the browser keeps it open, back to the record --}}
                    <button type="button" class="sfk-filter-btn"
                        x-on:click="window.close(); setTimeout(() => window.location.href = @js($page::getResource()::getUrl('view', ['record' => $page->record])), 300)">閉じる</button>
                </div>
            @elseif ($kind === 'view')
                @if ($endActions)
                    <x-filament::actions :actions="$endActions" class="sfk-app-actions" />
                @endif
                @if ($export)
                    @include('core::filament.app-export', ['export' => $export])
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
            @endif
        </div>
    </div>

    @if (in_array($kind, ['view', 'create', 'edit'], true) && ($menuActions || $extraActions) && ! $printing)
        {{-- the page's own buttons and the app's legacy buttons, under the app name --}}
        <div class="sfk-app-record-menu" x-data x-init="$store.sfkAppMenu.use(@js('sfk-app-help:'.$page::class))">
            @if ($extraActions)
                <x-filament::actions :actions="$extraActions" class="sfk-app-actions" />
            @endif
            @if ($menuActions)
                @include('core::filament.app-menu', ['actions' => $menuActions])
            @endif
            @if ($menuHelp)
                <button type="button" class="sfk-list-tool sfk-list-tool-square sfk-app-help-switch"
                    x-on:click="$store.sfkAppMenu.help = ! $store.sfkAppMenu.help" x-bind:class="{ 'is-active': $store.sfkAppMenu.help }" title="ボタンの説明">
                    <x-filament::icon :icon="Heroicon::OutlinedInformationCircle" class="sfk-list-tool-icon" />
                    <span class="fi-sr-only">ボタンの説明</span>
                </button>
                @include('core::filament.app-menu-help', ['help' => $menuHelp])
            @endif
        </div>
    @endif

    @if ($kind === 'list')
        @if (! $page->currentView()->isCalendar() && ($cards = $page->shownSummaryCards()) !== [])
            @include('core::filament.app-summary', ['cards' => $cards])
        @endif

        @if (! $chrome['single_screen'] && count($views = $page->allListViews()) > 1)
            <nav class="sfk-app-tabs" aria-label="一覧">
                @foreach ($views as $key => $label)
                    <button type="button" wire:click="$set('listView', @js((string) $key))"
                        @class(['sfk-app-tab', 'is-active' => $page->listView === (string) $key])
                        @if ($page->listView === (string) $key) aria-current="page" @endif>{{ $label }}</button>
                @endforeach
            </nav>
        @endif

        @if (! $chrome['single_screen'] && $page->filterFields() !== [])
            @include('core::filament.app-filter', ['page' => $page])
        @endif

        @unless ($chrome['single_screen'] || $page->currentView()->isTable())
            @include('core::filament.app-list-tools', ['page' => $page, 'part' => 'bar'])
        @endunless
    @elseif ($kind === 'settings')
        {{-- アプリの設定: 一覧 (AppListViewSettings) | 設定 (AppSettings: アクセス権, その他の設定) | 帳票出力 --}}
        <nav class="sfk-settings-tabs" aria-label="アプリの設定">
            @foreach ($chrome['settings_tabs'] as $tab => [$tabLabel, $tabUrl])
                <a href="{{ $tabUrl }}" @class(['sfk-settings-tab', 'is-active' => $page->settingsTab() === $tab]) @if ($page->settingsTab() === $tab) aria-current="page" @endif>{{ $tabLabel }}</a>
            @endforeach
        </nav>
    @endif
</header>
