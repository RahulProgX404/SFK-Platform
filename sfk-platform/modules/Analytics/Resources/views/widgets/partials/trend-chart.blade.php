{{-- A 12-month line chart (Modules\Analytics\Filament\Dashboard\TrendChart; personal-growth.png): dashed grid, the y
     axis on the left, the months below, smooth lines over a soft area; hovering (or ← → on the focused plot) shows a
     crosshair with each line's value. An incomplete month (TrendChart `notes`) is reached by a dotted line and ringed;
     months without data are not drawn (「データなし」 in the tooltip). The tooltip is the SFK UI information tooltip's
     look (sfk-tip-panel): the month, each line with its colour and its value aligned, then — with TrendChart `compare` —
     差額 and 前年同月比. A tap shows it too (a tap outside closes it); keyboard users hear the month's values (aria-live).
     The same values are in a table for screen readers.
     Needs: $chart (TrendChart::build()), $chartId (unique on the page), $chartLabel (what the chart shows). --}}
@php
    $payload = array_map(fn (array $line): array => [
        'label' => $line['label'],
        'color' => $line['color'],
        'values' => $line['values'],
        'points' => $line['points'],
    ], $chart['series']);
    $compare = $chart['compare'] ?? array_fill(0, 12, null);
@endphp

<div class="sfk-trend"
    wire:key="{{ $chartId }}-{{ md5(json_encode($payload)) }}"
    x-data="{ i: null, months: @js($chart['months']), lefts: @js($chart['lefts']), series: @js($payload), compare: @js($compare), at(event) { const box = this.$refs.plot.getBoundingClientRect(); this.i = Math.max(0, Math.min(11, Math.round((event.clientX - box.left) / box.width * 11))); }, step(by) { this.i = this.i === null ? (by > 0 ? 0 : 11) : Math.max(0, Math.min(11, this.i + by)); }, shown() { return this.i !== null && this.series.some((line) => line.values[this.i] !== null); }, spoken() { if (! this.shown()) { return ''; } const c = this.compare[this.i]; return [this.months[this.i], ...this.series.filter((line) => line.values[this.i] !== null).map((line) => line.label + ' ' + line.values[this.i]), ...(c && c.difference ? ['差額 ' + c.difference, ...(c.change ? ['前年同月比 ' + c.change] : [])] : (c && c.note ? [c.note] : []))].join('、'); } }">
    <div class="sfk-trend-axis" aria-hidden="true">
        @foreach ($chart['ticks'] as $tick)
            <span style="top: {{ $tick['top'] }}%">{{ $tick['label'] }}</span>
        @endforeach
    </div>

    <div class="sfk-trend-plot" x-ref="plot" tabindex="0" aria-label="{{ $chartLabel }}"
        x-on:mousemove="at($event)" x-on:pointerdown="at($event)" x-on:mouseleave="i = null" x-on:blur="i = null" x-on:click.outside="i = null"
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
                @if ($line['area'] !== '' && ! $line['dashed'])
                    <path d="{{ $line['area'] }}" fill="url(#{{ $chartId }}-{{ $line['key'] }}-fill)" stroke="none" />
                @endif
            @endforeach
            @foreach ($chart['series'] as $line)
                @if ($line['line'] !== '')
                    <path d="{{ $line['line'] }}" fill="none" stroke="{{ $line['color'] }}" stroke-width="{{ $line['dashed'] ? '1.75' : '2.5' }}" @if ($line['dashed']) stroke-dasharray="5 4" @endif stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                @endif
                @if ($line['partialLine'] !== '')
                    <path d="{{ $line['partialLine'] }}" fill="none" stroke="{{ $line['color'] }}" stroke-width="{{ $line['dashed'] ? '1.75' : '2.5' }}" stroke-dasharray="1 5" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                @endif
            @endforeach
        </svg>

        {{-- incomplete months: a ring on the point --}}
        @foreach ($chart['series'] as $line)
            @foreach ($line['points'] as $point)
                @if ($point !== null && $point['partial'])
                    <i class="sfk-trend-ring" style="left: {{ $point['left'] }}%; top: {{ $point['top'] }}%; --sfk-ring-color: {{ $line['color'] }}" aria-hidden="true"></i>
                @endif
            @endforeach
        @endforeach

        <template x-if="shown()">
            <div aria-hidden="true">
                <i class="sfk-trend-cross" x-bind:style="`left: ${lefts[i]}%`"></i>
                <template x-for="line in series" x-bind:key="line.label">
                    <i class="sfk-trend-dot" x-show="line.points[i] !== null" x-bind:style="line.points[i] ? `left: ${line.points[i].left}%; top: ${line.points[i].top}%; background: ${line.color}` : ''"></i>
                </template>
                <div class="sfk-trend-tip sfk-tip-panel" x-bind:class="{ 'is-left': i > 5 }" x-bind:style="`left: ${lefts[i]}%`">
                    <p class="sfk-tip-title" x-text="months[i]"></p>
                    <dl class="sfk-tip-rows">
                        <template x-for="line in series" x-bind:key="line.label">
                            <div x-show="line.values[i] !== null" x-bind:class="{ 'is-none': line.points[i] === null }"><dt><i class="sfk-tip-dot" x-bind:style="`background: ${line.color}`"></i><span x-text="line.label"></span></dt><dd x-text="line.values[i]"></dd></div>
                        </template>
                        <template x-if="compare[i] && compare[i].difference">
                            <div class="sfk-tip-divider" role="separator"></div>
                        </template>
                        <template x-if="compare[i] && compare[i].difference">
                            <div><dt>差額</dt><dd x-bind:class="`is-${compare[i].tone}`" x-text="compare[i].difference"></dd></div>
                        </template>
                        <template x-if="compare[i] && compare[i].change">
                            <div><dt>前年同月比</dt><dd x-bind:class="`is-${compare[i].tone}`" x-text="compare[i].change"></dd></div>
                        </template>
                    </dl>
                    <template x-if="compare[i] && compare[i].note">
                        <p class="sfk-tip-note" x-text="compare[i].note"></p>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <p class="fi-sr-only" aria-live="polite" x-text="spoken()"></p>

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
