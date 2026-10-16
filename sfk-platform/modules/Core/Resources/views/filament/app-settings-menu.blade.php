{{--
    Gear of an app screen (administrators): アプリの設定, with ▾ for the legacy shortcuts (この一覧の設定を開く, 一覧, 設定,
    アクセス権, その他の設定) from HasAppChrome::appChrome().
--}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp

@if ($chrome['settings_url'])
    <span class="sfk-app-settings">
        <a href="{{ $chrome['settings_url'] }}" class="sfk-app-tool" title="アプリの設定">
            <x-filament::icon :icon="Heroicon::OutlinedCog6Tooth" class="sfk-app-tool-icon" />
            <span class="fi-sr-only">アプリの設定</span>
        </a>
        @if ($chrome['settings_menu'] !== [])
            <x-filament::dropdown placement="bottom-end" teleport>
                <x-slot name="trigger">
                    <button type="button" class="sfk-app-tool sfk-app-settings-more" title="アプリの設定メニュー">
                        <x-filament::icon :icon="Heroicon::ChevronDown" class="sfk-app-settings-more-icon" />
                        <span class="fi-sr-only">アプリの設定メニュー</span>
                    </button>
                </x-slot>
                <x-filament::dropdown.list>
                    @foreach ($chrome['settings_menu'] as $item)
                        <x-filament::dropdown.list.item tag="a" :href="$item['url']">{{ $item['label'] }}</x-filament::dropdown.list.item>
                    @endforeach
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        @endif
    </span>
@endif
