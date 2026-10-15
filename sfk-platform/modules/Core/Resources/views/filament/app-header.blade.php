{{-- Frame of an app screen (Modules\Core\Filament\AppPages\Concerns\HasAppChrome). --}}
@php
    use Filament\Support\Icons\Heroicon;

    $actions = $page->getCachedHeaderActions();
@endphp

<header class="sfk-app-header">
    <div class="sfk-app-titlebar">
        <span class="sfk-app-icon" aria-hidden="true">{{ $chrome['icon'] }}</span>
        <h1 class="sfk-app-title">
            <a href="{{ $chrome['index_url'] }}">{{ $chrome['name'] }}</a>
        </h1>
    </div>

    <nav class="sfk-app-crumbs" aria-label="パンくずリスト">
        <a href="{{ $chrome['home_url'] }}" class="sfk-app-home" title="ポータル">
            <x-filament::icon :icon="Heroicon::Home" class="sfk-app-home-icon" />
        </a>
        @if (filled($chrome['space']))
            <span class="sfk-app-crumb-sep" aria-hidden="true">›</span>
            <span>スペース: {{ $chrome['space'] }}</span>
        @endif
        <span class="sfk-app-crumb-sep" aria-hidden="true">›</span>
        <a href="{{ $chrome['index_url'] }}">アプリ: {{ $chrome['name'] }}</a>
    </nav>

    @if (filled($chrome['description']))
        <div class="sfk-app-description">{{ $chrome['description'] }}</div>
    @endif

    <div class="sfk-app-toolbar">
        @if ($kind === 'list')
            <div class="sfk-app-toolbar-start">
                @if (count($page->getListViews()) > 0)
                    <label class="sfk-app-view">
                        <span class="sr-only">一覧</span>
                        <select wire:model.live="listView" class="sfk-app-view-select">
                            @foreach ($page->getListViews() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-filament::icon :icon="Heroicon::ChevronDown" class="sfk-app-view-chevron" />
                    </label>
                @endif
            </div>

            <div class="sfk-app-toolbar-end">
                @if ($chrome['create_url'])
                    <a href="{{ $chrome['create_url'] }}" class="sfk-app-add" title="レコードを追加する">
                        <x-filament::icon :icon="Heroicon::Plus" class="sfk-app-add-icon" />
                        <span class="sr-only">レコードを追加する</span>
                    </a>
                @endif

                @if ($chrome['settings_url'])
                    <a href="{{ $chrome['settings_url'] }}" class="sfk-app-tool" title="アプリの設定">
                        <x-filament::icon :icon="Heroicon::OutlinedCog6Tooth" class="sfk-app-tool-icon" />
                        <span class="sr-only">アプリの設定</span>
                    </a>
                @endif

                @if ($actions)
                    <x-filament::actions :actions="$actions" class="sfk-app-actions" />
                @endif

                @include('core::filament.app-pager', ['page' => $page, 'position' => 'top'])
            </div>
        @elseif ($kind === 'create' || $kind === 'edit')
            <div class="sfk-app-toolbar-start">
                <x-filament::actions :actions="$actions" class="sfk-app-actions" />
            </div>
        @else
            <div class="sfk-app-toolbar-start">
                @if ($actions)
                    <x-filament::actions :actions="$actions" class="sfk-app-actions" />
                @endif
            </div>

            <div class="sfk-app-toolbar-end">
                @if ($chrome['settings_url'])
                    <a href="{{ $chrome['settings_url'] }}" class="sfk-app-tool" title="アプリの設定">
                        <x-filament::icon :icon="Heroicon::OutlinedCog6Tooth" class="sfk-app-tool-icon" />
                        <span class="sr-only">アプリの設定</span>
                    </a>
                @endif
            </div>
        @endif
    </div>
</header>
