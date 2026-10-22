{{-- 合計 under the search row of the lists of 23. 売掛管理 / 23-2. 売掛管理(締日別) (Modules\Sales\Filament\Resources\Receivables\Pages\ListReceivableMonths::receivableTotals()); the look of 06's 集計サマリー (orders::filament.styles). --}}
@php
    use Modules\Core\Filament\Support\AppFormat;

    $totals = $page->receivableTotals();
@endphp

<section class="sfk-order-summary" x-data="{ open: true }" aria-label="合計">
    <button type="button" class="sfk-order-summary-bar" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'">
        <span class="sfk-order-summary-title">合計</span>
        <span class="sfk-order-summary-count">対象件数: {{ number_format($totals['count']) }} 件</span>
        <span class="sfk-order-summary-chevron" x-text="open ? '▲' : '▼'" aria-hidden="true">▲</span>
    </button>

    <dl class="sfk-order-summary-body" x-show="open">
        @foreach ($totals['rows'] as [$label, $value])
            <div class="sfk-order-summary-cell">
                <dt>{{ $label }}</dt>
                <dd>{{ AppFormat::yen($value) }}</dd>
            </div>
        @endforeach
    </dl>
</section>
