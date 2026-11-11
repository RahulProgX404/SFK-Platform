{{-- 売上合計 (Modules\Analytics\Filament\Widgets\RevenueCard): the first figure of the dashboard — the sales of the period,
     compared with the same number of days just before (前期間比, with that amount and those days), and the progress
     towards 売上目標 (個人: 売上予想) of the period. --}}
@php
    use Modules\Core\Filament\Support\AppFormat;

    $progress = $reached === null ? 0 : min(100, $reached);
@endphp

<div class="sfk-dash-cell sfk-dash-span-4">
    <style>
        .sfk-revenue { display: flex; flex-direction: column; gap: var(--sfk-space-4); height: 100%; padding: var(--sfk-space-5); border-radius: var(--sfk-radius-xl); background: linear-gradient(160deg, var(--sfk-primary) 0%, #0b5aa3 100%); box-shadow: 0 8px 20px rgba(5, 70, 126, .2); color: #fff; }
        .sfk-revenue-head { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--sfk-space-3); }
        .sfk-revenue-title { margin: 0; font-size: var(--sfk-fs-heading); font-weight: var(--sfk-fw-bold); }
        .sfk-revenue-caption { display: block; margin-top: 2px; color: rgba(255, 255, 255, .78); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; }
        .sfk-revenue-amount { margin: 0; font-size: 22px; font-weight: var(--sfk-fw-bold); line-height: 1.2; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
        .sfk-revenue-sub { margin: 4px 0 0; color: rgba(255, 255, 255, .78); font-size: var(--sfk-fs-small); font-variant-numeric: tabular-nums; }
        .sfk-revenue-compare { display: flex; flex-wrap: wrap; align-items: center; gap: 4px var(--sfk-space-2); color: rgba(255, 255, 255, .82); font-size: var(--sfk-fs-small); font-variant-numeric: tabular-nums; }
        .sfk-revenue-chip { display: inline-flex; align-items: center; gap: 2px; padding: 1px 8px; border-radius: 9999px; background: #fff; color: var(--sfk-success); font-size: var(--sfk-fs-badge); font-weight: var(--sfk-fw-bold); white-space: nowrap; }
        .sfk-revenue-chip.is-down { color: var(--sfk-danger-text, var(--sfk-danger)); }
        .sfk-revenue-target { padding: var(--sfk-space-3) var(--sfk-space-4); border-radius: var(--sfk-radius-lg); background: rgba(255, 255, 255, .12); }
        .sfk-revenue-target-row { display: flex; align-items: baseline; justify-content: space-between; gap: var(--sfk-space-3); font-size: var(--sfk-fs-small); }
        .sfk-revenue-target-row strong { font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-bold); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .sfk-revenue-bar { height: 8px; margin: var(--sfk-space-2) 0 6px; overflow: hidden; border-radius: 9999px; background: rgba(255, 255, 255, .22); }
        .sfk-revenue-bar i { display: block; height: 100%; border-radius: 9999px; background: #fff; }
        .sfk-revenue-target-note { display: flex; justify-content: space-between; gap: var(--sfk-space-3); color: rgba(255, 255, 255, .82); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; }
        .sfk-revenue-target-note b { color: #fff; font-weight: var(--sfk-fw-bold); }
        .sfk-revenue-more { display: inline-flex; align-items: center; gap: 6px; align-self: flex-start; margin-top: auto; color: #fff; font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-semibold); text-decoration: none; opacity: .92; }
        .sfk-revenue-more:hover { opacity: 1; text-decoration: underline; }
        .sfk-revenue-more .fi-icon { width: 16px; height: 16px; }
    </style>

    <section class="sfk-revenue" aria-labelledby="sfk-revenue-title">
        <div class="sfk-revenue-head">
            <div>
                <h2 class="sfk-revenue-title" id="sfk-revenue-title">売上合計</h2>
                <span class="sfk-revenue-caption">{{ $this->periodCaption() }}</span>
            </div>
        </div>

        <div>
            <p class="sfk-revenue-amount">{{ AppFormat::yen($amount) }}</p>
            <p class="sfk-revenue-sub">売上件数 {{ number_format($count) }}件</p>
        </div>

        <div class="sfk-revenue-compare">
            @if ($change !== null)
                <span @class(['sfk-revenue-chip', 'is-down' => $change < 0])>前期間比 {{ $change >= 0 ? '▲' : '▼' }} {{ abs($change) }}%</span>
                <span>前期間 {{ AppFormat::yen($before) }}（{{ $previousLabel }}）</span>
            @else
                <span>前期間（{{ $previousLabel }}）の売上はありません</span>
            @endif
        </div>

        <div class="sfk-revenue-target">
            <div class="sfk-revenue-target-row">
                <span>{{ $targetLabel }}</span>
                <strong>{{ $target !== null ? AppFormat::yen($target) : '未設定' }}</strong>
            </div>
            @if ($reached !== null)
                <div class="sfk-revenue-bar" role="progressbar" aria-label="{{ $targetLabel }}の達成率" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}"><i style="width: {{ $progress }}%"></i></div>
                <div class="sfk-revenue-target-note">
                    <span><b>{{ $reached }}% 達成</b></span>
                    <span>{{ $amount >= $target ? '超過 '.AppFormat::yen($amount - $target) : '残り '.AppFormat::yen($target - $amount) }}</span>
                </div>
            @endif
        </div>

        @if ($detailUrl !== null)
            <a href="{{ $detailUrl }}" class="sfk-revenue-more"><x-filament::icon icon="heroicon-o-chart-bar" />売上分析を見る<x-filament::icon icon="heroicon-m-arrow-right" /></a>
        @endif
    </section>
</div>
