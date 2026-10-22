{{--
    条件 and ソート of a filter (shared by the 絞り込む dialog, core::filament.app-filter, and the 一覧 screen,
    core::filament.app-list-views): 条件 rows of field / comparison / value with (+) below and すべてクリア, the
    すべての条件を満たす / いずれかの条件を満たす choice once there are two rows, and ソート rows (up to 5). Choice and user
    values come from a list box ("--" = blank), dates from the calendar (今日 / 選択を解除). State and methods: the
    sfkFilterEditor part of the Alpine components (core::filament.app-filter-script).
    $conditionsLabel: 条件 (dialog) or 絞り込み (一覧 screen); $idPrefix: unique id prefix.
--}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp

<section class="sfk-filter-section" aria-labelledby="{{ $idPrefix }}-conditions">
    <h3 id="{{ $idPrefix }}-conditions" class="sfk-filter-label">{{ $conditionsLabel }}</h3>

    <div class="sfk-filter-match" x-show="conditions.length > 1" role="radiogroup" aria-label="条件の組み合わせ">
        <label><input type="radio" class="sfk-ui-radio" value="and" x-model="match"> すべての条件を満たす</label>
        <label><input type="radio" class="sfk-ui-radio" value="or" x-model="match"> いずれかの条件を満たす</label>
    </div>

    <div class="sfk-filter-box">
        <template x-for="(row, index) in conditions" :key="row.key">
            <div class="sfk-filter-row">
                <span class="sfk-filter-select">
                    <select class="sfk-filter-field" x-model="row.field" x-on:change="changeField(row)" aria-label="フィールド">
                        <option value="">すべてのレコード</option>
                        <template x-for="f in config.fields" :key="f.name">
                            <option :value="f.name" x-text="f.label" :selected="row.field === f.name"></option>
                        </template>
                    </select>
                </span>

                <template x-if="field(row)">
                    <span class="sfk-filter-select sfk-filter-select-op">
                        <select x-model="row.operator" aria-label="条件">
                            <template x-for="op in field(row).operators" :key="op.value">
                                <option :value="op.value" x-text="op.label" :selected="row.operator === op.value"></option>
                            </template>
                        </select>
                    </span>
                </template>

                {{-- 次のキーワードを含む / ＝ … : one keyword or number --}}
                <template x-if="valueKind(row) === 'text'">
                    <input type="text" class="sfk-filter-input" x-model="row.values[0]" maxlength="100" aria-label="値"
                           :inputmode="field(row).type === 'number' ? 'decimal' : 'text'">
                </template>

                {{-- 次のいずれかと等しい: one input per value, (+) adds one --}}
                <template x-if="valueKind(row) === 'multi'">
                    <span class="sfk-filter-values">
                        <template x-for="(value, valueIndex) in row.values" :key="valueIndex">
                            <span class="sfk-filter-value">
                                <input type="text" class="sfk-filter-input" x-model="row.values[valueIndex]" maxlength="100" aria-label="値">
                                <button type="button" class="sfk-filter-round" x-show="row.values.length > 1" x-on:click="row.values.splice(valueIndex, 1)" title="値を削除する">
                                    <x-filament::icon :icon="Heroicon::Minus" class="sfk-filter-round-icon" />
                                    <span class="fi-sr-only">値を削除する</span>
                                </button>
                            </span>
                        </template>
                        <button type="button" class="sfk-filter-round" x-on:click="row.values.push('')" title="値を追加する">
                            <x-filament::icon :icon="Heroicon::Plus" class="sfk-filter-round-icon" />
                            <span class="fi-sr-only">値を追加する</span>
                        </button>
                    </span>
                </template>

                {{-- 次のいずれかを含む / 次のいずれも含まない: list box --}}
                <template x-if="valueKind(row) === 'list'">
                    <select multiple size="6" class="sfk-filter-listbox" aria-label="値"
                            x-on:change="row.values = Array.from($el.selectedOptions).map((option) => option.value)">
                        <template x-for="option in field(row).options" :key="option.value">
                            <option :value="option.value" x-text="option.label" :selected="row.values.includes(option.value)"></option>
                        </template>
                    </select>
                </template>

                {{-- dates: 日付を指定 / 日時を指定 with the calendar, or 今日, 今月 … with すべて / a day --}}
                <template x-if="valueKind(row) === 'date'">
                    <span class="sfk-filter-date-ctn">
                        <span class="sfk-filter-select sfk-filter-select-op">
                            <select x-model="row.mode" x-on:change="changeMode(row)" aria-label="日付の指定方法">
                                <template x-for="mode in field(row).modes" :key="mode.value">
                                    <option :value="mode.value" x-text="mode.label" :selected="row.mode === mode.value"></option>
                                </template>
                            </select>
                        </span>

                        <template x-if="row.mode === 'specified'">
                            <span class="sfk-filter-date" x-on:click.outside="picker.key === row.key && (picker.key = null)">
                                <input type="text" class="sfk-filter-input sfk-filter-input-date" x-model="row.values[0]"
                                       placeholder="YYYY-MM-DD" maxlength="10" autocomplete="off" aria-label="日付"
                                       x-on:focus="openPicker(row)" x-on:click="openPicker(row)">
                                <template x-if="field(row).type === 'datetime'">
                                    <input type="time" class="sfk-filter-input sfk-filter-input-time" x-model="row.time" aria-label="時刻">
                                </template>

                                <div class="sfk-calendar" x-show="picker.key === row.key" x-cloak role="dialog" aria-label="カレンダー"
                                     x-init="$watch('picker.key', (open) => open === row.key && $nextTick(() => placeCalendar($el)))">
                                    <div class="sfk-calendar-head">
                                        <button type="button" class="sfk-calendar-nav" x-on:click="shiftMonth(-1)" title="前の月">
                                            <x-filament::icon :icon="Heroicon::ChevronLeft" class="sfk-calendar-nav-icon" />
                                            <span class="fi-sr-only">前の月</span>
                                        </button>
                                        <span class="sfk-calendar-selects">
                                            <select x-model.number="picker.year" aria-label="年">
                                                <template x-for="year in pickerYears()" :key="year">
                                                    <option :value="year" x-text="year + '年'" :selected="picker.year === year"></option>
                                                </template>
                                            </select>
                                            <select x-model.number="picker.month" aria-label="月">
                                                <template x-for="month in 12" :key="month">
                                                    <option :value="month" x-text="month + '月'" :selected="picker.month === month"></option>
                                                </template>
                                            </select>
                                        </span>
                                        <button type="button" class="sfk-calendar-nav" x-on:click="shiftMonth(1)" title="次の月">
                                            <x-filament::icon :icon="Heroicon::ChevronRight" class="sfk-calendar-nav-icon" />
                                            <span class="fi-sr-only">次の月</span>
                                        </button>
                                    </div>
                                    <table class="sfk-calendar-grid">
                                        <thead>
                                            <tr>
                                                <template x-for="(weekday, weekdayIndex) in ['日', '月', '火', '水', '木', '金', '土']" :key="weekday">
                                                    <th scope="col" :class="{ 'is-weekend': weekdayIndex === 0 || weekdayIndex === 6 }" x-text="weekday"></th>
                                                </template>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(week, weekIndex) in calendarWeeks()" :key="weekIndex">
                                                <tr>
                                                    <template x-for="day in week" :key="day.iso">
                                                        <td>
                                                            <button type="button" class="sfk-calendar-day" x-on:click="pickDate(row, day.iso)" x-text="day.day"
                                                                    :class="{ 'is-outside': ! day.inMonth, 'is-today': day.iso === todayIso(), 'is-selected': day.iso === row.values[0] }"
                                                                    :aria-label="day.iso"></button>
                                                        </td>
                                                    </template>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                    <div class="sfk-calendar-foot">
                                        <button type="button" class="sfk-filter-link" x-on:click="pickDate(row, todayIso())">今日</button>
                                        <button type="button" class="sfk-filter-link" x-on:click="pickDate(row, '')">選択を解除</button>
                                    </div>
                                </div>
                            </span>
                        </template>

                        <template x-if="subOptions(row).length > 0">
                            <span class="sfk-filter-select sfk-filter-select-op">
                                <select x-model="row.sub" aria-label="日付の範囲">
                                    <template x-for="sub in subOptions(row)" :key="sub.value">
                                        <option :value="sub.value" x-text="sub.label" :selected="row.sub === sub.value"></option>
                                    </template>
                                </select>
                            </span>
                        </template>
                    </span>
                </template>

                <button type="button" class="sfk-filter-round sfk-filter-row-remove" x-show="conditions.length > 1" x-on:click="removeCondition(index)" title="条件を削除する">
                    <x-filament::icon :icon="Heroicon::Minus" class="sfk-filter-round-icon" />
                    <span class="fi-sr-only">条件を削除する</span>
                </button>
            </div>
        </template>
    </div>

    <div class="sfk-filter-after">
        <button type="button" class="sfk-filter-round" x-on:click="addCondition()" title="条件を追加する">
            <x-filament::icon :icon="Heroicon::Plus" class="sfk-filter-round-icon" />
            <span class="fi-sr-only">条件を追加する</span>
        </button>
    </div>
    <button type="button" class="sfk-filter-link sfk-filter-clear" x-show="hasConditions()" x-on:click="clearAll()">すべてクリア</button>
</section>

<section class="sfk-filter-section" aria-labelledby="{{ $idPrefix }}-sorts" x-show="config.sortable.length > 0">
    <h3 id="{{ $idPrefix }}-sorts" class="sfk-filter-label">ソート</h3>

    <div class="sfk-filter-box">
        <template x-for="(sort, index) in sorts" :key="sort.key">
            <div class="sfk-filter-row">
                <span class="sfk-filter-select">
                    <select class="sfk-filter-field" x-model="sort.field" aria-label="ソートするフィールド">
                        <template x-for="f in config.sortable" :key="f.name">
                            <option :value="f.name" x-text="f.label" :selected="sort.field === f.name"></option>
                        </template>
                    </select>
                </span>
                <span class="sfk-filter-select sfk-filter-select-op">
                    <select x-model="sort.direction" aria-label="並び順">
                        <option value="asc" :selected="sort.direction === 'asc'">昇順</option>
                        <option value="desc" :selected="sort.direction === 'desc'">降順</option>
                    </select>
                </span>
                <button type="button" class="sfk-filter-round sfk-filter-row-remove" x-show="sorts.length > 1" x-on:click="sorts.splice(index, 1)" title="ソートを削除する">
                    <x-filament::icon :icon="Heroicon::Minus" class="sfk-filter-round-icon" />
                    <span class="fi-sr-only">ソートを削除する</span>
                </button>
            </div>
        </template>
    </div>

    <div class="sfk-filter-after">
        <button type="button" class="sfk-filter-round" x-show="sorts.length < 5" x-on:click="addSort()" title="ソートを追加する">
            <x-filament::icon :icon="Heroicon::Plus" class="sfk-filter-round-icon" />
            <span class="fi-sr-only">ソートを追加する</span>
        </button>
    </div>
</section>
