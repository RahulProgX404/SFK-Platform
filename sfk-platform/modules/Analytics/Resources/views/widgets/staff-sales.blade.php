{{-- 担当者別売上 (Modules\Analytics\Filament\Widgets\StaffSalesCard): the 担当者 by 売上金額, each with a bar against the
     first (sfk-dash-ranking); 個人 ($latest): the user's own newest sales --}}
@php
    use Modules\Analytics\Filament\Dashboard\DashboardCard;
    use Modules\Core\Filament\Support\AppFormat;
@endphp

<div class="sfk-dash-cell sfk-dash-span-4">
    <section class="sfk-dash-card" aria-labelledby="sfk-staff-sales-title">
        <header class="sfk-dash-card-head">
            <div class="sfk-dash-card-titles">
                <h2 class="sfk-dash-card-title" id="sfk-staff-sales-title">{{ $latest === null ? '担当者別売上' : '担当した売上' }}</h2>
                <span class="sfk-dash-caption">{{ $this->periodCaption() }}</span>
            </div>
            @if ($analysisUrl !== null)
                <a href="{{ $analysisUrl }}" class="sfk-dash-more" title="売上分析を見る"><x-filament::icon icon="heroicon-m-arrow-right" /><span class="fi-sr-only">売上分析を見る</span></a>
            @endif
        </header>

        @if ($latest !== null)
            @forelse ($latest as $index => $sale)
                @if ($loop->first)
                    <ul class="sfk-dash-people">
                @endif
                <li wire:key="latest-sale-{{ $sale['id'] }}">
                    <span class="sfk-dash-avatar is-solid is-{{ $index + 1 }}">{{ DashboardCard::initials($sale['customer']) }}</span>
                    @if ($sale['url'] !== null)
                        <a href="{{ $sale['url'] }}" class="sfk-dash-people-text" title="レコードを開く">
                    @else
                        <span class="sfk-dash-people-text">
                    @endif
                        <strong>{{ $sale['customer'] }}</strong>
                        <small>{{ $sale['sold_on'] }}{{ filled($sale['product']) ? '・'.$sale['product'] : '' }}</small>
                    @if ($sale['url'] !== null)
                        </a>
                    @else
                        </span>
                    @endif
                    <span class="sfk-dash-people-figure">
                        <small>売上金額</small>
                        <strong>{{ AppFormat::yen($sale['amount']) }}</strong>
                    </span>
                </li>
                @if ($loop->last)
                    </ul>
                @endif
            @empty
                <p class="sfk-dash-empty">この期間の売上はありません。</p>
            @endforelse
        @else
            @php
                $topAmount = max(1, (int) ($staff[0]['amount'] ?? 0));
            @endphp
            @forelse ($staff as $index => $person)
                @if ($loop->first)
                    <ol class="sfk-dash-ranking">
                @endif
                <li>
                    <span @class(['sfk-dash-rankno', 'is-'.($index + 1)])>{{ $index + 1 }}</span>
                    <span class="sfk-dash-ranking-name">
                        <strong>{{ $person['name'] }}</strong>
                        <small>売上件数 {{ number_format($person['count']) }}件</small>
                    </span>
                    <span class="sfk-dash-ranking-figure"><strong>{{ AppFormat::yen($person['amount']) }}</strong></span>
                    <span class="sfk-dash-meter" aria-hidden="true"><i style="width: {{ max(2, (int) round($person['amount'] / $topAmount * 100)) }}%"></i></span>
                </li>
                @if ($loop->last)
                    </ol>
                @endif
            @empty
                <p class="sfk-dash-empty">この期間の売上はありません。</p>
            @endforelse
        @endif
    </section>
</div>
