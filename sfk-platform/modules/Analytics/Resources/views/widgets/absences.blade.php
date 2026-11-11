{{-- 休暇・勤怠予定 (Modules\Analytics\Filament\Widgets\AbsencesCard): 今日 and 明日, side by side where the card is wide
     enough, each under its date --}}
@php
    use Modules\Analytics\Filament\Dashboard\DashboardCard;

    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
@endphp

<div class="sfk-dash-cell sfk-dash-span-{{ $span }}">
    <style>
        .sfk-absence-days { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--sfk-space-3); }
        .sfk-absence-day { min-width: 0; padding: var(--sfk-space-3); border-radius: var(--sfk-radius-lg); box-shadow: inset 0 0 0 1px var(--sfk-line-soft); }
        .sfk-absence-day h3 { display: flex; align-items: baseline; justify-content: space-between; gap: var(--sfk-space-2); margin: 0 0 var(--sfk-space-1); color: var(--sfk-ink); font-size: var(--sfk-fs-body); font-weight: var(--sfk-fw-bold); }
        .sfk-absence-day h3 small { color: var(--sfk-muted); font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-regular); font-variant-numeric: tabular-nums; }
        .sfk-absence-day.is-today { background: var(--sfk-primary-soft); box-shadow: none; }
        .sfk-absence-chip { flex: none; padding: 1px 8px; border-radius: 9999px; background: var(--sfk-tone-soft); color: var(--sfk-tone); font-size: var(--sfk-fs-badge); font-weight: var(--sfk-fw-semibold); white-space: nowrap; }
        .sfk-absence-none { margin: 0; padding: var(--sfk-space-1) 0; color: var(--sfk-muted); font-size: var(--sfk-fs-small); }
    </style>

    <section class="sfk-dash-card" aria-labelledby="sfk-absences-title">
        <header class="sfk-dash-card-head">
            <div class="sfk-dash-card-titles">
                <h2 class="sfk-dash-card-title" id="sfk-absences-title">休暇・勤怠予定</h2>
                <span class="sfk-dash-caption">{{ $dates['today']->format('Y/m/d') }}〜{{ $dates['tomorrow']->format('m/d') }}</span>
            </div>
            @if ($requestsUrl !== null)
                <a href="{{ $requestsUrl }}" class="sfk-dash-more" title="申請管理を開く"><x-filament::icon icon="heroicon-m-arrow-right" /><span class="fi-sr-only">申請管理を開く</span></a>
            @endif
        </header>

        <div class="sfk-absence-days">
        @foreach (['today' => '今日', 'tomorrow' => '明日'] as $key => $label)
            <div @class(['sfk-absence-day', 'is-today' => $key === 'today'])>
                <h3>{{ $label }}<small>{{ $dates[$key]->format('n月j日') }}（{{ $weekdays[(int) $dates[$key]->format('w')] }}）</small></h3>
                @forelse ($days[$key] as $absence)
                    @if ($loop->first)
                        <ul class="sfk-dash-people is-compact">
                    @endif
                    <li>
                        <span class="sfk-dash-avatar is-solid is-{{ ($loop->index % 5) + 1 }}">{{ DashboardCard::initials($absence['name']) }}</span>
                        <a href="{{ $absence['url'] }}" class="sfk-dash-people-text">
                            <strong>{{ $absence['name'] }}</strong>
                            <small>{{ $absence['category'] }}・{{ $absence['type'] }}{{ filled($absence['time']) ? '（'.$absence['time'].'）' : '' }}</small>
                        </a>
                        <span @class(['sfk-absence-chip', 'sfk-tone-green' => $absence['status'] === '承認済', 'sfk-tone-amber' => $absence['status'] !== '承認済'])>{{ $absence['status'] === '承認済' ? '承認済' : '申請中' }}</span>
                    </li>
                    @if ($loop->last)
                        </ul>
                    @endif
                @empty
                    <p class="sfk-absence-none">予定はありません。</p>
                @endforelse
            </div>
        @endforeach
        </div>
    </section>
</div>
