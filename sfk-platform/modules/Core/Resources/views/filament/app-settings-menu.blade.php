{{--
    ⚙ of a record page (administrators): the shortcuts of アプリの設定 (この一覧の設定を開く, 一覧, 設定, アクセス権,
    その他の設定, 帳票出力) from HasAppChrome::appChrome(); a plain link when there is only the app's permissions.
--}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp

@if ($chrome['settings_menu'] !== [])
    <x-filament::dropdown placement="bottom-end" teleport>
        <x-slot name="trigger">
            <button type="button" class="sfk-app-tool" title="アプリの設定">
                <x-filament::icon :icon="Heroicon::OutlinedCog6Tooth" class="sfk-app-tool-icon" />
                <span class="fi-sr-only">アプリの設定</span>
            </button>
        </x-slot>
        <x-filament::dropdown.list>
            @foreach ($chrome['settings_menu'] as $item)
                <x-filament::dropdown.list.item tag="a" :href="$item['url']" icon="heroicon-o-adjustments-horizontal">{{ $item['label'] }}</x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
@elseif ($chrome['settings_url'])
    <a href="{{ $chrome['settings_url'] }}" class="sfk-app-tool" title="アプリの設定">
        <x-filament::icon :icon="Heroicon::OutlinedCog6Tooth" class="sfk-app-tool-icon" />
        <span class="fi-sr-only">アプリの設定</span>
    </a>
@endif
