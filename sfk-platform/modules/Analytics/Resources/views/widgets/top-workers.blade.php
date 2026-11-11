{{-- 作業者ランキング (Modules\Analytics\Filament\Widgets\TopWorkersCard): the five 作業者 who made the most in the period —
     place, name, their share of what all 作業者 made and their hours, the amount on the right and a bar against the first
     (sfk-dash-ranking); the signed-in user's own row is tinted (bug.md 2026-11-06). --}}
@php
    use Modules\Core\Filament\Support\AppFormat;

    $topTotal = max(1, (int) ($workers[0]['total'] ?? 0));
@endphp

<div class="sfk-dash-cell sfk-dash-span-4">
    <section class="sfk-dash-card" aria-labelledby="sfk-top-workers-title">
        <header class="sfk-dash-card-head">
            <div class="sfk-dash-card-titles">
                <h2 class="sfk-dash-card-title" id="sfk-top-workers-title">作業者ランキング</h2>
                <span class="sfk-dash-caption">{{ $this->periodCaption() }}</span>
            </div>
            @if ($analysisUrl !== null)
                <a href="{{ $analysisUrl }}" class="sfk-dash-more" title="日報分析を見る"><x-filament::icon icon="heroicon-m-arrow-right" /><span class="fi-sr-only">日報分析を見る</span></a>
            @endif
        </header>

        @forelse ($workers as $index => $worker)
            @if ($loop->first)
                <ol class="sfk-dash-ranking sfk-workers">
            @endif
            <li @class(['sfk-workers-row', 'is-'.($index + 1), 'is-me' => $worker['user_id'] === $me]) @if ($worker['user_id'] === $me) aria-current="true" @endif>
                <span @class(['sfk-dash-rankno', 'is-'.($index + 1)])>{{ $index + 1 }}</span>
                <span class="sfk-dash-ranking-name">
                    <strong>{{ $worker['name'] }}</strong>
                    <small title="全作業者の合計に占める割合・作業時間 + 残業時間">{{ $worker['share'] }}%・{{ rtrim(rtrim(number_format($worker['total_hours'], 1), '0'), '.') }}時間</small>
                </span>
                <span class="sfk-dash-ranking-figure"><strong>{{ AppFormat::yen($worker['total']) }}</strong></span>
                <span class="sfk-dash-meter" aria-hidden="true"><i style="width: {{ max(2, (int) round($worker['total'] / $topTotal * 100)) }}%"></i></span>
            </li>
            @if ($loop->last)
                </ol>
            @endif
        @empty
            <p class="sfk-dash-empty">この期間の日報はありません。</p>
        @endforelse
    </section>
</div>
