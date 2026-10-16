{{--
    カレンダー形式 of an app list (Modules\Core\Filament\AppPages\AppListRecords::calendarWeeks()): ‹ 2026年10月 › 今日,
    a Sunday-first month with the records of each day by the view's 日付, shown by its タイトル and linked to the record.
--}}
@php
    use Filament\Support\Icons\Heroicon;

    $page = $getLivewire();
    $month = $page->calendarMonthStart();
@endphp

<section class="sfk-calendar-view" aria-label="カレンダー">
    <div class="sfk-calendar-view-head">
        <button type="button" class="sfk-app-pager-btn" wire:click="shiftCalendarMonth(-1)" title="前の月">
            <x-filament::icon :icon="Heroicon::ChevronLeft" class="sfk-app-pager-icon" />
            <span class="fi-sr-only">前の月</span>
        </button>
        <h2 class="sfk-calendar-view-month">{{ $month->format('Y') }}年{{ $month->month }}月</h2>
        <button type="button" class="sfk-app-pager-btn" wire:click="shiftCalendarMonth(1)" title="次の月">
            <x-filament::icon :icon="Heroicon::ChevronRight" class="sfk-app-pager-icon" />
            <span class="fi-sr-only">次の月</span>
        </button>
        <button type="button" class="sfk-filter-btn sfk-calendar-view-today" wire:click="showCurrentCalendarMonth">今日</button>
    </div>

    <table class="sfk-calendar-view-grid">
        <thead>
            <tr>
                @foreach (['日', '月', '火', '水', '木', '金', '土'] as $index => $weekday)
                    <th scope="col" @class(['is-sunday' => $index === 0, 'is-saturday' => $index === 6])>{{ $weekday }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($page->calendarWeeks() as $week)
                <tr>
                    @foreach ($week as $day)
                        <td @class(['is-outside' => ! $day['inMonth'], 'is-today' => $day['isToday']])>
                            <span class="sfk-calendar-view-day">{{ $day['date']->day }}</span>
                            <ul class="sfk-calendar-view-records">
                                @foreach ($day['records'] as $record)
                                    <li>
                                        @if ($record['url'] !== null)
                                            <a href="{{ $record['url'] }}" title="{{ $record['title'] }}">{{ $record['title'] }}</a>
                                        @else
                                            <span title="{{ $record['title'] }}">{{ $record['title'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
