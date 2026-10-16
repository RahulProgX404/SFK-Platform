{{-- Frame of a ダッシュボード設定 screen (Modules\Core\Filament\DashboardSettings\Concerns\HasSettingsChrome). --}}
@php
    use Filament\Support\Icons\Heroicon;

    $actions = $page->getCachedHeaderActions();
@endphp

<header class="sfk-app-header">
    <div class="sfk-app-titlebar">
        <span class="sfk-app-icon sfk-settings-icon" aria-hidden="true">
            <x-filament::icon :icon="Heroicon::OutlinedCog6Tooth" class="sfk-settings-icon-svg" />
        </span>
        <h1 class="sfk-app-title">{{ $title }}</h1>
    </div>

    <nav class="sfk-app-crumbs" aria-label="パンくずリスト">
        <a href="{{ $homeUrl }}" class="sfk-app-home" title="ポータル">
            <x-filament::icon :icon="Heroicon::Home" class="sfk-app-home-icon" />
        </a>
        <span class="sfk-app-crumb-sep" aria-hidden="true">›</span>
        <span>{{ $group }}</span>
        <span class="sfk-app-crumb-sep" aria-hidden="true">›</span>
        <a href="{{ $sectionUrl }}">{{ $sectionLabel }}</a>
        @if (filled($recordTitle))
            <span class="sfk-app-crumb-sep" aria-hidden="true">›</span>
            <span>{{ $recordTitle }}</span>
        @endif
    </nav>

    @if ($actions)
        <div class="sfk-app-toolbar">
            <div class="sfk-app-toolbar-start">
                <x-filament::actions :actions="$actions" class="sfk-app-actions" />
            </div>
        </div>
    @endif
</header>
