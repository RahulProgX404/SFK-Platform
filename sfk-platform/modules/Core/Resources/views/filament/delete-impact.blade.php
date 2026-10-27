{{--
    Description of a delete confirmation (Modules\Core\Filament\Support\DeleteImpact): what is deleted with the records,
    then — as a warning — what deleting does to the records of other apps (RecordDeletion::impact()).
    $description: text or null; $impact: list of [app, text, blocks].
--}}
@if (filled($description))
    <span class="sfk-delete-text">{{ $description }}</span>
@endif
@if ($impact !== [])
    <span class="sfk-delete-impact" role="alert">
        <span class="sfk-delete-impact-title">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" />
            次のアプリのレコードにも影響します
        </span>
        @foreach ($impact as $row)
            <span @class(['sfk-delete-impact-row', 'is-blocking' => $row['blocks']])>
                <span class="sfk-delete-impact-app">{{ $row['app'] }}</span>
                <span>{{ $row['text'] }}</span>
            </span>
        @endforeach
    </span>
@endif
