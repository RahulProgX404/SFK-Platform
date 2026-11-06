{{--
    A table of 売上分析 (analytics::filament.analysis): a row per 年度 (May–April; the months still to come in the current
    year are 「－」; a month opens its 売上先), then 予想 and 仕掛 of the current year — typed in place where $editable.
    $staffId: the 担当者, null for every 担当者; $rows: list<{year, months}>; $planned: {forecast, wip} month => amount
--}}
@php
    $open = $sales['detail'];
@endphp

<div class="sfk-an-card">
    <table class="sfk-an-table">
        <thead>
            <tr>
                <th>年度</th>
                @foreach ($months as $month)
                    <th>{{ $monthLabel($month) }}</th>
                @endforeach
                <th class="is-total">合計</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr @class(['is-current' => $row['year'] === $currentYear]) wire:key="row-{{ $staffId ?? 'all' }}-{{ $row['year'] }}">
                    <td>{{ $row['year'] }}年度</td>
                    @foreach ($months as $index => $month)
                        @if ($row['year'] === $currentYear && $index >= $futureFrom)
                            <td class="is-future">－</td>
                        @else
                            <td>
                                <button type="button" wire:click="showDetail({{ $staffId ?? 'null' }}, {{ $row['year'] }}, {{ $month }})"
                                    @class(['sfk-an-link', 'is-open' => $open !== null && $open['staff'] === $staffId && $open['year'] === $row['year'] && $open['month'] === $month])
                                    title="{{ $row['year'] }}年度 {{ $month }}月の売上先">{{ $yen($row['months'][$month]) }}</button>
                            </td>
                        @endif
                    @endforeach
                    <td class="is-total">{{ $yen(array_sum($row['months'])) }}</td>
                </tr>
            @endforeach

            @foreach (\Modules\Analytics\Models\SalesForecast::KINDS as $kind => $label)
                <tr class="is-plan" wire:key="plan-{{ $staffId ?? 'all' }}-{{ $kind }}">
                    <td>{{ $label }}</td>
                    @foreach ($months as $month)
                        @php($amount = $planned[$kind][$month])
                        <td>
                            @if ($editable)
                                <div x-data="{ editing: false, value: @js($amount), save() { this.editing = false; if (Number(this.value) !== @js($amount)) { $wire.saveForecast({{ $staffId }}, @js($kind), {{ $month }}, this.value) } } }"
                                    wire:key="cell-{{ $staffId }}-{{ $kind }}-{{ $month }}-{{ $amount }}">
                                    <button type="button" class="sfk-an-edit" x-show="! editing" x-on:click="editing = true; setTimeout(() => { $refs.input.focus(); $refs.input.select() })"
                                        title="{{ $currentYear }}年度 {{ $month }}月の{{ $label }}">{{ $yen($amount) }}</button>
                                    <input type="number" class="sfk-an-input" x-ref="input" x-show="editing" x-cloak x-model="value" step="1"
                                        x-on:keydown.enter.prevent="save()" x-on:keydown.escape="editing = false; value = @js($amount)" x-on:blur="save()"
                                        aria-label="{{ $currentYear }}年度 {{ $month }}月の{{ $label }}">
                                </div>
                            @else
                                {{ $yen($amount) }}
                            @endif
                        </td>
                    @endforeach
                    <td class="is-total">{{ $yen(array_sum($planned[$kind])) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
