{{--
    The legacy graphs of 22. 過去の売上データ as カスタマイズ形式 views (Modules\Analytics\Filament\Resources\PastSales\Pages\ListPastSales::pastSalesChart()):
    年間売上 — one horizontal bar per 決算年度 under each 担当者; 担当者別売上比較 — the range smallest … largest 年間合計 per 担当者.
    One hue (magnitude only), the values written at the bars and in the tooltip; values below 0 are drawn from 0.
--}}
@php
    use Modules\Core\Filament\Support\AppFormat;

    $chart = $page->pastSalesChart();
    $max = max(1, $chart['max']);
    $share = fn (int $value): float => round(max(0, $value) / $max * 100, 3);
@endphp

@once
    <style>
        .sfk-chart { background: #fff; border: 1px solid var(--kt-border); padding: 16px 20px; }
        .sfk-chart-group { display: grid; grid-template-columns: 180px 1fr; gap: 6px 16px; padding: 10px 0; border-bottom: 1px solid #f0f2f3; }
        .sfk-chart-group:last-child { border-bottom: 0; }
        .sfk-chart-name { color: var(--kt-label); font-size: .8125rem; font-weight: 700; }
        .sfk-chart-bars { display: grid; gap: 6px; }
        .sfk-chart-bar { display: grid; grid-template-columns: 72px 1fr; align-items: center; gap: 8px; font-size: .75rem; color: var(--kt-label); }
        .sfk-chart-track { position: relative; height: 18px; margin-inline-end: 240px; }
        .sfk-chart-fill { position: absolute; top: 0; bottom: 0; background: var(--kt-blue); }
        .sfk-chart-fill:hover { filter: brightness(.9); }
        .sfk-chart-value { position: absolute; top: 0; line-height: 18px; padding-inline-start: 6px; white-space: nowrap; color: var(--kt-text); }
        .sfk-chart-low { position: absolute; top: -2px; bottom: -2px; width: 2px; background: var(--kt-text); }
    </style>
@endonce

<section class="sfk-chart" aria-label="{{ $page->currentView()->name }}">
    @foreach ($chart['rows'] as $row)
        <div class="sfk-chart-group">
            <div class="sfk-chart-name">{{ $row['label'] }}</div>
            <div class="sfk-chart-bars">
                @foreach ($row['bars'] as $bar)
                    <div class="sfk-chart-bar">
                        <span>{{ $bar['label'] }}</span>
                        <div class="sfk-chart-track">
                            @if (array_key_exists('low', $bar))
                                @php($title = '最大 '.AppFormat::yen($bar['value']).' / 最小 '.AppFormat::yen($bar['low']))
                                <div class="sfk-chart-fill" style="left: {{ $share($bar['low']) }}%; width: {{ max(0.4, $share($bar['value']) - $share($bar['low'])) }}%" title="{{ $title }}"></div>
                                <div class="sfk-chart-low" style="left: {{ $share($bar['low']) }}%" title="最小 {{ AppFormat::yen($bar['low']) }}"></div>
                                <span class="sfk-chart-value" style="left: {{ $share($bar['value']) }}%">{{ $title }}</span>
                            @else
                                <div class="sfk-chart-fill" style="left: 0; width: {{ max(0.4, $share($bar['value'])) }}%" title="{{ $row['label'] }} {{ $bar['label'] }} {{ AppFormat::yen($bar['value']) }}"></div>
                                <span class="sfk-chart-value" style="left: {{ $share($bar['value']) }}%">{{ AppFormat::yen($bar['value']) }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</section>
