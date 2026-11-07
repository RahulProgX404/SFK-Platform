{{-- 勤務時間・収入の推移 (Modules\Analytics\Filament\Widgets\WorkTrendCard; personal-growth.png): the user's 勤務時間 and
     収入 per month of the chosen year, as two charts side by side (each with its own axis) --}}
@php
    use Modules\Analytics\Filament\Dashboard\TrendChart;
    use Modules\Core\Filament\Support\AppFormat;
@endphp

<div class="sfk-dash-cell sfk-dash-span-12">
    @include('analytics::widgets.partials.trend-style')

    <section class="sfk-dash-card" aria-labelledby="sfk-work-trend-title">
        <header class="sfk-dash-card-head">
            <h2 class="sfk-dash-card-title" id="sfk-work-trend-title">勤務時間・収入の推移</h2>
            <div class="sfk-growth-tools">
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

        <div class="sfk-growth-pair">
            <div>
                <dl class="sfk-growth-figures">
                    <div>
                        <dt>{{ $shownYear }}年の勤務時間</dt>
                        <dd>{{ TrendChart::trim($totalHours) }}<small>時間</small></dd>
                    </div>
                </dl>
                @include('analytics::widgets.partials.trend-chart', ['chart' => $hoursChart, 'chartId' => 'sfk-work-hours', 'chartLabel' => '勤務時間の推移（月別）'])
            </div>
            <div>
                <dl class="sfk-growth-figures">
                    <div>
                        <dt>{{ $shownYear }}年の収入</dt>
                        <dd>{{ AppFormat::yen($totalIncome) }}</dd>
                    </div>
                </dl>
                @include('analytics::widgets.partials.trend-chart', ['chart' => $incomeChart, 'chartId' => 'sfk-work-income', 'chartLabel' => '収入の推移（月別）'])
            </div>
        </div>
    </section>
</div>
