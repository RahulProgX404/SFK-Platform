{{-- フォームに反映する内容を確認 (Modules\Orders\Filament\Resources\Orders\Pages\CreateOrder::faxPreviewAction()). --}}
@php
    $fields = (array) ($import['fields'] ?? []);
    $unread = (array) ($import['errors'] ?? []);
    $uncertain = (array) ($import['uncertain'] ?? []);
    $warnings = array_values(array_filter($fields, fn (array $field): bool => filled($field['warning'] ?? null)));
    $fromInspection = str_starts_with((string) ($import['stampSource'] ?? ''), '検印欄');
@endphp

<div class="sfk-fax-preview">
    <p class="sfk-fax-source">読み取り元: {{ $import['fileName'] ?? '' }} ／ 得意先: {{ $import['stamp'] ?? '' }}（判定元: {{ $import['stampSource'] ?? '' }}）</p>

    @if ($unread !== [])
        <div class="sfk-fax-note is-error" role="alert">
            <strong>次の項目が読み取れませんでした。原本を確認して画面上で手入力してください。</strong>
            <ul>@foreach ($unread as $field)<li>{{ $field }}</li>@endforeach</ul>
        </div>
    @endif

    @if (! $fromInspection)
        <div class="sfk-fax-note is-warning">
            <strong>得意先を検印欄から判定できませんでした。別の欄から推定しています — 原本と照合してください。</strong>
            <ul>
                <li>「{{ $import['stampSource'] ?? '' }}」に得意先名が1件だけ見つかったため「{{ $import['stamp'] ?? '' }}」として扱いました。</li>
                <li>得意先は品名と製袋予定規格にも影響します。誤っている場合はキャンセルしてください。</li>
            </ul>
        </div>
    @endif

    @if ($unread === [] && $warnings !== [])
        <div class="sfk-fax-note is-warning">
            <strong>次の項目は空、または確認が必要です。反映後に画面上で修正してください。</strong>
            <ul>@foreach ($warnings as $field)<li>{{ $field['label'] }}: {{ $field['warning'] }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($uncertain !== [])
        <div class="sfk-fax-note is-info">
            <strong>AIが判読に自信を持てなかった箇所:</strong>
            <ul>@foreach ($uncertain as $place)<li>{{ $place }}</li>@endforeach</ul>
        </div>
    @endif

    <table class="sfk-fax-table">
        <thead>
            <tr><th>項目</th><th>フォームに入る値</th></tr>
        </thead>
        <tbody>
            @foreach ($fields as $field)
                <tr @class(['is-warning' => filled($field['warning'] ?? null)])>
                    <td class="sfk-fax-label">{{ $field['label'] }}</td>
                    <td>
                        <span class="sfk-fax-value">{{ filled($field['value']) ? $field['value'] : '（空）' }}</span>
                        <span class="sfk-fax-from">抽出元: {{ $field['source'] }}</span>
                        @if (filled($field['warning'] ?? null))
                            <span class="sfk-fax-check">要確認: {{ $field['warning'] }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <details class="sfk-fax-raw">
        <summary>AIが抽出した全項目（JSON）を表示</summary>
        <pre>{{ json_encode($import['reading'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </details>
</div>
