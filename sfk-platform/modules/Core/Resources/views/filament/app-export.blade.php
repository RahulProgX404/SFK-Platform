{{--
    エクスポート of an app list or record (Modules\Core\Filament\Support\MenuButtons::export()): a menu of the 帳票出力 of the
    page; 出力設定 with the same ボタン名 open a sub-menu of their サブボタン名. An entry is a link (the record's file) or mounts
    its action — through the table when it works on the checked rows, so they are sent along.
    $export: the ActionGroup of the page's header actions.
--}}
@php
    use Filament\Actions\Action;
    use Filament\Actions\ActionGroup;
    use Illuminate\Support\Js;

    $click = function (Action $action): string {
        $name = Js::from($action->getName());

        return $action->hasCanAccessSelectedRecords()
            ? "(t => t ? Alpine.\$data(t).mountAction({$name}) : \$wire.mountAction({$name}))(\$wire.\$el.querySelector('[x-data^=filamentTable]'))"
            : "\$wire.mountAction({$name})";
    };
    $entries = array_values(array_filter($export->getActions(), fn ($entry): bool => $entry->isVisible()));
@endphp

<div class="sfk-export" x-data="{ open: false, sub: null }" x-on:keydown.escape.window="open = false; sub = null" x-on:click.outside="open = false; sub = null">
    <button type="button" class="sfk-export-btn" x-on:click="open = ! open; sub = null" x-bind:aria-expanded="open ? 'true' : 'false'" aria-haspopup="menu">
        <x-filament::icon icon="heroicon-o-arrow-up-on-square" class="sfk-export-icon" />
        <span>エクスポート</span>
        <x-filament::icon icon="heroicon-m-chevron-down" class="sfk-export-chevron" />
    </button>

    <div class="sfk-export-menu" role="menu" x-show="open" x-cloak x-transition.opacity>
        @foreach ($entries as $index => $entry)
            @if ($entry instanceof ActionGroup)
                <div class="sfk-export-group" x-on:mouseenter="sub = {{ $index }}" x-on:mouseleave="sub = null">
                    <button type="button" class="sfk-export-item" role="menuitem" aria-haspopup="menu"
                        x-on:click="sub = sub === {{ $index }} ? null : {{ $index }}" x-bind:aria-expanded="sub === {{ $index }} ? 'true' : 'false'">
                        <x-filament::icon icon="heroicon-o-printer" class="sfk-export-item-icon" />
                        <span>{{ $entry->getLabel() }}</span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="sfk-export-item-more" />
                    </button>
                    <div class="sfk-export-submenu" role="menu" x-show="sub === {{ $index }}" x-cloak>
                        @foreach (array_filter($entry->getActions(), fn ($action): bool => $action->isVisible()) as $action)
                            @include('core::filament.app-export-item', ['action' => $action, 'click' => $click])
                        @endforeach
                    </div>
                </div>
            @else
                @include('core::filament.app-export-item', ['action' => $entry, 'click' => $click])
            @endif
        @endforeach
    </div>
</div>
