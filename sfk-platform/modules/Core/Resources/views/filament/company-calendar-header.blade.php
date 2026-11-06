{{--
    Header of 会社カレンダー on the dashboards (Modules\Core\Filament\Calendar\CompanyCalendarPage), in the look of the app
    screens (core::filament.app-header): the breadcrumb home › 会社カレンダー. $title, $homeUrl
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
            <h1 class="sfk-app-title">{{ $title }}</h1>
        </div>
    </div>
</header>
