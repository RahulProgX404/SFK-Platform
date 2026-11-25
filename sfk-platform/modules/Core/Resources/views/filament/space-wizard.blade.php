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
