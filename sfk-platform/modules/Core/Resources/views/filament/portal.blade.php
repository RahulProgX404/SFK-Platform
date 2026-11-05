<x-filament-panels::page>
    <style>
        /* the dashboard home: widgets as white cards (tokens from core::filament.design) */
        .sfk-portal { display: flex; flex-direction: column; gap: var(--sfk-space-5); color: var(--sfk-text); }
        .sfk-portal-grid { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: var(--sfk-space-5); align-items: start; }
        @media (max-width: 1024px) { .sfk-portal-grid { grid-template-columns: 1fr; } }
        .sfk-portal-side { display: flex; flex-direction: column; gap: var(--sfk-space-5); }

        .sfk-panel { overflow: hidden; background: var(--sfk-surface); border-radius: var(--sfk-radius-lg); box-shadow: var(--sfk-ring), var(--sfk-shadow); }
        .sfk-panel-head { display: flex; align-items: center; gap: var(--sfk-space-3); min-height: 3.5rem; padding: 0 var(--sfk-space-3) 0 var(--sfk-space-4); border-bottom: 1px solid var(--sfk-line); }
        .sfk-panel-icon { display: grid; place-items: center; width: 2.25rem; height: 2.25rem; flex-shrink: 0; border-radius: var(--sfk-radius); color: #fff; }
        .sfk-panel-icon svg { width: 1.25rem; height: 1.25rem; }
        .sfk-panel-icon.is-shortcut { background: var(--sfk-primary); }
        .sfk-panel-icon.is-space { background: #16956b; }
        .sfk-panel-icon.is-app { background: #3c78b4; }
        .sfk-panel-title { flex: 1; margin: 0; color: var(--sfk-ink); font-size: var(--sfk-fs-subheading); font-weight: var(--sfk-fw-bold); font-feature-settings: "palt"; letter-spacing: var(--sfk-tracking-heading); }
        .sfk-panel-tools { display: flex; align-items: center; gap: var(--sfk-space-1); }
        .sfk-add { display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: var(--sfk-radius); color: var(--sfk-muted); }
        .sfk-add:hover { background: var(--sfk-primary-soft); color: var(--sfk-primary); }
        .sfk-add svg { width: 1.25rem; height: 1.25rem; }

        .sfk-panel-filter { padding: var(--sfk-space-2) var(--sfk-space-4); background: var(--sfk-head); border-bottom: 1px solid var(--sfk-line); }
        .sfk-panel-filter select { width: 100%; padding: .25rem 0; color: var(--sfk-primary); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-medium); background: transparent; border: 0; cursor: pointer; }
        .sfk-panel-filter select:focus { outline: 2px solid var(--sfk-primary); outline-offset: 2px; }

        .sfk-list { margin: 0; padding: var(--sfk-space-1) 0; list-style: none; }
        .sfk-list li + li { border-top: 1px solid var(--sfk-line-soft); }
        /* スペース row: the name (filters the apps) and ⚙ to the space's settings (administrators) */
        .sfk-space-item { display: flex; align-items: center; }
        .sfk-space-item .sfk-row { flex: 1; min-width: 0; }
        .sfk-space-settings { display: grid; place-items: center; width: 2rem; height: 2rem; margin-right: var(--sfk-space-3); flex-shrink: 0; border-radius: var(--sfk-radius); color: var(--sfk-subtle); }
        .sfk-space-settings:hover { background: var(--sfk-primary-soft); color: var(--sfk-primary); }
        .sfk-space-settings:focus-visible { outline: 2px solid var(--sfk-primary); outline-offset: -2px; }
        .sfk-space-settings svg { width: 1.125rem; height: 1.125rem; }
        .sfk-row { display: flex; align-items: center; gap: var(--sfk-space-3); width: 100%; min-height: 2.75rem; padding: var(--sfk-space-2) var(--sfk-space-4); color: var(--sfk-text); font-size: var(--sfk-fs-label); text-align: left; text-decoration: none; }
        a.sfk-row:hover, button.sfk-row:hover { background: var(--sfk-hover) !important; color: var(--sfk-primary); }
        .sfk-row.is-selected { background: var(--sfk-primary-soft); box-shadow: inset 3px 0 0 var(--sfk-primary); color: var(--sfk-primary-dark); }
        .sfk-row-muted { color: var(--sfk-muted); font-size: var(--sfk-fs-small); }
        .sfk-space-icon { width: 1.75rem; height: 1.75rem; flex-shrink: 0; border-radius: var(--sfk-radius); background: linear-gradient(135deg, #3fbf8c, #16956b); }
        .sfk-app-icon { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; flex-shrink: 0; overflow: hidden; border-radius: var(--sfk-radius); background: #3c78b4; color: #fff; font-size: .625rem; font-weight: var(--sfk-fw-bold); line-height: 1; letter-spacing: -.02em; }
        .sfk-lock { width: .75rem; height: .75rem; color: var(--sfk-subtle); flex-shrink: 0; }
        .sfk-empty { padding: var(--sfk-space-4); color: var(--sfk-muted); font-size: var(--sfk-fs-label); }
        /* Apps that are not 公開中 (shown to super admins): dimmed */
        .sfk-unpublished { opacity: .45; }
        .sfk-unpublished:hover { opacity: .8; }

        /* アプリの並び替え (⚙ of アプリ, administrators) */
        .sfk-app-order { margin: 0; padding: 0; list-style: none; overflow: hidden; border-radius: var(--sfk-radius-lg); box-shadow: var(--sfk-ring); }
        .sfk-app-order-row { display: flex; align-items: center; gap: .625rem; min-height: 2.75rem; padding: 0 .75rem 0 .25rem; background: var(--sfk-surface); border-top: 1px solid var(--sfk-line-soft); font-size: var(--sfk-fs-label); }
        .sfk-app-order-row:first-child { border-top: 0; }
        .sfk-app-order-row:hover { background: var(--sfk-hover); }
        .sfk-app-order-row.fi-sortable-ghost { opacity: .4; }
        .sfk-app-order-handle { display: inline-flex; align-items: center; justify-content: center; width: 1.75rem; height: 2.25rem; color: var(--sfk-subtle); cursor: grab; }
        .sfk-app-order-handle:hover { color: var(--sfk-primary); }
        .sfk-app-order-handle-icon { width: 1.125rem; height: 1.125rem; }
        .sfk-app-order-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* each shortcut its own card, with space between them */
        .sfk-shortcut-body { padding: var(--sfk-space-5) var(--sfk-space-6); }
        .sfk-shortcuts { display: flex; flex-wrap: wrap; gap: var(--sfk-space-3); max-width: 56rem; }
        .sfk-shortcut { display: flex; flex-direction: column; align-items: center; gap: var(--sfk-space-3); width: 9rem; padding: var(--sfk-space-4) var(--sfk-space-2); border-radius: var(--sfk-radius-lg); box-shadow: var(--sfk-ring); color: var(--sfk-ink); font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-semibold); text-decoration: none; transition: box-shadow .15s, transform .15s; }
        a.sfk-shortcut:hover { box-shadow: 0 0 0 1px var(--sfk-primary-soft-strong), var(--sfk-shadow-md); color: var(--sfk-primary); }
        .sfk-shortcut-tile { display: grid; place-items: center; width: 3.75rem; height: 3.75rem; border-radius: var(--sfk-radius-xl); background: linear-gradient(135deg, #2b6aa0, var(--sfk-primary)); color: #fff; font-size: 1.0625rem; font-weight: var(--sfk-fw-bold); }
        a.sfk-shortcut:hover .sfk-shortcut-tile { background: var(--sfk-primary-dark); }
        .sfk-shortcut.is-disabled { color: var(--sfk-muted); }
        .sfk-shortcut.is-disabled .sfk-shortcut-tile { background: #b8c4cc; }
    </style>

    <div class="sfk-portal">
        <div class="sfk-portal-grid">
            {{-- アプリショートカット (the お知らせ is on the dashboard selection, /portal) --}}
            <section class="sfk-panel" aria-labelledby="sfk-shortcut-title">
                <header class="sfk-panel-head">
                    <span class="sfk-panel-icon is-shortcut" aria-hidden="true"><x-filament::icon icon="heroicon-o-squares-2x2" /></span>
                    <h2 id="sfk-shortcut-title" class="sfk-panel-title">アプリショートカット</h2>
                    <div class="sfk-panel-tools">
                        @if ($this->editShortcutsAction->isVisible())
                            {{ $this->editShortcutsAction }}
                        @endif
                    </div>
                </header>

                <div class="sfk-shortcut-body">
                    @if ($shortcuts->isEmpty())
                        <p class="sfk-row-muted">ショートカットはありません。</p>
                    @else
                        <div class="sfk-shortcuts">
                            @foreach ($shortcuts as $app)
                                @php($url = $shortcutUrls[$app->id] ?? null)
                                @if ($url !== null)
                                    <a href="{{ $url }}" @class(['sfk-shortcut', 'sfk-unpublished' => $app->status !== \Modules\Core\Domain\Apps\AppStatus::Published])>
                                        <span class="sfk-shortcut-tile" aria-hidden="true">{{ $app->iconText() }}</span>
                                        <span>{{ $app->displayName() }}</span>
                                    </a>
                                @else
                                    <div class="sfk-shortcut is-disabled" title="{{ array_key_exists($app->id, $shortcutUrls) ? '画面は準備中です' : 'このアプリを開く権限がありません' }}">
                                        <span class="sfk-shortcut-tile" aria-hidden="true">{{ $app->iconText() }}</span>
                                        <span>{{ $app->displayName() }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <div class="sfk-portal-side">
                {{-- スペース --}}
                <section class="sfk-panel" aria-labelledby="sfk-space-title">
                    <header class="sfk-panel-head">
                        <span class="sfk-panel-icon is-space" aria-hidden="true"><x-filament::icon icon="heroicon-o-chat-bubble-left-ellipsis" /></span>
                        <h2 id="sfk-space-title" class="sfk-panel-title">スペース</h2>
                        @if ($spacesUrl !== null)
                            <div class="sfk-panel-tools">
                                <a href="{{ $spacesUrl }}" class="sfk-add" title="スペースを作成・管理" aria-label="スペースを作成・管理">
                                    <x-filament::icon icon="heroicon-m-plus" />
                                </a>
                            </div>
                        @endif
                    </header>

                    <div class="sfk-panel-filter">
                        @if ($isAdministrator)
                            <select wire:model.live="spaceScope" aria-label="表示するスペース">
                                <option value="joined">参加中のスペース</option>
                                <option value="all">すべてのスペース</option>
                            </select>
                        @else
                            <span class="sfk-row-muted" style="color: var(--kt-blue, #05467E)">参加中のスペース</span>
                        @endif
                    </div>

                    @if ($spaces->isEmpty())
                        <p class="sfk-empty">{{ $spaceScope === 'all' ? 'スペースはありません。' : '参加中のスペースはありません。' }}</p>
                    @else
                        <ul class="sfk-list">
                            @foreach ($spaces as $space)
                                <li wire:key="space-{{ $space->id }}" class="sfk-space-item">
                                    <button type="button" wire:click="showSpace({{ $space->id }})" @class(['sfk-row', 'is-selected' => $appSpace === $space->id])>
                                        <span class="sfk-space-icon" aria-hidden="true"></span>
                                        <x-filament::icon icon="heroicon-s-lock-closed" class="sfk-lock" />
                                        <span>{{ $space->name }}</span>
                                    </button>
                                    @if (isset($spaceSettingsUrls[$space->id]))
                                        <a href="{{ $spaceSettingsUrls[$space->id] }}" class="sfk-space-settings" title="スペースの設定（{{ $space->name }}）">
                                            <x-filament::icon icon="heroicon-o-cog-6-tooth" />
                                            <span class="fi-sr-only">スペースの設定（{{ $space->name }}）</span>
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                {{-- アプリ --}}
                <section class="sfk-panel" aria-labelledby="sfk-app-title">
                    <header class="sfk-panel-head">
                        <span class="sfk-panel-icon is-app" aria-hidden="true"><x-filament::icon icon="heroicon-o-list-bullet" /></span>
                        <h2 id="sfk-app-title" class="sfk-panel-title">アプリ</h2>
                        @if ($appsSettingsUrl !== null || $isAdministrator)
                            <div class="sfk-panel-tools">
                                @if ($appsSettingsUrl !== null)
                                    <a href="{{ $appsSettingsUrl }}" class="sfk-add" title="アプリ設定" aria-label="アプリ設定">
                                        <x-filament::icon icon="heroicon-m-plus" />
                                    </a>
                                @endif
                                @if ($this->reorderAppsAction->isVisible())
                                    {{ $this->reorderAppsAction }}
                                @endif
                            </div>
                        @endif
                    </header>

                    <div class="sfk-panel-filter">
                        <select wire:model.live="appSpace" aria-label="表示するアプリ">
                            <option value="">すべてのアプリ</option>
                            @foreach ($filterSpaces as $space)
                                <option value="{{ $space->id }}">{{ $space->name }}のアプリ</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($apps->isEmpty())
                        <p class="sfk-empty">利用できるアプリはありません。</p>
                    @else
                        <ul class="sfk-list">
                            @foreach ($apps as $app)
                                @php($url = $appUrls[$app->id] ?? null)
                                @php($published = $app->status === \Modules\Core\Domain\Apps\AppStatus::Published)
                                <li wire:key="app-{{ $app->id }}">
                                    @if ($url !== null)
                                        <a href="{{ $url }}" @class(['sfk-row', 'sfk-unpublished' => ! $published])>
                                            <span class="sfk-app-icon" aria-hidden="true">{{ $app->iconText() }}</span>
                                            <span>{{ $app->name }}</span>
                                            @unless ($published)
                                                <span class="sfk-row-muted">（{{ $app->status->label() }}）</span>
                                            @endunless
                                        </a>
                                    @else
                                        <div @class(['sfk-row', 'sfk-unpublished' => ! $published])>
                                            <span class="sfk-app-icon" aria-hidden="true">{{ $app->iconText() }}</span>
                                            <span>{{ $app->name }}</span>
                                            <span class="sfk-row-muted">（{{ $published ? '画面準備中' : $app->status->label() }}）</span>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
