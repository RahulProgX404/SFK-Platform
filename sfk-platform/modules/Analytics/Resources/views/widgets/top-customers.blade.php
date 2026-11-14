{{-- 上位の売上先 (Modules\Analytics\Filament\Widgets\TopCustomersCard): the customers with the most sales in the period —
     place, name and number of sales, the amount on the right and a bar against the first (core::filament.portal,
     sfk-dash-ranking) --}}
@php
    use Modules\Analytics\Filament\Dashboard\DashboardTips;
    use Modules\Core\Filament\Support\AppFormat;

    $top = max(1, (int) ($customers[0]['amount'] ?? 0));
@endphp

<div class="sfk-dash-cell sfk-dash-span-30p is-fit">
    <section class="sfk-dash-card" aria-labelledby="sfk-top-customers-title">
        <header class="sfk-dash-card-head">
            <div class="sfk-dash-card-titles">
                <h2 class="sfk-dash-card-title" id="sfk-top-customers-title">上位の売上先</h2>
                <span class="sfk-dash-caption"><span class="sfk-dash-tag is-selected">選択期間</span>{{ $this->periodCaption() }}</span>
            </div>
            @if ($salesUrl !== null)
                <a href="{{ $salesUrl }}" class="sfk-dash-more" x-tooltip="{{ \Modules\Core\Filament\Support\Tip::text('売上入力を開く')->x() }}"><x-filament::icon icon="heroicon-m-arrow-right" /><span class="fi-sr-only">売上入力を開く</span></a>
            @endif
        </header>

        @forelse ($customers as $index => $customer)
            @if ($loop->first)
                <ol class="sfk-dash-ranking">
            @endif
            @php($tip = DashboardTips::ranking(($customer['code'] ? $customer['code'].' ' : '').$customer['name'], DashboardTips::share($customer['amount'], $whole)))
            <li tabindex="0" data-sfk-tip x-tooltip="{{ $tip->x() }}">
                <span @class(['sfk-dash-rankno', 'is-'.($index + 1)])>{{ $index + 1 }}</span>
                <span class="sfk-dash-ranking-name">
                    <strong>{{ $customer['name'] }}</strong>
                    <small>{{ number_format($customer['count']) }}件</small>
                </span>
                <span class="sfk-dash-ranking-figure"><strong>{{ AppFormat::yen($customer['amount']) }}</strong></span>
                <span class="sfk-dash-meter" aria-hidden="true"><i style="width: {{ max(2, (int) round($customer['amount'] / $top * 100)) }}%"></i></span>
            </li>
            @if ($loop->last)
                </ol>
            @endif
        @empty
            <p class="sfk-dash-empty">この期間の売上はありません。</p>
        @endforelse
    </section>
</div>
