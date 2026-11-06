{{-- 売れ筋 TOP3 (Modules\Analytics\Filament\Widgets\TopProductsCard): bars on a dashed grid, each with its 品名 and amount
     written on top of it, across (bug.md 2026-11-06: up the bar it could not be read) --}}
@php
    use Modules\Analytics\Domain\ShortYen;
    use Modules\Core\Filament\Support\AppFormat;
@endphp

<div class="sfk-dash-cell sfk-dash-span-4">
    <style>
        /* the labels of the tallest bar stand in the space above the grid (padding-top) */
        .sfk-products {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            grid-template-rows: 236px;
            gap: var(--sfk-space-3);
            padding-top: 64px;
        }

        .sfk-products-axis {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: var(--sfk-subtle);
            font-size: 11px;
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .sfk-products-axis span {
            line-height: 1;
            transform: translateY(50%);
        }

        .sfk-products-axis span:first-child {
            transform: translateY(0);
        }

        .sfk-products-plot {
            position: relative;
            display: grid;
            margin: 0;
            list-style: none;
            grid-auto-columns: minmax(0, 1fr);
            grid-auto-flow: column;
            align-items: end;
            gap: var(--sfk-space-3);
            padding: 0 var(--sfk-space-2);
            background: repeating-linear-gradient(to top, var(--sfk-line) 0 1px, transparent 1px 20%);
            border-bottom: 1px solid var(--sfk-line-strong);
        }

        .sfk-products-col {
            position: relative;
            display: flex;
            justify-content: center;
            height: 100%;
            align-items: flex-end;
            min-width: 0;
        }

        .sfk-products-bar {
            position: relative;
            width: min(72px, 70%);
            min-height: 12px;
            border-radius: 8px 8px 0 0;
            background: linear-gradient(180deg, #7fb2e5 0%, var(--sfk-primary) 100%);
        }

        .sfk-products-bar.is-2 {
            background: linear-gradient(180deg, #8fd0c3 0%, #16956b 100%);
        }

        .sfk-products-bar.is-3 {
            background: linear-gradient(180deg, #f3c98b 0%, #d48806 100%);
        }

        .sfk-products-label {
            position: absolute;
            bottom: calc(100% + 6px);
            left: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1px;
            width: max(100%, 104px);
            max-width: 150px;
            text-align: center;
            transform: translateX(-50%);
            background: #fff;
            padding: 0 2px;
        }

        .sfk-products-name {
            display: -webkit-box;
            overflow: hidden;
            color: var(--sfk-ink);
            font-size: var(--sfk-fs-small);
            font-weight: var(--sfk-fw-semibold);
            line-height: 1.3;
            overflow-wrap: anywhere;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .sfk-products-amount {
            color: var(--sfk-muted);
            font-size: var(--sfk-fs-badge);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
    </style>

    <section class="sfk-dash-card" aria-labelledby="sfk-products-title">
        <header class="sfk-dash-card-head">
            <h2 class="sfk-dash-card-title" id="sfk-products-title">売れ筋 TOP3</h2>
            @if ($salesUrl !== null)
                <a href="{{ $salesUrl }}" class="sfk-dash-more" title="売上入力を開く"><x-filament::icon
                        icon="heroicon-m-arrow-right" /><span class="fi-sr-only">売上入力を開く</span></a>
            @endif
        </header>

        @if ($products === [])
            <p class="sfk-dash-empty">この期間の売上はありません。</p>
        @else
            <div class="sfk-products">
                <div class="sfk-products-axis" aria-hidden="true">
                    @foreach ($axis as $step)
                        <span>{{ ShortYen::format($step) }}</span>
                    @endforeach
                </div>
                <ol class="sfk-products-plot">
                    @foreach ($products as $index => $product)
                        <li class="sfk-products-col"
                            title="{{ $product['name'] }}：{{ AppFormat::yen($product['amount']) }}（{{ number_format($product['count']) }}件）">
                            <div class="sfk-products-bar is-{{ $index + 1 }}"
                                style="height: {{ max(8, (int) round(($product['amount'] / $top) * 100)) }}%">
                                <span class="sfk-products-label">
                                    <span class="sfk-products-name">{{ $product['name'] }}</span>
                                    <span
                                        class="sfk-products-amount">{{ ShortYen::format($product['amount']) }}・{{ number_format($product['count']) }}件</span>
                                </span>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </section>
</div>
