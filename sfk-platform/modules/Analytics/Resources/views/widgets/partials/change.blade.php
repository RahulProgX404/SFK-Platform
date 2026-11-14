{{-- A comparison chip of the dashboard (core::filament.portal `.sfk-dash-trend`), the same on every card: green ▲ up,
     red ▼ down, grey ±0 — what it is compared with is its label (前期間比, 前年同期比 …). DashboardCard::change() is null
     without a valid baseline: then there is no chip. With $tip (DashboardTips::comparison) hover, focus or a tap shows
     both periods, both values, the difference and the calculation.
     Needs: $label, $change (float|null); optional $tip (Modules\Core\Filament\Support\Tip). --}}
@if ($change !== null)
    @php
        $direction = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat');
        $percent = abs($change) >= 1000 ? '1,000%超' : number_format(abs($change), 1).'%';
    @endphp
    <span class="sfk-dash-trend is-{{ $direction }}" @isset($tip) tabindex="0" data-sfk-tip x-tooltip="{{ $tip->x() }}" @endisset>{{ $label }} {{ ['up' => '▲', 'down' => '▼', 'flat' => '±'][$direction] }}{{ $direction === 'flat' ? '0.0%' : $percent }}</span>
@endif
