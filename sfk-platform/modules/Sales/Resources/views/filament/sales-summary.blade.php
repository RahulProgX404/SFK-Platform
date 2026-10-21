{{-- 集計サマリー under the search row of the list of 06. 売上入力 (Modules\Sales\Filament\Resources\Sales\Pages\ListSales::salesSummary()); the look of 04's summary (orders::filament.styles). --}}
@php
    use Modules\Sales\Filament\SalesFormat;

    $summary = $page->salesSummary();
    $sign = fn (int|float|null $value): string => $value === null ? '' : ($value > 0 ? 'is-positive' : ($value < 0 ? 'is-negative' : ''));
@endphp

<section class="sfk-order-summary" x-data="{ open: true }" aria-label="集計サマリー">
    <button type="button" class="sfk-order-summary-bar" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'">
        <span class="sfk-order-summary-title">集計サマリー</span>
        <span class="sfk-order-summary-count">対象件数: {{ number_format($summary['count']) }} 件　売上数量: {{ SalesFormat::number($summary['quantity']) ?: '0' }}　売上残数量: {{ SalesFormat::number($summary['remaining']) ?: '0' }}</span>
        <span class="sfk-order-summary-chevron" x-text="open ? '▲' : '▼'" aria-hidden="true">▲</span>
    </button>

    <dl class="sfk-order-summary-body" x-show="open">
        @foreach ($summary['rows'] as [$label, $kind, $value])
            <div class="sfk-order-summary-cell">
                <dt>{{ $label }}</dt>
                <dd @class([$sign($value) => $kind !== 'yen'])>
                    @if ($kind === 'percent')
                        {{ $value !== null ? $value.'%' : '—' }}
                    @else
                        {{ SalesFormat::yen($value) }}
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>
</section>
