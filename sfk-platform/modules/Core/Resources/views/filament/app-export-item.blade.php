{{-- An entry of エクスポート (core::filament.app-export): a link to the file, or the action mounted. --}}
@if (filled($url = $action->getUrl()))
    <a href="{{ $url }}" class="sfk-export-item" role="menuitem" @if ($action->shouldOpenUrlInNewTab()) target="_blank" rel="noopener" @endif x-on:click="open = false">
        <x-filament::icon icon="heroicon-o-document-arrow-down" class="sfk-export-item-icon" />
        <span>{{ $action->getLabel() }}</span>
    </a>
@else
    <button type="button" class="sfk-export-item" role="menuitem" x-on:click="open = false; {{ $click($action) }}">
        <x-filament::icon icon="heroicon-o-document-arrow-down" class="sfk-export-item-icon" />
        <span>{{ $action->getLabel() }}</span>
    </button>
@endif
