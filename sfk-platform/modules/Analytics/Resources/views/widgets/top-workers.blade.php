{{-- 作業者ランキング (Modules\Analytics\Filament\Widgets\TopWorkersCard; design of revenue-category.png): one row per
     作業者 on a soft tint fading to the right — a white tile with their initials, the name, the share of the period and
     the hours, the amount on the right --}}
@php
    use Modules\Analytics\Filament\Dashboard\DashboardCard;
    use Modules\Core\Filament\Support\AppFormat;
@endphp

<div class="sfk-dash-cell sfk-dash-span-4">
    <style>
        .sfk-workers { display: flex; flex-direction: column; gap: var(--sfk-space-3); margin: 0; padding: 0; list-style: none; }
        .sfk-workers-row { --sfk-worker: var(--sfk-primary); --sfk-worker-soft: var(--sfk-primary-soft); display: flex; align-items: center; gap: var(--sfk-space-3); min-height: 60px; padding: var(--sfk-space-2) var(--sfk-space-4) var(--sfk-space-2) var(--sfk-space-2); border-radius: var(--sfk-radius-lg); background: linear-gradient(90deg, var(--sfk-worker-soft) 0%, rgba(255, 255, 255, 0) 100%); }
        .sfk-workers-row.is-2 { --sfk-worker: #d4610f; --sfk-worker-soft: #fdf0e6; }
        .sfk-workers-row.is-3 { --sfk-worker: #6e56cf; --sfk-worker-soft: #f1eefc; }
        .sfk-workers-row.is-4 { --sfk-worker: #16956b; --sfk-worker-soft: #e5f5ee; }
        .sfk-workers-row.is-5 { --sfk-worker: #c2185b; --sfk-worker-soft: #fcebf2; }
        /* the signed-in user's own row (bug.md 2026-11-06) */
        .sfk-workers-row.is-me { box-shadow: inset 0 0 0 2px var(--sfk-worker); }
        .sfk-workers-tile { display: grid; place-items: center; width: 44px; height: 44px; flex: none; border-radius: var(--sfk-radius-lg); background: var(--sfk-surface); box-shadow: 0 1px 3px rgba(16, 24, 40, .08); color: var(--sfk-worker); font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-bold); }
        .sfk-workers-text { flex: 1; min-width: 0; }
        .sfk-workers-text strong { display: block; overflow: hidden; color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); text-overflow: ellipsis; white-space: nowrap; }
        .sfk-workers-text small { display: block; overflow: hidden; color: var(--sfk-muted); font-size: var(--sfk-fs-small); font-variant-numeric: tabular-nums; text-overflow: ellipsis; white-space: nowrap; }
        .sfk-workers-amount { flex: none; color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-bold); font-variant-numeric: tabular-nums; white-space: nowrap; }
    </style>

    <section class="sfk-dash-card" aria-labelledby="sfk-top-workers-title">
        <header class="sfk-dash-card-head">
            <h2 class="sfk-dash-card-title" id="sfk-top-workers-title">作業者ランキング</h2>
            @if ($analysisUrl !== null)
                <a href="{{ $analysisUrl }}" class="sfk-dash-more" title="日報分析を見る"><x-filament::icon icon="heroicon-m-arrow-right" /><span class="fi-sr-only">日報分析を見る</span></a>
            @endif
        </header>

        @forelse ($workers as $index => $worker)
            @if ($loop->first)
                <ol class="sfk-workers">
            @endif
            <li @class(['sfk-workers-row', 'is-'.($index + 1), 'is-me' => $worker['user_id'] === $me]) @if ($worker['user_id'] === $me) aria-current="true" @endif>
                <span class="sfk-workers-tile" aria-hidden="true">{{ DashboardCard::initials($worker['name']) }}</span>
                <span class="sfk-workers-text">
                    <strong>{{ $worker['name'] }}</strong>
                    <small title="全作業者の合計に占める割合・作業時間 + 残業時間">{{ $worker['share'] }}%・{{ rtrim(rtrim(number_format($worker['total_hours'], 1), '0'), '.') }}時間</small>
                </span>
                <span class="sfk-workers-amount">{{ AppFormat::yen($worker['total']) }}</span>
            </li>
            @if ($loop->last)
                </ol>
            @endif
        @empty
            <p class="sfk-dash-empty">この期間の日報はありません。</p>
        @endforelse
    </section>
</div>
