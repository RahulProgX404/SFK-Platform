{{--
    A tab of a record page added by another module (Modules\Core\Filament\Records\RecordTabs, design_ref/customer-*.png):
    its summary cards, a search box over its rows and the table — every column sorts on a click of its header. The
    rows link to their records. Nothing is listed while there are no rows (no message).
    $columns (key => label), $numeric (keys aligned right), $rows, $urls, $cards, $tones (key => [value => tone]).
--}}
@php
    $records = array_map(fn (array $row, int $index): array => ['cells' => $row, 'url' => $urls[$index] ?? null, 'text' => mb_strtolower(implode(' ', $row))], $rows, array_keys($rows));
@endphp

<div class="sfk-record-tab"
    wire:key="record-tab-{{ md5(json_encode(array_keys($columns))) }}"
    x-data="{
        query: '',
        sort: null,
        desc: false,
        records: @js($records),
        numeric: @js($numeric),
        toggle(key) { this.desc = this.sort === key ? ! this.desc : false; this.sort = key },
        value(record, key) {
            const text = record.cells[key] ?? ''
            if (! this.numeric.includes(key)) return text
            const number = Number(String(text).replace(/[^0-9.\-]/g, ''))
            return Number.isNaN(number) ? 0 : number
        },
        get shown() {
            const words = this.query.toLowerCase().split(/\s+/).filter(Boolean)
            let list = this.records.filter((record) => words.every((word) => record.text.includes(word)))
            if (this.sort !== null) {
                list = [...list].sort((a, b) => {
                    const x = this.value(a, this.sort), y = this.value(b, this.sort)
                    return (x > y ? 1 : x < y ? -1 : 0) * (this.desc ? -1 : 1)
                })
            }
            return list
        },
    }">
    @if ($cards !== [])
        <section class="sfk-record-tab-stats" aria-label="集計">
            @foreach ($cards as $card)
                <div>
                    <div class="sfk-record-tab-stat-label">{{ $card['label'] }}</div>
                    <div class="sfk-record-tab-stat-value">{{ $card['value'] }}</div>
                </div>
            @endforeach
        </section>
    @endif

    @if ($rows !== [])
        <div class="sfk-record-tab-bar">
            <label class="sfk-record-tab-search">
                <x-filament::icon icon="heroicon-m-magnifying-glass" class="sfk-record-tab-search-icon" />
                <span class="fi-sr-only">検索</span>
                <input type="search" x-model.debounce.200ms="query" placeholder="検索">
            </label>
            <span class="sfk-record-tab-count"><span x-text="shown.length">{{ count($rows) }}</span>件</span>
        </div>

        <div class="sfk-record-tab-table">
            <table>
                <thead>
                    <tr>
                        @foreach ($columns as $key => $label)
                            <th @class(['is-number' => in_array($key, $numeric, true)])>
                                <button type="button" x-on:click="toggle(@js($key))">
                                    {{ $label }}
                                    <x-filament::icon icon="heroicon-m-arrows-up-down" class="sfk-record-tab-sort" />
                                </button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(record, index) in shown" :key="index">
                        <tr x-bind:class="{ 'is-link': record.url }" x-on:click="if (record.url && ! $event.target.closest('a')) window.location.href = record.url">
                            @foreach ($columns as $key => $label)
                                <td @class(['is-number' => in_array($key, $numeric, true)])>
                                    @if (isset($tones[$key]))
                                        <span class="sfk-record-tab-badge" x-show="record.cells[@js($key)]"
                                            x-bind:class="'sfk-tone-' + (@js($tones[$key])[record.cells[@js($key)]] ?? 'slate')"
                                            x-text="record.cells[@js($key)]"></span>
                                    @elseif ($loop->first)
                                        <a x-bind:href="record.url" x-show="record.url" x-text="record.cells[@js($key)]"></a>
                                        <span x-show="! record.url" x-text="record.cells[@js($key)]"></span>
                                    @else
                                        <span x-text="record.cells[@js($key)]"></span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    @endif
</div>
