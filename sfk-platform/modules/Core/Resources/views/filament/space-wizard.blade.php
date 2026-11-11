{{--
    スペースを作成 / 編集 (Modules\Core\Filament\DashboardSettings\Resources\Spaces\SpaceWizard), made for the computer: a window
    over the screen with the step bar on top (スペース名 → メンバー → アプリ → 確認; steps reached once can be clicked), the
    choices in columns with search and 表示中をすべて選択 / 選択を解除, what is chosen in the panel at the right (× takes it
    out), キャンセル at the left and 戻る / 次へ (保存) at the right of the footer. Phones get the window over the whole
    screen with the panel under the choices. Employees who are members through a chosen 従業員カテゴリ stay ticked.
--}}
@php
    use Modules\Core\Filament\DashboardSettings\Resources\Spaces\SpaceWizard;

    $id = 'sfk-space-wizard';
    $isStep = in_array($screen, SpaceWizard::STEPS, true);
    $picking = in_array($screen, ['members', 'apps'], true);
    $categoryOptions = $picking ? $this->categoryOptions() : [];
    $employees = $picking ? $this->employees() : [];
    $appOptions = $picking ? $this->appOptions() : [];
    $covered = $screen === 'members' ? $this->coveredUsers() : [];
    $chosenCategories = array_flip($categories);
    $chosenUsers = array_flip($users);
    $chosenApps = array_flip($apps);
@endphp

<div x-data="{ shown: $wire.entangle('open') }"
    x-init="$watch('shown', (open) => open && setTimeout(() => $root.querySelector('.sfk-sw-main [data-autofocus]')?.focus({ preventScroll: true }), 50))"
    x-on:keydown.escape.window="if (shown) { shown = false; $wire.close() }"
    x-effect="document.documentElement.style.overflow = shown ? 'hidden' : ''">
    <style>
        .sfk-sw-backdrop { position: fixed; inset: 0; z-index: 45; display: flex; align-items: center; justify-content: center; padding: var(--sfk-space-6); background: rgba(15, 23, 42, .45); }
        .sfk-sw { display: flex; flex-direction: column; width: min(1120px, 100%); height: min(780px, calc(100vh - 48px)); height: min(780px, calc(100dvh - 48px)); overflow: clip; border-radius: var(--sfk-radius-xl); background: var(--sfk-surface); box-shadow: var(--sfk-shadow-lg); color: var(--sfk-text); font-size: var(--sfk-fs-body); }
        .sfk-sw-head { display: flex; align-items: center; gap: var(--sfk-space-6); padding: var(--sfk-space-4) var(--sfk-space-6); border-bottom: 1px solid var(--sfk-line); }
        .sfk-sw-title { flex: none; margin: 0; color: var(--sfk-ink); font-size: var(--sfk-fs-title); font-weight: var(--sfk-fw-bold); }
        .sfk-sw-steps { display: flex; flex: 1; align-items: center; gap: var(--sfk-space-2); min-width: 0; margin: 0; padding: 0; list-style: none; }
        .sfk-sw-steps li { display: flex; align-items: center; gap: var(--sfk-space-2); min-width: 0; }
        .sfk-sw-steps li + li::before { content: ""; width: 32px; height: 1px; background: var(--sfk-line-strong); }
        .sfk-sw-step { display: inline-flex; align-items: center; gap: var(--sfk-space-2); height: var(--sfk-control-sm, 32px); padding: 0 var(--sfk-space-2); border: 0; border-radius: var(--sfk-radius); background: transparent; color: var(--sfk-muted); font-size: var(--sfk-fs-label); white-space: nowrap; }
        .sfk-sw-step:not(:disabled) { cursor: pointer; }
        .sfk-sw-step:not(:disabled):hover { background: var(--sfk-hover); color: var(--sfk-ink); }
        .sfk-sw-step i { display: grid; place-items: center; width: 22px; height: 22px; border: 1px solid var(--sfk-line-strong); border-radius: 999px; font-size: var(--sfk-fs-badge); font-style: normal; font-weight: var(--sfk-fw-semibold); }
        .sfk-sw-step i .fi-icon { width: 14px; height: 14px; }
        .sfk-sw-step.is-current { color: var(--sfk-ink); font-weight: var(--sfk-fw-semibold); }
        .sfk-sw-step.is-current i { border-color: var(--sfk-primary); background: var(--sfk-primary); color: #fff; }
        .sfk-sw-step.is-done i { border-color: var(--sfk-primary); color: var(--sfk-primary); }
        .sfk-sw-close { display: grid; flex: none; place-items: center; width: var(--sfk-control); height: var(--sfk-control); margin-left: auto; border: 0; border-radius: var(--sfk-radius); background: transparent; color: var(--sfk-muted); cursor: pointer; }
        .sfk-sw-close:hover { background: var(--sfk-hover); color: var(--sfk-ink); }
        .sfk-sw-close .fi-icon { width: 20px; height: 20px; }

        .sfk-sw-form { display: flex; flex: 1; flex-direction: column; min-height: 0; margin: 0; }
        .sfk-sw-body { display: grid; flex: 1; grid-template-columns: minmax(0, 1fr); min-height: 0; }
        .sfk-sw-body.has-aside { grid-template-columns: minmax(0, 1fr) 300px; }
        .sfk-sw-main { min-height: 0; overflow-y: auto; padding: var(--sfk-space-5) var(--sfk-space-6) var(--sfk-space-6); }
        .sfk-sw-aside { display: flex; flex-direction: column; gap: var(--sfk-space-4); min-height: 0; overflow-y: auto; padding: var(--sfk-space-5); border-left: 1px solid var(--sfk-line); background: var(--sfk-page); }

        .sfk-sw-label { display: block; margin: 0 0 var(--sfk-space-2); color: var(--kt-label, var(--sfk-ink)); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); }
        .sfk-sw-input { box-sizing: border-box; width: 100%; height: var(--sfk-control); padding: 0 var(--sfk-space-3); border: 1px solid var(--sfk-line-strong); border-radius: var(--sfk-radius); background: var(--sfk-surface); color: var(--sfk-ink); font-size: var(--sfk-fs-body); }
        .sfk-sw-input:focus { border-color: var(--sfk-primary); outline: 2px solid var(--sfk-primary-soft-strong, var(--sfk-primary-soft)); outline-offset: 0; }
        .sfk-sw-input.is-invalid { border-color: var(--sfk-danger); }
        .sfk-sw-name { max-width: 520px; }
        .sfk-sw-error { margin: var(--sfk-space-2) 0 0; color: var(--sfk-danger-text, var(--sfk-danger)); font-size: var(--sfk-fs-small); }

        .sfk-sw-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: var(--sfk-space-3); }
        .sfk-sw-toolbar .sfk-sw-input { width: 320px; max-width: 100%; }
        .sfk-sw-count { color: var(--sfk-primary); font-weight: var(--sfk-fw-semibold); }
        .sfk-sw-section { display: flex; align-items: center; justify-content: space-between; gap: var(--sfk-space-3); margin: var(--sfk-space-5) 0 var(--sfk-space-2); }
        .sfk-sw-section h3 { margin: 0; color: var(--kt-label, var(--sfk-ink)); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); }
        .sfk-sw-links { display: flex; gap: var(--sfk-space-1); }
        .sfk-sw-link { height: 28px; padding: 0 var(--sfk-space-2); border: 0; border-radius: var(--sfk-radius); background: transparent; color: var(--sfk-primary); font-size: var(--sfk-fs-small); cursor: pointer; }
        .sfk-sw-link:hover { background: var(--sfk-primary-soft); }

        .sfk-sw-chips { display: flex; flex-wrap: wrap; gap: var(--sfk-space-2); margin: 0; padding: 0; list-style: none; }
        .sfk-sw-chip { display: inline-flex; align-items: center; gap: var(--sfk-space-2); height: var(--sfk-control-sm, 32px); padding: 0 var(--sfk-space-3); border: 1px solid var(--sfk-line-strong); border-radius: 999px; background: var(--sfk-surface); color: var(--sfk-ink); font-size: var(--sfk-fs-body); cursor: pointer; }
        .sfk-sw-chip small { color: var(--sfk-muted); font-size: var(--sfk-fs-small); }
        .sfk-sw-chip:hover { border-color: var(--sfk-primary); }
        .sfk-sw-chip.is-selected { border-color: var(--sfk-primary); background: var(--sfk-primary); color: #fff; }
        .sfk-sw-chip.is-selected small { color: rgba(255, 255, 255, .8); }

        .sfk-sw-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: var(--sfk-space-2); margin: 0; padding: 0; list-style: none; }
        .sfk-sw-item { display: flex; align-items: center; gap: var(--sfk-space-3); box-sizing: border-box; width: 100%; min-height: 48px; padding: var(--sfk-space-2) var(--sfk-space-3); border: 1px solid var(--sfk-line); border-radius: var(--sfk-radius-lg); background: var(--sfk-surface); color: var(--sfk-ink); text-align: left; cursor: pointer; }
        .sfk-sw-item:hover { border-color: var(--sfk-primary); background: var(--sfk-hover); }
        .sfk-sw-item.is-selected { border-color: var(--sfk-primary); background: var(--sfk-primary-soft); }
        .sfk-sw-item:disabled { cursor: default; }
        .sfk-sw-item:disabled:hover { background: var(--sfk-primary-soft); }
        .sfk-sw-box { display: grid; flex: none; place-items: center; width: 18px; height: 18px; border: 1px solid var(--sfk-line-strong); border-radius: 4px; background: var(--sfk-surface); color: #fff; }
        .sfk-sw-box .fi-icon { width: 14px; height: 14px; }
        .sfk-sw-item.is-selected .sfk-sw-box { border-color: var(--sfk-primary); background: var(--sfk-primary); }
        .sfk-sw-item:disabled .sfk-sw-box { opacity: .55; }
        .sfk-sw-icon { display: grid; flex: none; place-items: center; width: 28px; height: 28px; border-radius: var(--sfk-radius); background: var(--sfk-primary-soft); color: var(--sfk-primary); font-size: var(--sfk-fs-badge); font-weight: var(--sfk-fw-semibold); }
        .sfk-sw-text { display: flex; flex-direction: column; min-width: 0; }
        .sfk-sw-text strong, .sfk-sw-text span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sfk-sw-text strong { font-weight: var(--sfk-fw-medium); }
        .sfk-sw-text span { color: var(--sfk-muted); font-size: var(--sfk-fs-small); }
        .sfk-sw-empty { margin: var(--sfk-space-2) 0 0; color: var(--sfk-muted); }

        .sfk-sw-aside h3 { display: flex; align-items: baseline; justify-content: space-between; margin: 0; color: var(--sfk-ink); font-size: var(--sfk-fs-heading); font-weight: var(--sfk-fw-semibold); }
        .sfk-sw-aside h4 { margin: 0 0 var(--sfk-space-2); color: var(--sfk-muted); font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-medium); }
        .sfk-sw-picked { display: flex; flex-direction: column; gap: var(--sfk-space-1); margin: 0; padding: 0; list-style: none; }
        .sfk-sw-picked li { display: flex; align-items: center; gap: var(--sfk-space-2); min-height: 32px; padding: 0 var(--sfk-space-1) 0 var(--sfk-space-3); border: 1px solid var(--sfk-line); border-radius: var(--sfk-radius); background: var(--sfk-surface); }
        .sfk-sw-picked span { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sfk-sw-picked small { color: var(--sfk-muted); font-size: var(--sfk-fs-small); white-space: nowrap; }
        .sfk-sw-remove { display: grid; flex: none; place-items: center; width: 26px; height: 26px; border: 0; border-radius: var(--sfk-radius); background: transparent; color: var(--sfk-muted); cursor: pointer; }
        .sfk-sw-remove:hover { background: var(--sfk-danger-soft); color: var(--sfk-danger); }
        .sfk-sw-remove .fi-icon { width: 16px; height: 16px; }

        .sfk-sw-review { margin: 0; border: 1px solid var(--sfk-line); border-radius: var(--sfk-radius-lg); }
        .sfk-sw-review > div { display: grid; grid-template-columns: 160px minmax(0, 1fr) auto; align-items: center; gap: var(--sfk-space-4); padding: var(--sfk-space-3) var(--sfk-space-4); }
        .sfk-sw-review > div + div { border-top: 1px solid var(--sfk-line); }
        .sfk-sw-review > div.is-invalid { background: var(--sfk-danger-soft); }
        .sfk-sw-review dt { color: var(--kt-label, var(--sfk-ink)); font-weight: var(--sfk-fw-semibold); }
        .sfk-sw-review dd { margin: 0; color: var(--sfk-ink); line-height: var(--sfk-lh-body, 1.6); overflow-wrap: anywhere; }

        .sfk-sw-foot { display: flex; align-items: center; justify-content: space-between; gap: var(--sfk-space-3); padding: var(--sfk-space-3) var(--sfk-space-6); border-top: 1px solid var(--sfk-line); background: var(--sfk-surface); }
        .sfk-sw-foot > div { display: flex; gap: var(--sfk-space-2); }
        .sfk-sw-btn { height: var(--sfk-control); padding: 0 var(--sfk-space-5); border: 1px solid var(--sfk-line-strong); border-radius: var(--sfk-radius); background: var(--sfk-surface); color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-medium); cursor: pointer; }
        .sfk-sw-btn:hover { background: var(--sfk-hover); }
        .sfk-sw-btn.is-primary { min-width: 120px; border-color: var(--sfk-primary); background: var(--sfk-primary); color: #fff; }
        .sfk-sw-btn.is-primary:hover { background: var(--sfk-primary-dark, var(--sfk-primary)); }
        .sfk-sw-btn:disabled { opacity: .6; cursor: wait; }

        @media (max-width: 767px) {
            .sfk-sw-backdrop { padding: 0; }
            .sfk-sw { width: 100%; height: 100vh; height: 100dvh; border-radius: 0; }
            .sfk-sw-head { flex-wrap: wrap; gap: var(--sfk-space-2); padding: var(--sfk-space-3) var(--sfk-space-4); }
            .sfk-sw-steps { order: 3; flex-basis: 100%; overflow-x: auto; }
            .sfk-sw-steps li + li::before { width: 12px; }
            .sfk-sw-body.has-aside { grid-template-columns: minmax(0, 1fr); grid-template-rows: minmax(0, 1fr) auto; }
            .sfk-sw-main { padding: var(--sfk-space-4); }
            .sfk-sw-aside { max-height: 35vh; border-top: 1px solid var(--sfk-line); border-left: 0; }
            .sfk-sw-review > div { grid-template-columns: minmax(0, 1fr) auto; }
            .sfk-sw-review dd { grid-column: 1 / -1; grid-row: 2; }
            .sfk-sw-foot { padding: var(--sfk-space-3) var(--sfk-space-4); }
        }
    </style>

    <div class="sfk-sw-backdrop" x-show="shown" x-cloak x-transition.opacity.duration.150ms>
        <div class="sfk-sw" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
            <header class="sfk-sw-head">
                <h2 class="sfk-sw-title" id="{{ $id }}-title">{{ $this->heading() }}</h2>
                <ol class="sfk-sw-steps">
                    @foreach ($this->stepBar() as $step)
                        <li>
                            <button type="button" wire:click="jump('{{ $step['key'] }}')" @disabled(! $step['enabled'])
                                @if ($step['current']) aria-current="step" @endif
                                @class(['sfk-sw-step', 'is-current' => $step['current'], 'is-done' => $step['done']])>
                                <i aria-hidden="true">
                                    @if ($step['done'])
                                        <x-filament::icon icon="heroicon-m-check" />
                                    @else
                                        {{ $loop->iteration }}
                                    @endif
                                </i>
                                {{ $step['label'] }}
                            </button>
                        </li>
                    @endforeach
                </ol>
                <button type="button" class="sfk-sw-close" x-on:click="shown = false; $wire.close()" title="閉じる">
                    <x-filament::icon icon="heroicon-o-x-mark" />
                    <span class="fi-sr-only">閉じる</span>
                </button>
            </header>

            <form class="sfk-sw-form" wire:submit="submit" novalidate>
                <div @class(['sfk-sw-body', 'has-aside' => $picking])>
                    <div class="sfk-sw-main" wire:key="{{ $id }}-{{ $screen }}"
                        x-init="$nextTick(() => $el.querySelector('[data-autofocus]')?.focus({ preventScroll: true }))">
                        @switch($screen)
                            @case('name')
                                <div class="sfk-sw-name">
                                    <label class="sfk-sw-label" for="{{ $id }}-name">スペース名</label>
                                    <input id="{{ $id }}-name" type="text" autocomplete="off" maxlength="64" placeholder="例：営業スペース"
                                        @class(['sfk-sw-input', 'is-invalid' => $errors->has('name')]) wire:model="name" data-autofocus>
                                    @error('name')
                                        <p class="sfk-sw-error">{{ $message }}</p>
                                    @enderror
                                </div>
                                @break

                            @case('members')
                                @php
                                    $listedCategories = $this->listedCategories();
                                    $listedEmployees = $this->listedEmployees();
                                @endphp
                                <div class="sfk-sw-toolbar">
                                    <label class="fi-sr-only" for="{{ $id }}-search">検索</label>
                                    <input id="{{ $id }}-search" type="search" class="sfk-sw-input" wire:model.live.debounce.250ms="search"
                                        placeholder="名前・従業員カテゴリで検索" autocomplete="off" x-on:keydown.enter.prevent data-autofocus>
                                    <span>メンバー <span class="sfk-sw-count">{{ count($this->memberIds()) }}名</span></span>
                                </div>
                                @error('users')
                                    <p class="sfk-sw-error">{{ $message }}</p>
                                @enderror

                                @if ($listedCategories !== [])
                                    <div class="sfk-sw-section"><h3 id="{{ $id }}-categories">従業員カテゴリ</h3></div>
                                    <ul class="sfk-sw-chips" aria-labelledby="{{ $id }}-categories">
                                        @foreach ($listedCategories as $categoryId => $category)
                                            @php
                                                $selected = isset($chosenCategories[$categoryId]);
                                            @endphp
                                            <li wire:key="{{ $id }}-category-{{ $categoryId }}">
                                                <button type="button" wire:click="toggleCategory({{ $categoryId }})" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                                    @class(['sfk-sw-chip', 'is-selected' => $selected])>
                                                    {{ $category['name'] }}<small>{{ $category['count'] }}名</small>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                <div class="sfk-sw-section">
                                    <h3 id="{{ $id }}-employees">従業員</h3>
                                    @if ($listedEmployees !== [])
                                        <div class="sfk-sw-links">
                                            <button type="button" class="sfk-sw-link" wire:click="selectListed('users', true)">表示中をすべて選択</button>
                                            <button type="button" class="sfk-sw-link" wire:click="selectListed('users', false)">選択を解除</button>
                                        </div>
                                    @endif
                                </div>
                                <ul class="sfk-sw-grid" aria-labelledby="{{ $id }}-employees">
                                    @forelse ($listedEmployees as $personId => $person)
                                        @php
                                            $through = $covered[$personId] ?? null;
                                            $selected = $through !== null || isset($chosenUsers[$personId]);
                                        @endphp
                                        <li wire:key="{{ $id }}-user-{{ $personId }}">
                                            <button type="button" wire:click="toggleUser({{ $personId }})" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                                @disabled($through !== null) @class(['sfk-sw-item', 'is-selected' => $selected])>
                                                <span class="sfk-sw-box" aria-hidden="true">
                                                    @if ($selected)
                                                        <x-filament::icon icon="heroicon-m-check" />
                                                    @endif
                                                </span>
                                                <span class="sfk-sw-text">
                                                    <strong>{{ $person['name'] }}</strong>
                                                    @if ($person['categories'] !== [])
                                                        <span>{{ implode('・', $person['categories']) }}</span>
                                                    @endif
                                                </span>
                                            </button>
                                        </li>
                                    @empty
                                        <li><p class="sfk-sw-empty">該当なし</p></li>
                                    @endforelse
                                </ul>
                                @break

                            @case('apps')
                                @php
                                    $listedApps = $this->listedApps();
                                @endphp
                                <div class="sfk-sw-toolbar">
                                    <label class="fi-sr-only" for="{{ $id }}-search">検索</label>
                                    <input id="{{ $id }}-search" type="search" class="sfk-sw-input" wire:model.live.debounce.250ms="search"
                                        placeholder="アプリ名で検索" autocomplete="off" x-on:keydown.enter.prevent data-autofocus>
                                    <span>アプリ <span class="sfk-sw-count">{{ count(array_intersect_key($appOptions, $chosenApps)) }}件</span></span>
                                </div>
                                <div class="sfk-sw-section">
                                    <h3 id="{{ $id }}-apps">アプリ</h3>
                                    @if ($listedApps !== [])
                                        <div class="sfk-sw-links">
                                            <button type="button" class="sfk-sw-link" wire:click="selectListed('apps', true)">表示中をすべて選択</button>
                                            <button type="button" class="sfk-sw-link" wire:click="selectListed('apps', false)">選択を解除</button>
                                        </div>
                                    @endif
                                </div>
                                <ul class="sfk-sw-grid" aria-labelledby="{{ $id }}-apps">
                                    @forelse ($listedApps as $appId => $app)
                                        @php
                                            $selected = isset($chosenApps[$appId]);
                                        @endphp
                                        <li wire:key="{{ $id }}-app-{{ $appId }}">
                                            <button type="button" wire:click="toggleApp({{ $appId }})" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                                @class(['sfk-sw-item', 'is-selected' => $selected])>
                                                <span class="sfk-sw-box" aria-hidden="true">
                                                    @if ($selected)
                                                        <x-filament::icon icon="heroicon-m-check" />
                                                    @endif
                                                </span>
                                                <span class="sfk-sw-icon" aria-hidden="true">{{ $app['icon'] }}</span>
                                                <span class="sfk-sw-text"><strong>{{ $app['name'] }}</strong></span>
                                            </button>
                                        </li>
                                    @empty
                                        <li><p class="sfk-sw-empty">該当なし</p></li>
                                    @endforelse
                                </ul>
                                @break

                            @default
                                <dl class="sfk-sw-review">
                                    @foreach ($this->summary() as $row)
                                        <div @class(['is-invalid' => $row['invalid']])>
                                            <dt>{{ $row['label'] }}</dt>
                                            <dd>{{ $row['value'] !== '' ? $row['value'] : '-----' }}</dd>
                                            <button type="button" class="sfk-sw-link" wire:click="goTo('{{ $row['screen'] }}')" title="{{ $row['label'] }}を変更する">変更</button>
                                        </div>
                                    @endforeach
                                </dl>
                        @endswitch
                    </div>

                    @if ($picking)
                        {{-- what is chosen; × takes it out --}}
                        <aside class="sfk-sw-aside" aria-label="選択中">
                            @if ($screen === 'members')
                                <h3>選択中<span class="sfk-sw-count">{{ count($this->memberIds()) }}名</span></h3>
                                @if ($categories !== [])
                                    <div>
                                        <h4>従業員カテゴリ</h4>
                                        <ul class="sfk-sw-picked">
                                            @foreach (array_intersect_key($categoryOptions, $chosenCategories) as $categoryId => $category)
                                                <li wire:key="{{ $id }}-picked-category-{{ $categoryId }}">
                                                    <span>{{ $category['name'] }}</span><small>{{ $category['count'] }}名</small>
                                                    <button type="button" class="sfk-sw-remove" wire:click="toggleCategory({{ $categoryId }})" title="{{ $category['name'] }}を外す">
                                                        <x-filament::icon icon="heroicon-m-x-mark" /><span class="fi-sr-only">外す</span>
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                @php
                                    $byName = array_diff_key(array_intersect_key($employees, $chosenUsers), $covered);
                                @endphp
                                @if ($byName !== [])
                                    <div>
                                        <h4>従業員</h4>
                                        <ul class="sfk-sw-picked">
                                            @foreach ($byName as $personId => $person)
                                                <li wire:key="{{ $id }}-picked-user-{{ $personId }}">
                                                    <span>{{ $person['name'] }}</span>
                                                    <button type="button" class="sfk-sw-remove" wire:click="toggleUser({{ $personId }})" title="{{ $person['name'] }}を外す">
                                                        <x-filament::icon icon="heroicon-m-x-mark" /><span class="fi-sr-only">外す</span>
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @else
                                @php
                                    $pickedApps = array_intersect_key($appOptions, $chosenApps);
                                @endphp
                                <h3>選択中<span class="sfk-sw-count">{{ count($pickedApps) }}件</span></h3>
                                @if ($pickedApps !== [])
                                    <ul class="sfk-sw-picked">
                                        @foreach ($pickedApps as $appId => $app)
                                            <li wire:key="{{ $id }}-picked-app-{{ $appId }}">
                                                <span>{{ $app['name'] }}</span>
                                                <button type="button" class="sfk-sw-remove" wire:click="toggleApp({{ $appId }})" title="{{ $app['name'] }}を外す">
                                                    <x-filament::icon icon="heroicon-m-x-mark" /><span class="fi-sr-only">外す</span>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            @endif
                        </aside>
                    @endif
                </div>

                <footer class="sfk-sw-foot">
                    <button type="button" class="sfk-sw-btn" x-on:click="shown = false; $wire.close()">キャンセル</button>
                    <div>
                        @if ($screen !== SpaceWizard::STEPS[0] || $fromReview)
                            <button type="button" class="sfk-sw-btn" wire:click="back">戻る</button>
                        @endif
                        @if ($screen === SpaceWizard::REVIEW)
                            <button type="submit" class="sfk-sw-btn is-primary" wire:loading.attr="disabled">{{ $spaceId === null ? 'この内容で作成' : 'この内容で保存' }}</button>
                        @else
                            <button type="submit" class="sfk-sw-btn is-primary" wire:loading.attr="disabled">{{ $fromReview ? '確認へ' : '次へ' }}</button>
                        @endif
                    </div>
                </footer>
            </form>
        </div>
    </div>
</div>
