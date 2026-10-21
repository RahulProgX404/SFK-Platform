{{--
    Gantt chart of 12. 製袋予定管理 (Modules\Production\Filament\Resources\BagSchedules\Pages\ListBagSchedules::ganttChart()), a
    カスタマイズ形式 view: ‹ 今日 › and the period, the 使用状況 colours, one row per machine with the bookings as bars (one lane
    per overlapping booking); a bar opens its record. The chart scrolls sideways inside its box.
--}}
@php
    $chart = $page->ganttChart();
    $columns = count($chart['columns']);
@endphp

@once
    <style>
        .sfk-gantt { background: #fff; border: 1px solid var(--kt-border); }
        .sfk-gantt-head { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 20px; padding: 12px 16px; border-bottom: 1px solid var(--kt-border); }
        .sfk-gantt-nav { display: flex; align-items: center; gap: 6px; }
        .sfk-gantt-nav button { min-width: 36px; height: 32px; padding: 0 10px; border: 1px solid var(--kt-border); background: #f7f9fa; color: var(--kt-blue); cursor: pointer; }
        .sfk-gantt-nav button:hover { background: #c8d6dd; }
        .sfk-gantt-title { font-weight: 700; color: var(--kt-label); }
        .sfk-gantt-legend { display: flex; gap: 14px; margin-inline-start: auto; font-size: .8125rem; color: var(--kt-label); }
        .sfk-gantt-legend span { display: inline-flex; align-items: center; gap: 6px; }
        .sfk-gantt-legend i { display: inline-block; width: 28px; height: 12px; }
        .sfk-gantt-scroll { overflow-x: auto; }
        .sfk-gantt-grid { min-width: 960px; }
        .sfk-gantt-row { display: grid; grid-template-columns: 200px 1fr; border-bottom: 1px solid #f0f2f3; }
        .sfk-gantt-row.is-head { background: var(--kt-blue-soft); font-weight: 700; color: var(--kt-label); font-size: .75rem; }
        .sfk-gantt-machine { padding: 8px 12px; border-inline-end: 1px solid var(--kt-border); color: var(--kt-label); font-size: .8125rem; }
        .sfk-gantt-cols { display: grid; }
        .sfk-gantt-cols > div { padding: 6px 2px; text-align: center; border-inline-start: 1px solid #e3e7e8; white-space: nowrap; overflow: hidden; }
        .sfk-gantt-track { position: relative; }
        .sfk-gantt-lines { position: absolute; inset: 0; display: grid; pointer-events: none; }
        .sfk-gantt-lines > div { border-inline-start: 1px solid #f0f2f3; }
        .sfk-gantt-bar { position: absolute; height: 26px; padding: 0 6px; overflow: hidden; color: #fff; font-size: .75rem; line-height: 26px; white-space: nowrap; text-overflow: ellipsis; }
        .sfk-gantt-bar:hover { filter: brightness(.92); }
    </style>
@endonce

<section class="sfk-gantt" aria-label="ガントチャート">
    <div class="sfk-gantt-head">
        <div class="sfk-gantt-nav">
            <button type="button" wire:click="shiftGantt(-1)" title="前へ">‹</button>
            <button type="button" wire:click="showGanttToday">今日</button>
            <button type="button" wire:click="shiftGantt(1)" title="次へ">›</button>
        </div>
        <span class="sfk-gantt-title">{{ $chart['title'] }}</span>
        <div class="sfk-gantt-legend">
            @foreach ($chart['legend'] as $status => $color)
                <span><i style="background: {{ $color }}"></i>{{ $status }}</span>
            @endforeach
        </div>
    </div>

    <div class="sfk-gantt-scroll">
        <div class="sfk-gantt-grid">
            <div class="sfk-gantt-row is-head">
                <div class="sfk-gantt-machine">機械名</div>
                <div class="sfk-gantt-cols" style="grid-template-columns: repeat({{ $columns }}, minmax(0, 1fr))">
                    @foreach ($chart['columns'] as $column)
                        <div>{{ $column['label'] }}</div>
                    @endforeach
                </div>
            </div>

            @foreach ($chart['rows'] as $row)
                <div class="sfk-gantt-row">
                    <div class="sfk-gantt-machine">{{ $row['machine'] }}</div>
                    <div class="sfk-gantt-track" style="height: {{ $row['lanes'] * 30 + 6 }}px">
                        <div class="sfk-gantt-lines" style="grid-template-columns: repeat({{ $columns }}, minmax(0, 1fr))">
                            @foreach ($chart['columns'] as $column)
                                <div></div>
                            @endforeach
                        </div>
                        @foreach ($row['bars'] as $bar)
                            @php($style = 'left: '.$bar['left'].'%; width: '.$bar['width'].'%; top: '.($bar['lane'] * 30 + 4).'px; background: '.$bar['item']['color'])
                            @if ($bar['item']['url'] !== null)
                                <a href="{{ $bar['item']['url'] }}" class="sfk-gantt-bar" style="{{ $style }}" title="{{ $bar['item']['title'] }}">{{ $bar['item']['label'] }}</a>
                            @else
                                <span class="sfk-gantt-bar" style="{{ $style }}" title="{{ $bar['item']['title'] }}">{{ $bar['item']['label'] }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
