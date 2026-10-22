{{-- アプリの並び替え (ポータル ⚙, administrators): drag an app by its handle; the order applies when it is dropped. Each app
     shows its spaces of this dashboard ($panelId). --}}
<ul
    class="sfk-app-order"
    x-data
    x-sortable
    data-sortable-animation-duration="150"
    x-on:end.stop="$wire.saveAppOrder($event.target.sortable.toArray())"
>
    @foreach ($apps as $app)
        <li class="sfk-app-order-row" x-sortable-item="{{ $app->id }}" wire:key="app-order-{{ $app->id }}">
            <button type="button" class="sfk-app-order-handle" x-sortable-handle title="ドラッグして並び替え">
                <x-filament::icon icon="heroicon-m-bars-3" class="sfk-app-order-handle-icon" />
                <span class="fi-sr-only">ドラッグして並び替え</span>
            </button>
            <span class="sfk-app-icon" aria-hidden="true">{{ $app->iconText() }}</span>
            <span class="sfk-app-order-name">{{ $app->name }}</span>
            @php($spaceNames = $app->spaces->where('panel_id', $panelId)->pluck('name'))
            @if ($spaceNames->isNotEmpty())
                <span class="sfk-row-muted">{{ $spaceNames->implode('、') }}</span>
            @endif
        </li>
    @endforeach
</ul>
