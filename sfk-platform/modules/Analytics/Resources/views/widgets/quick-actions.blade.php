{{-- クイックアクション (Modules\Analytics\Filament\Widgets\QuickActionsCard): one coloured row per waiting step with its buttons
     (TeamDashboard::pendingActions(): a button runs its step — 承認, 差戻し after a confirmation — or opens its page, e.g. 申請する).
     Nothing waiting: the apps the user opened last ($recent), each with the record last opened in it (a click opens it).
     Every state keeps the height of the six recent apps (ghost rows, user 2026-11-17). --}}
@php
    $tones = ['is-blue', 'is-teal', 'is-green', 'is-orange', 'is-dark', 'is-purple'];
@endphp

<div class="sfk-dash-cell sfk-dash-span-{{ $span }} sfk-dash-t-half is-fit">
    {{-- live: redrawn when what it reads changes (DashboardCard::liveTables(), DataPulse) --}}
    @include('core::filament.live-data', ['tables' => $this::liveTables()])
    <section class="sfk-dash-card" aria-labelledby="sfk-quick-title">
        <header class="sfk-dash-card-head">
            {{-- nothing waits but there are recently viewed apps: the card is about those (bug.md 2026-11-06) --}}
            <h2 class="sfk-dash-card-title" id="sfk-quick-title">{{ $actions === [] && $recent !== [] ? '最近表示した項目' : 'クイックアクション' }}</h2>
        </header>

        <div class="sfk-dash-stack">
            @include('analytics::widgets.partials.ghost', ['kind' => 'recent', 'rows' => \Modules\Analytics\Filament\Widgets\QuickActionsCard::RECENT])
            @if ($actions === [] && $recent !== [])
                <ul class="sfk-recent" aria-label="最近開いたアプリ">
                    @foreach ($recent as $item)
                        <li wire:key="recent-{{ $item['code'] }}">
                            <a href="{{ $item['url'] }}" class="sfk-recent-link">
                                <span class="sfk-recent-icon" aria-hidden="true">{{ $item['icon'] }}</span>
                                <span class="sfk-recent-text">
                                    <strong>{{ $item['app'] }}</strong>
                                    <small>{{ $item['title'] ?? ($item['record'] !== null ? 'レコード番号 '.$item['record'] : '一覧') }}{{ $item['at'] !== '' ? '・'.$item['at'] : '' }}</small>
                                </span>
                                @if ($item['record'] !== null)
                                    <span class="sfk-recent-id">#{{ $item['record'] }}</span>
                                @endif
                                <x-filament::icon icon="heroicon-m-chevron-right" class="sfk-recent-arrow" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @elseif ($actions === [])
                <div class="sfk-quick-done">
                    <x-filament::icon icon="heroicon-o-check-circle" />
                    対応が必要な作業はありません。
                </div>
            @else
                <ul class="sfk-quick">
                    @foreach ($actions as $index => $action)
                        <li class="sfk-quick-row {{ $tones[$index % count($tones)] }}" wire:key="quick-{{ $action['key'] }}">
                            <span class="sfk-quick-icon" aria-hidden="true">
                                <x-filament::icon :icon="$action['kind'] === 'leave' ? 'heroicon-o-calendar-days' : 'heroicon-o-shopping-cart'" />
                            </span>
                            <a href="{{ $action['url'] }}" class="sfk-quick-text">
                                <strong>{{ $action['title'] }}</strong>
                                <small>{{ $action['subtitle'] }}</small>
                            </a>
                            <span class="sfk-quick-runs">
                                @foreach ($action['buttons'] as $button)
                                    @if (isset($button['url']))
                                        <a href="{{ $button['url'] }}" class="sfk-quick-run">
                                            {{ $button['label'] }}
                                            <x-filament::icon icon="heroicon-m-arrow-right" />
                                        </a>
                                    @else
                                        <button type="button" class="sfk-quick-run" wire:click="runAction(@js($button['run']))"
                                            @isset($button['confirm']) wire:confirm="{{ $button['confirm'] }}" @endisset
                                            wire:loading.attr="disabled">
                                            {{ $button['label'] }}
                                            @if ($loop->first)
                                                <x-filament::icon icon="heroicon-m-arrow-right" />
                                            @endif
                                        </button>
                                    @endif
                                @endforeach
                            </span>
                        </li>
                    @endforeach
                </ul>
                @if ($more > 0)
                    <p class="sfk-quick-more">ほか {{ $more }} 件</p>
                @endif
            @endif
        </div>
    </section>
</div>
