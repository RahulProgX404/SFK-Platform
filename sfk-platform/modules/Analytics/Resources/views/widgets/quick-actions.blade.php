{{-- クイックアクション (Modules\Analytics\Filament\Widgets\QuickActionsCard): one coloured row per waiting step; a click runs it.
     Nothing waiting: the apps the user opened last ($recent), each with the record last opened in it (a click opens it) --}}
@php
    $tones = ['is-blue', 'is-teal', 'is-green', 'is-orange', 'is-dark', 'is-purple'];
@endphp

<div class="sfk-dash-cell sfk-dash-span-{{ $span }} sfk-dash-t-half is-fit">
    <style>
        .sfk-quick { display: flex; flex-direction: column; gap: var(--sfk-space-3); margin: 0; padding: 0; list-style: none; }
        .sfk-quick-row { display: flex; align-items: center; gap: var(--sfk-space-3); min-height: 56px; padding: var(--sfk-space-2) var(--sfk-space-3); border-radius: var(--sfk-radius-lg); background: var(--sfk-quick); color: #fff; }
        .sfk-quick-row.is-blue { --sfk-quick: var(--sfk-primary); }
        .sfk-quick-row.is-teal { --sfk-quick: #0b8aa3; }
        .sfk-quick-row.is-green { --sfk-quick: #16956b; }
        .sfk-quick-row.is-orange { --sfk-quick: #d4610f; }
        .sfk-quick-row.is-dark { --sfk-quick: #1f2937; }
        .sfk-quick-row.is-purple { --sfk-quick: #6e56cf; }
        .sfk-quick-icon { display: grid; place-items: center; width: 30px; height: 30px; flex: none; border-radius: 9999px; background: rgba(255, 255, 255, .18); }
        .sfk-quick-icon .fi-icon { width: 16px; height: 16px; }
        .sfk-quick-text { flex: 1; min-width: 0; color: #fff; text-decoration: none; }
        .sfk-quick-text:hover { text-decoration: underline; }
        .sfk-quick-text strong { display: block; overflow: hidden; font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); text-overflow: ellipsis; white-space: nowrap; }
        .sfk-quick-text small { display: block; overflow: hidden; color: rgba(255, 255, 255, .85); font-size: var(--sfk-fs-badge); text-overflow: ellipsis; white-space: nowrap; }
        .sfk-quick-run { flex: none; display: inline-flex; align-items: center; gap: 4px; height: 30px; padding: 0 12px; border-radius: 9999px; background: rgba(255, 255, 255, .2); color: #fff; font-size: var(--sfk-fs-badge); font-weight: var(--sfk-fw-semibold); white-space: nowrap; }
        .sfk-quick-run:hover { background: rgba(255, 255, 255, .32); }
        .sfk-quick-run .fi-icon { width: 14px; height: 14px; }
        .sfk-quick-done { display: flex; flex-direction: column; align-items: center; gap: var(--sfk-space-2); padding: var(--sfk-space-6) var(--sfk-space-4); color: var(--sfk-muted); font-size: var(--sfk-fs-small); text-align: center; }
        .sfk-quick-done .fi-icon { width: 36px; height: 36px; color: #16956b; }
        .sfk-recent { display: flex; flex-direction: column; margin: 0; padding: 0; list-style: none; }
        .sfk-recent li + li { border-top: 1px solid var(--sfk-line-soft); }
        .sfk-recent-link { display: flex; align-items: center; gap: var(--sfk-space-3); min-height: 54px; padding: var(--sfk-space-2); border-radius: var(--sfk-radius); color: inherit; text-decoration: none; }
        .sfk-recent-link:hover { background: var(--sfk-hover); text-decoration: none; }
        .sfk-recent-link:hover strong { color: var(--sfk-primary); }
        .sfk-recent-icon { display: grid; place-items: center; width: 36px; height: 36px; flex: none; overflow: hidden; border-radius: var(--sfk-radius-lg); background: var(--sfk-primary); color: #fff; font-size: 11px; font-weight: var(--sfk-fw-bold); line-height: 1; }
        .sfk-recent-text { flex: 1; min-width: 0; }
        .sfk-recent-text strong { display: block; overflow: hidden; color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); text-overflow: ellipsis; white-space: nowrap; }
        .sfk-recent-text small { display: block; overflow: hidden; color: var(--sfk-muted); font-size: var(--sfk-fs-badge); text-overflow: ellipsis; white-space: nowrap; }
        .sfk-recent-id { flex: none; padding: 2px 8px; border-radius: 9999px; background: var(--sfk-primary-soft); color: var(--sfk-primary-dark); font-size: var(--sfk-fs-badge); font-weight: var(--sfk-fw-semibold); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .sfk-recent-arrow { width: 16px; height: 16px; flex: none; color: var(--sfk-subtle); }
        .sfk-quick-more { margin: var(--sfk-space-3) 0 0; color: var(--sfk-muted); font-size: var(--sfk-fs-small); text-align: right; }
    </style>

    <section class="sfk-dash-card" aria-labelledby="sfk-quick-title">
        <header class="sfk-dash-card-head">
            {{-- nothing waits but there are recently viewed apps: the card is about those (bug.md 2026-11-06) --}}
            <h2 class="sfk-dash-card-title" id="sfk-quick-title">{{ $actions === [] && $recent !== [] ? '最近表示した項目' : 'クイックアクション' }}</h2>
        </header>

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
                        <button type="button" class="sfk-quick-run" wire:click="runAction(@js($action['key']))" wire:loading.attr="disabled">
                            {{ $action['action'] }}
                            <x-filament::icon icon="heroicon-m-arrow-right" />
                        </button>
                    </li>
                @endforeach
            </ul>
            @if ($more > 0)
                <p class="sfk-quick-more">ほか {{ $more }} 件</p>
            @endif
        @endif
    </section>
</div>
