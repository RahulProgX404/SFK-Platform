{{-- 売上合計 (Modules\Analytics\Filament\Widgets\RevenueCard) --}}
@php
    use Modules\Core\Filament\Support\AppFormat;

    $dots = $reached === null ? 0 : min(5, intdiv($reached, 20));
@endphp

<div class="sfk-dash-cell sfk-dash-span-4">
    <style>
        .sfk-revenue { position: relative; display: flex; flex-direction: column; gap: var(--sfk-space-4); height: 100%; padding: var(--sfk-space-5); overflow: hidden; border-radius: var(--sfk-radius-xl); background: linear-gradient(135deg, var(--sfk-primary) 0%, #0d5fb3 55%, #2a83e0 100%); box-shadow: 0 10px 24px rgba(5, 70, 126, .22); color: #fff; }
        .sfk-revenue::after { content: ""; position: absolute; right: -60px; top: -60px; width: 220px; height: 220px; border-radius: 9999px; background: radial-gradient(circle, rgba(255, 255, 255, .16), transparent 70%); pointer-events: none; }
        .sfk-revenue-top { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--sfk-space-3); }
        .sfk-revenue-label { margin: 0; color: rgba(255, 255, 255, .82); font-size: var(--sfk-fs-label); }
        .sfk-revenue-amount { margin: 2px 0 0; font-size: 1.625rem; font-weight: var(--sfk-fw-bold); line-height: 1.25; font-variant-numeric: tabular-nums; letter-spacing: 0; overflow-wrap: anywhere; }
        .sfk-revenue-chip { flex: none; display: inline-flex; align-items: center; gap: 2px; padding: 2px 10px; border-radius: 9999px; background: rgba(255, 255, 255, .18); font-size: var(--sfk-fs-badge); font-weight: var(--sfk-fw-semibold); font-variant-numeric: tabular-nums; }
        .sfk-revenue-target { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--sfk-space-3); }
        .sfk-revenue-target strong { display: block; margin-top: 2px; font-size: var(--sfk-fs-body); font-weight: var(--sfk-fw-semibold); font-variant-numeric: tabular-nums; }
        .sfk-revenue-reached { text-align: right; font-size: var(--sfk-fs-small); color: rgba(255, 255, 255, .9); }
        .sfk-revenue-dots { display: flex; justify-content: flex-end; gap: 6px; margin-top: 6px; }
        .sfk-revenue-dots i { width: 10px; height: 10px; border-radius: 9999px; background: rgba(255, 255, 255, .28); }
        .sfk-revenue-dots i.is-on { background: #fff; }
        .sfk-revenue-more { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; gap: var(--sfk-space-3); margin-top: auto; padding: 8px 8px 8px var(--sfk-space-4); border-radius: 9999px; background: rgba(255, 255, 255, .16); color: #fff; font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); text-decoration: none; }
        .sfk-revenue-more:hover { background: rgba(255, 255, 255, .24); text-decoration: none; }
        .sfk-revenue-more span:first-child { display: inline-flex; align-items: center; gap: 8px; }
        .sfk-revenue-more .fi-icon { width: 16px; height: 16px; }
        .sfk-revenue-arrow { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 9999px; background: rgba(255, 255, 255, .2); }
    </style>

    <section class="sfk-revenue" aria-label="売上合計">
        <div class="sfk-revenue-top">
            <div>
                <p class="sfk-revenue-label">売上合計</p>
                <p class="sfk-revenue-amount">{{ AppFormat::yen($amount) }}</p>
            </div>
            @if ($change !== null)
                <span class="sfk-revenue-chip" title="前の期間との比較">{{ $change >= 0 ? '▲' : '▼' }} {{ abs($change) }}%</span>
            @endif
        </div>

        <div class="sfk-revenue-target">
            <div>
                <p class="sfk-revenue-label">{{ $targetLabel }}</p>
                <strong>{{ $target !== null ? AppFormat::yen($target) : '未設定' }}</strong>
            </div>
            <div class="sfk-revenue-reached">
                @if ($reached !== null)
                    {{ $reached }}% 達成
                @endif
                <div class="sfk-revenue-dots" aria-hidden="true">
                    @for ($i = 0; $i < 5; $i++)
                        <i @class(['is-on' => $i < $dots])></i>
                    @endfor
                </div>
            </div>
        </div>

        @if ($detailUrl !== null)
            <a href="{{ $detailUrl }}" class="sfk-revenue-more">
                <span><x-filament::icon icon="heroicon-o-chart-bar" /> 売上分析を見る</span>
                <span class="sfk-revenue-arrow"><x-filament::icon icon="heroicon-m-arrow-right" /></span>
            </a>
        @endif
    </section>
</div>
