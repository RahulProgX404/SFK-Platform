{{-- 集計サマリー under the search row of the list of 04. 受注入力 (Modules\Orders\Filament\Resources\Orders\Pages\ListOrders::orderSummary()). --}}
@php
    use Modules\Orders\Filament\Resources\Orders\OrderFormat;
    use Modules\Orders\Filament\Resources\Orders\OrderLayout;

    $summary = $page->orderSummary();
    $sign = fn (int|float $value): string => $value > 0 ? 'is-positive' : ($value < 0 ? 'is-negative' : '');
@endphp

<section class="sfk-order-summary" x-data="{ open: true }" aria-label="集計サマリー">
    <button type="button" class="sfk-order-summary-bar" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'">
        <span class="sfk-order-summary-title">集計サマリー</span>
        <span class="sfk-order-summary-count">対象件数: {{ number_format($summary['count']) }} 件</span>
        <span class="sfk-order-summary-chevron" x-text="open ? '▲' : '▼'" aria-hidden="true">▲</span>
    </button>

    <dl class="sfk-order-summary-body" x-show="open">
        @foreach ($summary['sums'] as $column => $sum)
            <div class="sfk-order-summary-cell">
                <dt>{{ OrderLayout::label($column) }}</dt>
                <dd @class([$sign($sum) => $column === 'estimated_profit'])>{{ OrderFormat::yen($sum) }}</dd>
            </div>
        @endforeach
        <div class="sfk-order-summary-cell">
            <dt>利益率</dt>
            <dd @class([$summary['margin'] !== null ? $sign((float) $summary['margin']) : ''])>{{ $summary['margin'] !== null ? $summary['margin'].'%' : '—' }}</dd>
        </div>
    </dl>
</section>
