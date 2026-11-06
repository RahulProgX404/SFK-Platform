{{-- 上位の売上先 (Modules\Analytics\Filament\Widgets\TopCustomersCard) --}}
@php
    use Modules\Analytics\Filament\Dashboard\DashboardCard;
    use Modules\Core\Filament\Support\AppFormat;
@endphp

<div class="sfk-dash-cell sfk-dash-span-4">
    <section class="sfk-dash-card" aria-labelledby="sfk-top-customers-title">
        <header class="sfk-dash-card-head">
            <h2 class="sfk-dash-card-title" id="sfk-top-customers-title">上位の売上先</h2>
            @if ($salesUrl !== null)
                <a href="{{ $salesUrl }}" class="sfk-dash-more" title="売上入力を開く"><x-filament::icon icon="heroicon-m-arrow-right" /><span class="fi-sr-only">売上入力を開く</span></a>
            @endif
        </header>

        @forelse ($customers as $index => $customer)
            @if ($loop->first)
                <ol class="sfk-dash-rank">
            @endif
            <li class="sfk-dash-rank-row is-{{ $index + 1 }}" title="{{ $customer['code'] ? $customer['code'].' ' : '' }}{{ $customer['name'] }}">
                <span class="sfk-dash-avatar">{{ DashboardCard::initials($customer['name']) }}</span>
                <span class="sfk-dash-rank-text">
                    <strong>{{ $customer['name'] }}</strong>
                    <small>合計：{{ AppFormat::yen($customer['amount']) }}</small>
                </span>
                <span class="sfk-dash-rank-count"><b>{{ number_format($customer['count']) }}</b>件</span>
            </li>
            @if ($loop->last)
                </ol>
            @endif
        @empty
            <p class="sfk-dash-empty">この期間の売上はありません。</p>
        @endforelse
    </section>
</div>
