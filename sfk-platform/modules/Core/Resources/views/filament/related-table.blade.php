{{--
    A related-record list on a record page (e.g. 入出庫履歴, 仕入履歴), used as a schema View with the view data
    title (optional, shown like a field label), columns (key: label), numeric (keys aligned right), rows (key: value) and urls (record link per row, or null).
    Nothing is shown while there are no rows (no message).
--}}
@once
    <style>
        .sfk-related-table { overflow-x: auto; }
        .sfk-related-table table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
        .sfk-related-table th { padding: 8px 12px; background: var(--kt-blue-soft); color: var(--kt-label); text-align: start; white-space: nowrap; }
        .sfk-related-table td { padding: 6px 12px; border-top: 1px solid var(--kt-border); color: var(--kt-text); }
        .sfk-related-table .is-number { text-align: end; font-variant-numeric: tabular-nums; }
        .sfk-related-table a { color: var(--kt-blue); }
        .sfk-related-table .sfk-related-icon { width: 1rem; height: 1rem; }
        .sfk-related-title { margin: 0 0 6px; color: var(--kt-label); font-size: .8125rem; font-weight: 700; }
    </style>
@endonce

@if (filled($title ?? null))
    <p class="sfk-related-title">{{ $title }}</p>
@endif

@if ($rows !== [])
    <div class="sfk-related-table">
        <table>
            <thead>
                <tr>
                    @if (array_filter($urls ?? []) !== [])
                        <th><span class="fi-sr-only">レコード</span></th>
                    @endif
                    @foreach ($columns as $key => $label)
                        <th @class(['is-number' => in_array($key, $numeric ?? [], true)])>{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $index => $row)
                    <tr>
                        @if (array_filter($urls ?? []) !== [])
                            <td>
                                @if (($urls[$index] ?? null) !== null)
                                    <a href="{{ $urls[$index] }}" title="レコードを開く">
                                        <x-filament::icon icon="heroicon-o-document-text" class="sfk-related-icon" />
                                        <span class="fi-sr-only">レコードを開く</span>
                                    </a>
                                @endif
                            </td>
                        @endif
                        @foreach ($columns as $key => $label)
                            <td @class(['is-number' => in_array($key, $numeric ?? [], true)])>{{ $row[$key] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
