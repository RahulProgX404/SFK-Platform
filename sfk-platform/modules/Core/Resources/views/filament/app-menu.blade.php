{{--
    The app's legacy buttons (MenuButtons, menuActions() of the page): a segmented bar of icon + label buttons. On a list,
    the buttons on the checked rows form their own group; one marked MenuButtons::withCheckedCount() carries a live
    「N件」 badge of the checked rows read from the table.
    $actions: the visible menu actions.
--}}
@php
    use Modules\Core\Filament\Support\MenuButtons;

    $onRows = array_values(array_filter($actions, fn($action): bool => MenuButtons::isOnCheckedRows($action)));
    $general = array_values(array_filter($actions, fn($action): bool => !MenuButtons::isOnCheckedRows($action)));
@endphp

<div class="sfk-app-menu" role="toolbar" aria-label="アプリのボタン">
    @if ($general)
        <div class="sfk-app-menu-group">
            @foreach ($general as $action)
                {{ $action }}
            @endforeach
        </div>
    @endif

    @if ($onRows)
        {{-- 「N件」: the rows checked in the table ($store.sfkCheckedRows, core::filament.app-filter-script) --}}
        <div class="sfk-app-menu-group sfk-app-menu-rows" x-data x-bind:class="{ 'has-checked': $store.sfkCheckedRows > 0 }">
            @foreach ($onRows as $action)
                @if (MenuButtons::showsCheckedCount($action))
                    <span class="sfk-menu-btn-counted">
                        {{ $action }}
                        <span class="sfk-menu-badge" aria-live="polite"><span x-text="$store.sfkCheckedRows">0</span>件</span>
                    </span>
                @else
                    {{ $action }}
                @endif
            @endforeach
        </div>
    @endif
</div>
