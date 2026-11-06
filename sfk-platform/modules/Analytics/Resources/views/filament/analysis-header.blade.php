{{--
    Header of the 分析 pages (Modules\Analytics\Filament\Pages\Concerns\HasAnalysisChrome), in the look of the app screens
    (core::filament.app-header): the breadcrumb home › 分析 › page, then the 分析 pages the user may open as tabs.
    $title; $tabs: list<{label, url, active}>; $homeUrl
--}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp

<header class="sfk-app-header sfk-app-header-list">
    <div class="sfk-app-head">
        <div class="sfk-app-head-title">
            <a href="{{ $homeUrl }}" class="sfk-app-home" title="ホーム">
                <x-filament::icon :icon="Heroicon::OutlinedHome" class="sfk-app-home-icon" />
                <span class="fi-sr-only">ホーム</span>
            </a>
            <x-filament::icon :icon="Heroicon::ChevronRight" class="sfk-app-crumb-sep" />
            <span class="sfk-app-subtitle">分析</span>
            <x-filament::icon :icon="Heroicon::ChevronRight" class="sfk-app-crumb-sep" />
            <h1 class="sfk-app-title">{{ $title }}</h1>
        </div>
    </div>

    @if (count($tabs) > 1)
        <nav class="sfk-app-tabs" aria-label="分析">
            @foreach ($tabs as $tab)
                <a href="{{ $tab['url'] }}" @class(['sfk-app-tab', 'is-active' => $tab['active']]) @if ($tab['active']) aria-current="page" @endif>{{ $tab['label'] }}</a>
            @endforeach
        </nav>
    @endif
</header>
