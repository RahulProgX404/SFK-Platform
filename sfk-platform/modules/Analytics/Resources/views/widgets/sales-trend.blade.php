{{-- 売上の推移 (Modules\Analytics\Filament\Widgets\SalesTrendCard; personal-growth.png): the sales per month of the chosen
     calendar year (solid, with the area) against the year before (dashed), exact amounts on hover (個人: the user's own);
     the total says which days it covers, 前年同期比 the days of the year before it is compared with and their amount --}}
@php
    use Modules\Core\Filament\Support\AppFormat;
@endphp

<div class="sfk-dash-cell sfk-dash-span-{{ $span }}">
    @include('analytics::widgets.partials.trend-style')

    <section class="sfk-dash-card" aria-labelledby="sfk-sales-trend-title">
        <header class="sfk-dash-card-head">
            <div class="sfk-dash-card-titles">
                <h2 class="sfk-dash-card-title" id="sfk-sales-trend-title">売上の推移</h2>
                {{-- the months of a calendar year: the business year (May – April) is in 売上分析 --}}
                <span class="sfk-dash-caption"><span class="sfk-dash-tag">暦年・月別</span>{{ $shownYear }}年1月〜12月</span>
            </div>
            <div class="sfk-growth-tools">
                <ul class="sfk-growth-legend">
                    @foreach ($chart['series'] as $line)
                        <li><i @class(['is-dashed' => $line['dashed']]) style="--sfk-legend: {{ $line['color'] }}" aria-hidden="true"></i>{{ $line['label'] }}</li>
                    @endforeach
                </ul>
                <label class="sfk-growth-year">
                    <x-filament::icon icon="heroicon-o-calendar" />
                    <span class="fi-sr-only">年</span>
                    <select wire:model.live="year">
                        @foreach ($years as $choice)
                            <option value="{{ $choice }}" @selected($choice === $shownYear)>{{ $choice }}年</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </header>

        <dl class="sfk-growth-figures">
            <div>
                <dt>{{ $shownYear }}年の売上 @if ($totalNote !== null)<small class="sfk-growth-days">（{{ $totalNote }}）</small>@endif</dt>
                <dd>{{ AppFormat::yen($total) }}</dd>
                {{-- 前年同期比 over the days both years have data, which may be fewer than the total's --}}
                @if ($comparison['label'] !== null)
                    <dd class="sfk-growth-note">
                        @include('analytics::widgets.partials.change', ['label' => '前年同期比', 'change' => $change])
                        <span>{{ $comparison['days'] }} {{ AppFormat::yen($comparison['now']) }}</span>
                        <span>前年同期（{{ $comparison['label'] }}） {{ AppFormat::yen($comparison['before']) }}</span>
                    </dd>
                @endif
            </div>
        </dl>

        @include('analytics::widgets.partials.trend-chart', ['chart' => $chart, 'chartId' => 'sfk-sales-trend', 'chartLabel' => '売上の推移（月別）'])
    </section>
</div>
