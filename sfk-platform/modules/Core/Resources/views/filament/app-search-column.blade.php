{{--
    The icon right of the search box of an app list (Modules\Core\Filament\AppPages\AppListRecords::searchColumnOptions()):
    a list of the columns of the current 一覧 to search one of them, or 標準の検索項目 (the app's searchable columns).
    Blue while a column is chosen.
--}}
@php
    use Illuminate\Support\Arr;
    use Illuminate\Support\Js;

    $options = $page->searchColumnOptions();
    $active = $page->activeSearchColumn();
    $hasDefault = $page->defaultSearchColumns() !== [];
@endphp

@if ($options !== [])
    {{-- teleport: drawn fixed, so the table's scroll box does not cut the list off --}}
    <x-filament::dropdown placement="bottom-start" width="xs" max-height="24rem" :teleport="true" class="sfk-search-column">
        <x-slot name="trigger">
            <button type="button" @class(['sfk-search-column-btn', 'is-active' => $active !== null])
                title="検索する項目{{ $active !== null ? '：'.$options[$active] : '' }}" aria-label="検索する項目">
                <x-filament::icon icon="heroicon-m-adjustments-horizontal" />
            </button>
        </x-slot>

        <x-filament::dropdown.list>
            @if ($hasDefault)
                <x-filament::dropdown.list.item tag="button" wire:click="setSearchColumn(null)"
                    :icon="$active === null ? 'heroicon-m-check' : null"
                    :class="Arr::toCssClasses(['sfk-search-column-item', 'is-selected' => $active === null])">
                    標準の検索項目
                </x-filament::dropdown.list.item>
            @endif
            @foreach ($options as $name => $label)
                <x-filament::dropdown.list.item tag="button" wire:click="setSearchColumn({{ Js::from($name) }})"
                    :icon="$active === $name ? 'heroicon-m-check' : null"
                    :class="Arr::toCssClasses(['sfk-search-column-item', 'is-selected' => $active === $name])">
                    {{ $label }}
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
@endif
