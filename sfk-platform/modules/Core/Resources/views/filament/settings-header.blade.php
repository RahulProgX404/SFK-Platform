{{--
    Header of a ダッシュボード設定 screen (Modules\Core\Filament\DashboardSettings\Concerns\HasSettingsChrome), in the
    look of the app screens: home › group › screen › record, the screen's name, its actions on the right.
--}}
@php
    use Filament\Support\Icons\Heroicon;

    $actions = $page->getCachedHeaderActions();
@endphp

<header class="sfk-app-header sfk-app-header-settings">
    <div class="sfk-app-head">
        <div class="sfk-app-head-title">
            <a href="{{ $homeUrl }}" class="sfk-app-home" title="ホーム">
                <x-filament::icon :icon="Heroicon::OutlinedHome" class="sfk-app-home-icon" />
                <span class="fi-sr-only">ホーム</span>
            </a>
            <x-filament::icon :icon="Heroicon::ChevronRight" class="sfk-app-crumb-sep" />
            <span class="sfk-app-subtitle">{{ $group }}</span>
            <x-filament::icon :icon="Heroicon::ChevronRight" class="sfk-app-crumb-sep" />
            <h1 class="sfk-app-title"><a href="{{ $sectionUrl }}">{{ filled($recordTitle) ? $sectionLabel : $title }}</a></h1>
            @if (filled($recordTitle))
                <x-filament::icon :icon="Heroicon::ChevronRight" class="sfk-app-crumb-sep" />
                <span class="sfk-app-subtitle">{{ $recordTitle }}</span>
            @endif
        </div>

        @if ($actions)
            <div class="sfk-app-head-actions">
                <x-filament::actions :actions="$actions" class="sfk-app-actions" />
            </div>
        @endif
    </div>
</header>
