{{--
    The dashboard (Modules\Core\Filament\Portal\PortalPage; design_ref/Dashboard.png): the cards the business modules give
    the panel (widgets, each drawn in `sfk-dash-span-<n>` of a 12-column grid, or `-30p` / `-20p` of the row; 2 columns
    on tablets, 1 on phones), and
    アプリ — the panel over the whole screen opened from the top bar (event `sfk-open-apps`): search, スペース,
    よく使うアプリ and the apps in the user's own order. The card frame (sfk-dash-card …) is shared by the widgets.
--}}
<x-filament-panels::page>
    {{-- live: who may see what changed (財務分析を表示, アクセス権, apps) — the page redraws its cards, 新規作成 and アプリ;
         each card follows its own tables (DashboardCard::liveTables(), DataPulse) --}}
    @include('core::filament.live-data', ['tables' => [\Modules\Core\Application\Realtime\DataPulse::REFERENCE]])

    {{-- the cards --}}
    <div class="sfk-dash">
        @foreach ($this->dashboardWidgets() as $widget)
            @livewire($widget, ['pageFilters' => $this->filters], key($widget))
        @endforeach
    </div>

    {{-- 新規作成 of an app adding records in a panel over the screen (PortalPage::quickCreateComponents) --}}
    @foreach ($this->quickCreateComponents() as $quickApp => $quickComponent)
        @livewire($quickComponent, ['app' => $quickApp], key('sfk-quick-create-' . $quickApp))
    @endforeach

    {{-- アプリ: opened by the button of the top bar (core::filament.app-launcher-button) --}}
    <div class="sfk-apps" role="dialog" aria-modal="true" aria-labelledby="sfk-apps-title" x-data="{ open: false, q: '' }"
        x-show="open" x-cloak x-on:sfk-open-apps.window="open = true; setTimeout(() => $refs.q.focus(), 50)"
        x-on:keydown.escape.window="open = false"
        x-effect="document.documentElement.style.overflow = open ? 'hidden' : ''" x-transition.opacity.duration.150ms>
        <div class="sfk-apps-head">
            <h2 class="sfk-apps-title" id="sfk-apps-title">アプリ</h2>
            <label class="sfk-apps-search">
                <x-filament::icon icon="heroicon-o-magnifying-glass" />
                <span class="fi-sr-only">アプリを検索</span>
                <input type="search" x-ref="q" x-model="q" placeholder="アプリ名で検索" autocomplete="off"
                    x-on:keydown.enter.prevent="const first = $root.querySelector('.sfk-apps-grid li:not([hidden]) a'); if (first) window.location.href = first.href">
            </label>
            <div class="sfk-apps-tools">
                @if ($this->reorderAppsAction->isVisible())
                    {{ $this->reorderAppsAction }}
                @endif
                <button type="button" class="sfk-apps-close" x-on:click="open = false" title="閉じる">
                    <x-filament::icon icon="heroicon-o-x-mark" />
                    <span class="fi-sr-only">閉じる</span>
                </button>
            </div>
        </div>

        <div class="sfk-apps-body">
            {{-- スペース: filter the apps --}}
            <nav class="sfk-apps-side" aria-label="スペース">
                <p class="sfk-apps-side-head">
                    <span>スペース</span>
                    @if ($spacesUrl !== null)
                        <a href="{{ $spacesUrl }}" class="sfk-add" title="スペースを作成・管理" aria-label="スペースを作成・管理">
                            <x-filament::icon icon="heroicon-m-plus" />
                        </a>
                    @endif
                </p>
                @if ($isAdministrator)
                    <div class="sfk-apps-scope-wrap">
                        <select class="sfk-apps-scope" wire:model.live="spaceScope" aria-label="表示するスペース">
                            <option value="joined">参加中のスペース</option>
                            <option value="all">すべてのスペース</option>
                        </select>
                    </div>
                @endif
                <ul class="sfk-list">
                    <li>
                        <button type="button" wire:click="$set('appSpace', null)" @class(['sfk-row', 'is-selected' => $appSpace === null])>
                            <span class="sfk-space-icon is-all" aria-hidden="true"></span>
                            <span class="sfk-row-name">すべてのアプリ</span>
                        </button>
                    </li>
                    @foreach ($spaces as $space)
                        <li wire:key="space-{{ $space->id }}" class="sfk-space-item">
                            <button type="button" wire:click="showSpace({{ $space->id }})"
                                @class(['sfk-row', 'is-selected' => $appSpace === $space->id])>
                                <span class="sfk-space-icon" aria-hidden="true"></span>
                                <x-filament::icon icon="heroicon-s-lock-closed" class="sfk-lock" />
                                <span class="sfk-row-name">{{ $space->name }}</span>
                            </button>
                            @if (isset($spaceSettingsUrls[$space->id]))
                                <a href="{{ $spaceSettingsUrls[$space->id] }}" class="sfk-space-settings"
                                    title="スペースの設定（{{ $space->name }}）">
                                    <x-filament::icon icon="heroicon-o-cog-6-tooth" />
                                    <span class="fi-sr-only">スペースの設定（{{ $space->name }}）</span>
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($spaces->isEmpty())
                    <p class="sfk-empty">{{ $spaceScope === 'all' ? 'スペースはありません。' : '参加中のスペースはありません。' }}</p>
                @endif
            </nav>

            <div class="sfk-apps-main">
                {{-- よく使うアプリ: the five apps this user opens most --}}
                @if ($mostUsed->isNotEmpty())
                    <section class="sfk-apps-section" aria-labelledby="sfk-most-used-title" x-show="! q">
                        <h3 id="sfk-most-used-title">よく使うアプリ</h3>
                        <div class="sfk-shortcuts">
                            @foreach ($mostUsed as $app)
                                <a href="{{ $appUrls[$app->id] }}" @class([
                                    'sfk-shortcut',
                                    'sfk-unpublished' =>
                                        $app->status !== \Modules\Core\Domain\Apps\AppStatus::Published,
                                ])>
                                    <span class="sfk-shortcut-tile" aria-hidden="true">{{ $app->iconText() }}</span>
                                    <span>{{ $app->displayName() }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- アプリ: all, or those of the chosen space, in the user's own order (drag ⠿ to change it, theirs only;
                     administrators' drag changes the default order of everyone); the search narrows them as you type --}}
                <section class="sfk-apps-section" aria-labelledby="sfk-app-title">
                    <h3 id="sfk-app-title">
                        アプリ <small>{{ $apps->count() }}件</small>
                        @if ($hasOwnOrder)
                            <span class="sfk-apps-order-tools" x-show="q === ''">
                                <button type="button" class="sfk-apps-order-btn" wire:click="resetMyAppOrder"
                                    wire:loading.attr="disabled">
                                    <x-filament::icon icon="heroicon-m-arrow-uturn-left" />既定の並びに戻す
                                </button>
                            </span>
                        @endif
                    </h3>
                    @if ($apps->isEmpty())
                        <p class="sfk-apps-none">利用できるアプリはありません。</p>
                    @else
                        <ul class="sfk-apps-grid" x-sortable data-sortable-animation-duration="150"
                            x-on:end.stop="$wire.saveMyAppOrder($event.target.sortable.toArray())">
                            @foreach ($apps as $app)
                                @php($url = $appUrls[$app->id] ?? null)
                                @php($published = $app->status === \Modules\Core\Domain\Apps\AppStatus::Published)
                                <li wire:key="app-{{ $app->id }}" x-sortable-item="{{ $app->id }}"
                                    class="sfk-apps-tile"
                                    data-name="{{ mb_strtolower($app->name . ' ' . $app->iconText()) }}"
                                    x-bind:hidden="q !== '' && !$el.dataset.name.includes(q.trim().toLowerCase())">
                                    @php($handleTitle = $isAdministrator ? 'ドラッグして既定の並びを変更' : 'ドラッグして並び替え')
                                    <button type="button" class="sfk-apps-handle" x-sortable-handle x-show="q === ''"
                                        title="{{ $handleTitle }}">
                                        <x-filament::icon icon="heroicon-m-bars-3" />
                                        <span class="fi-sr-only">{{ $handleTitle }}</span>
                                    </button>
                                    @if ($url !== null)
                                        <a href="{{ $url }}" @class(['sfk-row', 'sfk-unpublished' => !$published])>
                                            <span class="sfk-app-icon"
                                                aria-hidden="true">{{ $app->iconText() }}</span>
                                            <span class="sfk-row-name">{{ $app->name }}</span>
                                            @unless ($published)
                                                <span class="sfk-row-muted">（{{ $app->status->label() }}）</span>
                                            @endunless
                                        </a>
                                    @else
                                        <div @class(['sfk-row', 'sfk-unpublished' => !$published])>
                                            <span class="sfk-app-icon"
                                                aria-hidden="true">{{ $app->iconText() }}</span>
                                            <span class="sfk-row-name">{{ $app->name }}</span>
                                            <span
                                                class="sfk-row-muted">（{{ $published ? '画面準備中' : $app->status->label() }}）</span>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <p class="sfk-apps-none"
                            x-show="q !== '' && ! [...$root.querySelectorAll('.sfk-apps-grid li')].some((li) => ! li.hidden)"
                            x-cloak>「<span x-text="q"></span>」に一致するアプリはありません。</p>
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
