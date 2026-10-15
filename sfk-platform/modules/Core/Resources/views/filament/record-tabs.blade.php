{{-- Tab bar above a record (Modules\Core\Filament\Records\RecordLayout::tabbed()); `tab` lives in the parent x-data. --}}
<div class="kt-record-tabs" role="tablist">
    @foreach ($tabs as $key => $label)
        <button
            type="button"
            role="tab"
            class="kt-record-tab"
            x-on:click="tab = '{{ $key }}'"
            x-bind:class="{ 'kt-active': tab === '{{ $key }}' }"
            x-bind:aria-selected="tab === '{{ $key }}'"
        >{{ $label }}</button>
    @endforeach
</div>
