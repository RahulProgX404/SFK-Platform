{{--
    会社カレンダー (Modules\Core\Filament\Calendar\Concerns\ShowsCompanyCalendar): ‹ 年 ›, the 12 months from Sunday and the
    legend. $editable: a click cycles the day (平日 → 祝日 → 会社休日 → 半日出勤) and saves it ($wire.setDay), the screen
    first and back with a red ring when saving fails; otherwise the days are only shown.
    $selectedYear, $version, $months: list<{label, cells: list<{date, day, sunday}|null>}>, $days: date => type (marked),
    $cycle: type => next type ('' = 平日), $labels: type => label, $today, $weekdays
--}}
<style>
    /* 会社カレンダー: 12 small months (tokens from core::filament.design) */
    .sfk-cal { display: flex; flex-direction: column; gap: var(--sfk-space-4); }
    .sfk-cal-year { display: flex; align-items: center; gap: var(--sfk-space-2); }
    .sfk-cal-year-label { min-width: 5rem; text-align: center; color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); font-variant-numeric: tabular-nums; }
    .sfk-cal-months { display: grid; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); gap: var(--sfk-space-4); }
    .sfk-cal-month { padding: var(--sfk-space-3); background: var(--sfk-surface); border-radius: var(--sfk-radius-lg); box-shadow: var(--sfk-ring); }
    .sfk-cal-month-title { margin: 0 0 var(--sfk-space-2); text-align: center; color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); }
    .sfk-cal-days { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); row-gap: var(--sfk-space-1); text-align: center; }
    .sfk-cal-weekday { padding-bottom: var(--sfk-space-1); color: var(--sfk-muted); font-size: var(--sfk-fs-badge); font-weight: var(--sfk-fw-medium); }
    .sfk-cal-weekday.is-sunday { color: var(--sfk-danger); }
    .sfk-cal-day { position: relative; display: flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; margin: 0 auto; border-radius: 9999px;
        color: var(--sfk-text); font-size: var(--sfk-fs-small); font-variant-numeric: tabular-nums; transition: background-color .15s, color .15s; }
    button.sfk-cal-day:hover { background: var(--sfk-hover); }
    .sfk-cal-day:focus-visible { outline: 2px solid var(--sfk-primary); outline-offset: 1px; }
    .sfk-cal-day.is-national { color: var(--sfk-danger); font-weight: var(--sfk-fw-semibold); }
    .sfk-cal-day.is-company { color: var(--sfk-danger); font-weight: var(--sfk-fw-semibold); box-shadow: 0 0 0 1px var(--sfk-danger); }
    .sfk-cal-day.is-half { color: var(--sfk-warning); font-weight: var(--sfk-fw-semibold); }
    .sfk-cal-day.is-today { outline: 2px solid var(--sfk-primary); outline-offset: 1px; }
    .sfk-cal-day.is-failed { box-shadow: 0 0 0 2px var(--sfk-danger); }
    .sfk-cal-half-mark { position: absolute; bottom: -2px; font-size: 8px; line-height: 1; }
    .sfk-cal-error { margin: 0; color: var(--sfk-danger-text, var(--sfk-danger)); font-size: var(--sfk-fs-small); }
    .sfk-cal-legend { display: flex; flex-wrap: wrap; align-items: center; gap: var(--sfk-space-2) var(--sfk-space-4); color: var(--sfk-muted); font-size: var(--sfk-fs-small); }
    .sfk-cal-legend-red { color: var(--sfk-danger); font-weight: var(--sfk-fw-semibold); }
    .sfk-cal-legend-circle { padding: 0 6px; border-radius: 9999px; color: var(--sfk-danger); font-weight: var(--sfk-fw-semibold); box-shadow: 0 0 0 1px var(--sfk-danger); }
    .sfk-cal-legend-half { color: var(--sfk-warning); font-weight: var(--sfk-fw-semibold); }
    .sfk-cal-legend-today { display: inline-block; width: 12px; height: 12px; margin-right: 6px; vertical-align: -1px; border-radius: 9999px; outline: 2px solid var(--sfk-primary); outline-offset: 1px; }
</style>

@php
    $typeClass = ['national_holiday' => 'is-national', 'company_holiday' => 'is-company', 'half_day' => 'is-half'];
    $typeOf = fn (array $cell): string => $days[$cell['date']] ?? ($cell['sunday'] ? 'national_holiday' : '');
    $title = fn (array $cell): string => implode(' ・ ', array_filter([$labels[$typeOf($cell)] ?? null, $cell['date'] === $today ? '本日' : null])) ?: '平日';
@endphp

<div class="sfk-cal">
    <div class="sfk-cal-year">
        <x-filament::icon-button icon="heroicon-m-chevron-left" color="gray" label="前の年" wire:click="previousYear" />
        <span class="sfk-cal-year-label">{{ $selectedYear }}年</span>
        <x-filament::icon-button icon="heroicon-m-chevron-right" color="gray" label="次の年" wire:click="nextYear" />
    </div>

    <div
        wire:key="sfk-cal-{{ $selectedYear }}-{{ $version }}"
        class="sfk-cal"
        @if ($editable)
            x-data="{
                days: @js((object) $days),
                cycle: @js($cycle),
                labels: @js($labels),
                today: @js($today),
                failed: null,
                error: null,
                queue: Promise.resolve(),
                typeOf(date, sunday) { return this.days[date] ?? (sunday ? 'national_holiday' : '') },
                classes(date, sunday) {
                    const type = this.typeOf(date, sunday)
                    return { 'is-national': type === 'national_holiday', 'is-company': type === 'company_holiday', 'is-half': type === 'half_day', 'is-failed': this.failed === date }
                },
                title(date, sunday) {
                    return [this.labels[this.typeOf(date, sunday)], date === this.today ? '本日' : null].filter(Boolean).join(' ・ ') || '平日'
                },
                toggle(date, sunday) {
                    const next = this.cycle[this.typeOf(date, sunday)]
                    const previous = { ...this.days }
                    if (next === '') { delete this.days[date] } else { this.days[date] = next }
                    this.failed = null
                    this.error = null
                    // saved in click order, so quick clicks on one day cannot land out of order
                    this.queue = this.queue.then(() => $wire.setDay(date, next)).then((message) => {
                        if (message) { this.days = previous; this.failed = date; this.error = message }
                    }, () => { this.days = previous; this.failed = date; this.error = '更新できませんでした。' })
                },
            }"
        @endif
    >
        <div class="sfk-cal-months">
            @foreach ($months as $month)
                <section class="sfk-cal-month" aria-label="{{ $selectedYear }}年{{ $month['label'] }}">
                    <h3 class="sfk-cal-month-title">{{ $month['label'] }}</h3>
                    <div class="sfk-cal-days">
                        @foreach ($weekdays as $index => $weekday)
                            <div @class(['sfk-cal-weekday', 'is-sunday' => $index === 0])>{{ $weekday }}</div>
                        @endforeach
                        @foreach ($month['cells'] as $cell)
                            @if ($cell === null)
                                <div></div>
                            @elseif ($editable)
                                @php($args = "'".$cell['date']."', ".($cell['sunday'] ? 'true' : 'false'))
                                <button type="button" data-date="{{ $cell['date'] }}" title="{{ $title($cell) }}"
                                    @class(['sfk-cal-day', $typeClass[$typeOf($cell)] ?? null, 'is-today' => $cell['date'] === $today])
                                    :class="classes({{ $args }})" :title="title({{ $args }})" :aria-label="'{{ $cell['day'] }}日 ' + title({{ $args }})"
                                    x-on:click="toggle({{ $args }})">
                                    {{ $cell['day'] }}<span class="sfk-cal-half-mark" x-show="typeOf({{ $args }}) === 'half_day'" @style(['display: none' => $typeOf($cell) !== 'half_day']) aria-hidden="true">△</span>
                                </button>
                            @else
                                <span data-date="{{ $cell['date'] }}" title="{{ $title($cell) }}" aria-label="{{ $cell['day'] }}日 {{ $title($cell) }}"
                                    @class(['sfk-cal-day', $typeClass[$typeOf($cell)] ?? null, 'is-today' => $cell['date'] === $today])>
                                    {{ $cell['day'] }}@if ($typeOf($cell) === 'half_day')<span class="sfk-cal-half-mark" aria-hidden="true">△</span>@endif
                                </span>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        @if ($editable)
            <p class="sfk-cal-error" x-show="error" x-text="error" style="display: none" role="alert"></p>
        @endif

        <div class="sfk-cal-legend">
            <span><span class="sfk-cal-legend-red">赤字</span>＝祝日・日曜</span>
            <span><span class="sfk-cal-legend-circle">◯</span>＝会社休日</span>
            <span><span class="sfk-cal-legend-half">△</span>＝半日出勤（午前のみ）</span>
            <span><span class="sfk-cal-legend-today" aria-hidden="true"></span>＝本日</span>
        </div>
    </div>
</div>
