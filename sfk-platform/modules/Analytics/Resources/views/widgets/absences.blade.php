{{-- 休暇・勤怠予定 (Modules\Analytics\Filament\Widgets\AbsencesCard): 今日 and 明日, side by side where the card is wide
     enough, each under its date --}}
@php
    use Modules\Analytics\Filament\Dashboard\DashboardCard;

    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
@endphp

<div class="sfk-dash-cell sfk-dash-span-{{ $span }} sfk-dash-t-half is-fit">
    {{-- live: redrawn when what it reads changes (DashboardCard::liveTables(), DataPulse) --}}
    @include('core::filament.live-data', ['tables' => $this::liveTables()])
    <section class="sfk-dash-card" aria-labelledby="sfk-absences-title">
        <header class="sfk-dash-card-head">
            <div class="sfk-dash-card-titles">
                <h2 class="sfk-dash-card-title" id="sfk-absences-title">休暇・勤怠予定</h2>
                <span class="sfk-dash-caption"><span class="sfk-dash-tag">今日・明日</span>{{ $dates['today']->format('Y/m/d') }}〜{{ $dates['tomorrow']->format('m/d') }}</span>
            </div>
            @if ($requestsUrl !== null)
                <a href="{{ $requestsUrl }}" class="sfk-dash-more" x-tooltip="{{ \Modules\Core\Filament\Support\Tip::text('申請管理を開く')->x() }}"><x-filament::icon icon="heroicon-m-arrow-right" /><span class="fi-sr-only">申請管理を開く</span></a>
            @endif
        </header>

        <div class="sfk-dash-stack" style="--sfk-absence-rows: {{ \Modules\Analytics\Filament\Widgets\AbsencesCard::ROWS_PER_DAY }}">
            @include('analytics::widgets.partials.ghost', ['kind' => 'absences', 'rows' => \Modules\Analytics\Filament\Widgets\AbsencesCard::ROWS_PER_DAY])
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
        </div>
    </section>

</div>
