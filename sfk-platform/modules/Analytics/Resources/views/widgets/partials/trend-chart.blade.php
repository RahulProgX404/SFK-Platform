{{-- A 12-month line chart (Modules\Analytics\Filament\Dashboard\TrendChart; personal-growth.png): dashed grid, the y
     axis on the left, the months below, smooth lines over a soft area; hovering (or ← → on the focused plot) shows a
     crosshair with each line's value. The same values are in a table for screen readers.
     Needs: $chart (TrendChart::build()), $chartId (unique on the page), $chartLabel (what the chart shows). --}}
@php
    $payload = array_map(fn (array $line): array => [
        'label' => $line['label'],
        'color' => $line['color'],
        'values' => $line['values'],
        'points' => $line['points'],
    ], $chart['series']);
@endphp

<div class="sfk-trend"
    wire:key="{{ $chartId }}-{{ md5(json_encode($payload)) }}"
    x-data="{ i: null, months: @js($chart['months']), lefts: @js($chart['lefts']), series: @js($payload), at(event) { const box = this.$refs.plot.getBoundingClientRect(); this.i = Math.max(0, Math.min(11, Math.round((event.clientX - box.left) / box.width * 11))); }, step(by) { this.i = this.i === null ? (by > 0 ? 0 : 11) : Math.max(0, Math.min(11, this.i + by)); }, shown() { return this.i !== null && this.series.some((line) => line.values[this.i] !== null); } }">
    <div class="sfk-trend-axis" aria-hidden="true">
        @foreach ($chart['ticks'] as $tick)
            <span style="top: {{ $tick['top'] }}%">{{ $tick['label'] }}</span>
        @endforeach
    </div>

    <div class="sfk-trend-plot" x-ref="plot" tabindex="0" aria-label="{{ $chartLabel }}"
        x-on:mousemove="at($event)" x-on:mouseleave="i = null" x-on:blur="i = null"
        x-on:keydown.arrow-right.prevent="step(1)" x-on:keydown.arrow-left.prevent="step(-1)" x-on:keydown.escape="i = null">
        @foreach ($chart['ticks'] as $tick)
            <i @class(['sfk-trend-grid', 'is-base' => $loop->last]) style="top: {{ $tick['top'] }}%" aria-hidden="true"></i>
        @endforeach

        <svg class="sfk-trend-svg" viewBox="0 0 {{ \Modules\Analytics\Filament\Dashboard\TrendChart::WIDTH }} {{ \Modules\Analytics\Filament\Dashboard\TrendChart::HEIGHT }}" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                @foreach ($chart['series'] as $line)
                    <linearGradient id="{{ $chartId }}-{{ $line['key'] }}-fill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="{{ $line['color'] }}" stop-opacity=".16" />
                        <stop offset="100%" stop-color="{{ $line['color'] }}" stop-opacity="0" />
                    </linearGradient>
                @endforeach
            </defs>
            @foreach ($chart['series'] as $line)
                @if ($line['area'] !== '')
                    <path d="{{ $line['area'] }}" fill="url(#{{ $chartId }}-{{ $line['key'] }}-fill)" stroke="none" />
                @endif
            @endforeach
            @foreach ($chart['series'] as $line)
                @if ($line['line'] !== '')
                    <path d="{{ $line['line'] }}" fill="none" stroke="{{ $line['color'] }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                @endif
            @endforeach
        </svg>

        <template x-if="shown()">
            <div aria-hidden="true">
                <i class="sfk-trend-cross" x-bind:style="`left: ${lefts[i]}%`"></i>
                <template x-for="line in series" x-bind:key="line.label">
                    <i class="sfk-trend-dot" x-show="line.points[i] !== null" x-bind:style="line.points[i] ? `left: ${line.points[i].left}%; top: ${line.points[i].top}%; background: ${line.color}` : ''"></i>
                </template>
                <div class="sfk-trend-tip" x-bind:class="{ 'is-left': i > 7 }" x-bind:style="`left: ${lefts[i]}%`">
                    <strong x-text="months[i]"></strong>
                    <template x-for="line in series" x-bind:key="line.label">
                        <span class="sfk-trend-tip-row" x-show="line.values[i] !== null"><i x-bind:style="`background: ${line.color}`"></i><span x-text="line.label"></span><b x-text="line.values[i]"></b></span>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <div class="sfk-trend-months" aria-hidden="true">
        @foreach ($chart['months'] as $index => $month)
            <span style="left: {{ $chart['lefts'][$index] }}%">{{ $month }}</span>
        @endforeach
    </div>

    <table class="fi-sr-only">
        <caption>{{ $chartLabel }}</caption>
        <thead>
            <tr>
                <th scope="col">月</th>
                @foreach ($chart['series'] as $line)
                    <th scope="col">{{ $line['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($chart['months'] as $index => $month)
                <tr>
                    <th scope="row">{{ $month }}</th>
                    @foreach ($chart['series'] as $line)
                        <td>{{ $line['values'][$index] ?? '—' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
