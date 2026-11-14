{{-- 売れ筋 TOP3 (Modules\Analytics\Filament\Widgets\TopProductsCard): the three 品名 with the most sales in the period as
     horizontal bars — place, 品名 (two lines at most, the whole name on hover), the amount and the number of sales, the bar
     against the first. Across the narrow card nothing overlaps (2026-11-11; the upright bars crowded their labels). --}}
@php
    use Modules\Analytics\Filament\Dashboard\DashboardTips;
    use Modules\Core\Filament\Support\AppFormat;

    $colors = ['var(--sfk-primary)', '#16956b', '#d48806'];
@endphp

<div class="sfk-dash-cell sfk-dash-span-20p is-fit">
    <style>
        .sfk-products { display: flex; flex-direction: column; gap: var(--sfk-space-4); margin: 0; padding: 0; list-style: none; }
        .sfk-products-item { display: grid; grid-template-columns: 24px minmax(0, 1fr); gap: 6px var(--sfk-space-2); }
        .sfk-products-item .sfk-dash-rankno { grid-row: span 3; align-self: start; }
        .sfk-products-name { display: -webkit-box; overflow: hidden; color: var(--sfk-ink); font-size: var(--sfk-fs-body); font-weight: var(--sfk-fw-semibold); line-height: 1.35; overflow-wrap: anywhere; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        .sfk-products-bar { height: 10px; overflow: hidden; border-radius: 9999px; background: var(--sfk-line-soft); }
        .sfk-products-bar i { display: block; height: 100%; border-radius: 9999px; }
        .sfk-products-figure { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 0 var(--sfk-space-2); }
        .sfk-products-amount { color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-bold); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .sfk-products-count { color: var(--sfk-muted); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; white-space: nowrap; }
    </style>

    <section class="sfk-dash-card" aria-labelledby="sfk-products-title">
        <header class="sfk-dash-card-head">
            <div class="sfk-dash-card-titles">
                <h2 class="sfk-dash-card-title" id="sfk-products-title">売れ筋 TOP3</h2>
                <span class="sfk-dash-caption"><span class="sfk-dash-tag is-selected">選択期間</span>{{ $this->periodCaption() }}</span>
            </div>
            @if ($salesUrl !== null)
                <a href="{{ $salesUrl }}" class="sfk-dash-more" x-tooltip="{{ \Modules\Core\Filament\Support\Tip::text('売上入力を開く')->x() }}"><x-filament::icon icon="heroicon-m-arrow-right" /><span class="fi-sr-only">売上入力を開く</span></a>
            @endif
        </header>

        @if ($products === [])
            <p class="sfk-dash-empty">この期間の売上はありません。</p>
        @else
            <ol class="sfk-products">
                @foreach ($products as $index => $product)
                    @php($tip = DashboardTips::ranking($product['name'], DashboardTips::share($product['amount'], $whole)))
                    <li class="sfk-products-item" tabindex="0" data-sfk-tip x-tooltip="{{ $tip->x() }}">
                        <span @class(['sfk-dash-rankno', 'is-'.($index + 1)])>{{ $index + 1 }}</span>
                        <span class="sfk-products-name">{{ $product['name'] }}</span>
                        <span class="sfk-products-bar" aria-hidden="true"><i style="width: {{ max(3, (int) round($product['amount'] / $top * 100)) }}%; background: {{ $colors[$index] ?? $colors[0] }}"></i></span>
                        <span class="sfk-products-figure">
                            <span class="sfk-products-amount">{{ AppFormat::yen($product['amount']) }}</span>
                            <span class="sfk-products-count">{{ number_format($product['count']) }}件</span>
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</div>
