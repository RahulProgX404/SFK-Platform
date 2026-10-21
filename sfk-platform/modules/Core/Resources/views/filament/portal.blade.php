<x-filament-panels::page>
    <style>
        /* legacy portal look (colours from core::filament.theme) */
        .sfk-portal { display: flex; flex-direction: column; gap: 1rem; color: #333; }
        .sfk-portal-grid { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: 1rem; align-items: start; }
        @media (max-width: 1024px) { .sfk-portal-grid { grid-template-columns: 1fr; } }
        .sfk-portal-side { display: flex; flex-direction: column; gap: 1rem; }

        .sfk-panel { background: #fff; border: 1px solid var(--kt-border, #e3e7e8); }
        .sfk-panel-head { display: flex; align-items: center; gap: 1rem; height: 3.5rem; border-bottom: 1px solid var(--kt-border, #e3e7e8); }
        .sfk-panel-icon { display: grid; place-items: center; width: 3.5rem; height: 3.5rem; flex-shrink: 0; color: #fff; }
        .sfk-panel-icon svg { width: 1.75rem; height: 1.75rem; }
        .sfk-panel-icon.is-notice { background: #e74c3c; }
        .sfk-panel-icon.is-space { background: #8cc63f; }
        .sfk-panel-icon.is-app { background: #5b9bd5; }
        .sfk-panel-title { flex: 1; margin: 0; font-size: 1.25rem; font-weight: 400; color: #555; }
        .sfk-panel-tools { display: flex; align-items: center; gap: .25rem; padding-right: .75rem; }
        .sfk-add { display: grid; place-items: center; width: 2rem; height: 2rem; background: #f1f3f4; color: #888; }
        .sfk-add:hover { background: var(--kt-blue-soft, #ebf5fb); color: var(--kt-blue, #3498db); }
        .sfk-add svg { width: 1.25rem; height: 1.25rem; }

        .sfk-panel-filter { padding: .375rem 1rem; border-bottom: 1px solid var(--kt-border, #e3e7e8); }
        .sfk-panel-filter select { width: 100%; padding: .25rem 0; font-size: .8125rem; color: var(--kt-blue, #3498db); background: transparent; border: 0; cursor: pointer; }
        .sfk-panel-filter select:focus { outline: 2px solid var(--kt-blue, #3498db); outline-offset: 2px; }

        .sfk-list { margin: 0; padding: 0; list-style: none; }
        .sfk-list li:nth-child(even) .sfk-row, .sfk-list li.sfk-space-item:nth-child(even) { background: #f5f6f7; }
        /* スペース row: the name (filters the apps) and ⚙ to the space's settings (administrators) */
        .sfk-space-item { display: flex; align-items: center; }
        .sfk-space-item .sfk-row { flex: 1; min-width: 0; }
        .sfk-space-settings { display: grid; place-items: center; width: 2.25rem; height: 2.25rem; margin-right: .5rem; flex-shrink: 0; color: #999; }
        .sfk-space-settings:hover { background: var(--kt-blue-soft, #e2f2fe); color: var(--kt-blue, #3498db); }
        .sfk-space-settings:focus-visible { outline: 2px solid var(--kt-blue, #3498db); outline-offset: -2px; }
        .sfk-space-settings svg { width: 1.25rem; height: 1.25rem; }
        .sfk-row { display: flex; align-items: center; gap: .75rem; width: 100%; min-height: 3rem; padding: .5rem 1rem; font-size: .875rem; text-align: left; color: #333; text-decoration: none; }
        a.sfk-row:hover, button.sfk-row:hover { background: var(--kt-blue-soft, #ebf5fb) !important; color: var(--kt-blue, #3498db); }
        .sfk-row.is-selected { box-shadow: inset 3px 0 0 var(--kt-blue, #3498db); }
        .sfk-row-muted { color: var(--kt-muted, #888); font-size: .75rem; }
        .sfk-space-icon { width: 2rem; height: 2rem; flex-shrink: 0; background: linear-gradient(135deg, #a8d65a, #6fae2d); }
        .sfk-app-icon { display: grid; place-items: center; width: 2rem; height: 2rem; flex-shrink: 0; overflow: hidden; background: #5b9bd5; color: #fff; font-size: .5625rem; font-weight: 700; line-height: 1; letter-spacing: -.02em; }
        .sfk-lock { width: .75rem; height: .75rem; color: #999; flex-shrink: 0; }
        .sfk-empty { padding: 1rem; font-size: .8125rem; color: var(--kt-muted, #888); }
        /* Apps that are not 公開中 (shown to super admins): dimmed */
        .sfk-unpublished { opacity: .45; }
        .sfk-unpublished:hover { opacity: .8; }

        /* アプリの並び替え (⚙ of アプリ, administrators) */
        .sfk-app-order { margin: 0; padding: 0; list-style: none; }
        .sfk-app-order-row { display: flex; align-items: center; gap: .625rem; min-height: 3rem; padding: 0 .75rem 0 .25rem; margin-top: -1px; background: #fff; border: 1px solid var(--kt-border, #e3e7e8); font-size: .875rem; }
        .sfk-app-order-row:hover { background: var(--kt-blue-soft, #ebf5fb); }
        .sfk-app-order-row.fi-sortable-ghost { opacity: .4; }
        .sfk-app-order-handle { display: inline-flex; align-items: center; justify-content: center; width: 1.75rem; height: 2.25rem; color: #bbb; cursor: grab; }
        .sfk-app-order-handle:hover { color: var(--kt-blue, #3498db); }
        .sfk-app-order-handle-icon { width: 1.125rem; height: 1.125rem; }
        .sfk-app-order-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .sfk-notice-body { padding: 1.25rem 1.5rem; }
        .sfk-notice-content { font-size: .9375rem; line-height: 1.8; }
        .sfk-notice-content h2 { font-size: 1.5rem; font-weight: 700; margin: .25rem 0 .75rem; }
        .sfk-notice-content h3 { font-size: 1.125rem; font-weight: 700; margin: .75rem 0 .5rem; }
        .sfk-notice-content p { margin: 0; }
        .sfk-notice-content ul { list-style: disc; padding-left: 1.5rem; }
        .sfk-notice-content ol { list-style: decimal; padding-left: 1.5rem; }
        .sfk-notice-content a { color: var(--kt-blue, #3498db); text-decoration: underline; }
        .sfk-notice-content mark { padding: 0 .125rem; }
        /* 文字色 of the editor: <span class="color" style="--color: …"> (Filament only colours it inside .fi-prose) */
        .sfk-notice-content .color { color: var(--color); }
        .sfk-notice-content table { border-collapse: collapse; margin: .5rem 0; }
        .sfk-notice-content td, .sfk-notice-content th { border: 1px solid var(--kt-border, #e3e7e8); padding: .25rem .5rem; }

        .sfk-section-title { display: flex; align-items: center; gap: .5rem; margin: 2rem 0 .75rem; padding-left: .625rem; font-size: 1rem; font-weight: 700; border-left: 5px solid #f39c12; }
        .sfk-shortcuts { display: grid; grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr)); max-width: 56rem; border-top: 1px solid #555; border-left: 1px solid #555; }
        .sfk-shortcut { display: flex; flex-direction: column; align-items: center; gap: .625rem; padding: .875rem .5rem 1.25rem; border-right: 1px solid #555; border-bottom: 1px solid #555; color: var(--kt-blue, #3498db); text-decoration: none; font-size: .8125rem; font-weight: 700; }
        .sfk-shortcut-tile { display: grid; place-items: center; width: 6.25rem; height: 6.25rem; background: #4ea6dc; color: #fff; font-size: 1.875rem; font-weight: 700; }
        a.sfk-shortcut:hover .sfk-shortcut-tile { background: #3498db; }
        .sfk-shortcut.is-disabled { color: var(--kt-muted, #888); }
        .sfk-shortcut.is-disabled .sfk-shortcut-tile { background: #b8c4cc; }
    </style>

    <div class="sfk-portal">
        <div class="sfk-portal-grid">
            {{-- お知らせ --}}
            <section class="sfk-panel" aria-labelledby="sfk-notice-title">
                <header class="sfk-panel-head">
                    <span class="sfk-panel-icon is-notice" aria-hidden="true"><x-filament::icon icon="heroicon-o-megaphone" /></span>
                    <h2 id="sfk-notice-title" class="sfk-panel-title">お知らせ</h2>
                    <div class="sfk-panel-tools">{{ $this->editNoticeAction }}</div>
                </header>

                <div class="sfk-notice-body">
                    @if ($notice !== null)
                        <div class="sfk-notice-content">{!! $notice !!}</div>
                    @else
                        <p class="sfk-row-muted">お知らせはありません。</p>
                    @endif

                    @if ($shortcuts->isNotEmpty() || $isAdministrator)
                        <h3 class="sfk-section-title">
                            アプリショートカット
                            {{ $this->editShortcutsAction }}
                        </h3>

                        @if ($shortcuts->isEmpty())
                            <p class="sfk-row-muted">ショートカットはありません。</p>
                        @else
                            <div class="sfk-shortcuts">
                                @foreach ($shortcuts as $app)
                                    @php($url = $appUrls[$app->id] ?? null)
                                    @if ($url !== null)
                                        <a href="{{ $url }}" @class(['sfk-shortcut', 'sfk-unpublished' => $app->status !== \Modules\Core\Domain\Apps\AppStatus::Published])>
                                            <span>{{ $app->displayName() }}</span>
                                            <span class="sfk-shortcut-tile" aria-hidden="true">{{ $app->iconText() }}</span>
                                        </a>
                                    @else
                                        <div class="sfk-shortcut is-disabled" title="画面は準備中です">
                                            <span>{{ $app->displayName() }}</span>
                                            <span class="sfk-shortcut-tile" aria-hidden="true">{{ $app->iconText() }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
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
                            <span class="sfk-row-muted" style="color: var(--kt-blue, #3498db)">参加中のスペース</span>
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
                                {{ $this->reorderAppsAction }}
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
