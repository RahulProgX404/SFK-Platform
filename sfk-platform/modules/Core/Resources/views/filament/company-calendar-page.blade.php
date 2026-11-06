{{-- 会社カレンダー on the dashboards (Modules\Core\Filament\Calendar\CompanyCalendarPage): read-only. --}}
<x-filament-panels::page>
    <div style="padding: var(--sfk-space-5) var(--sfk-gutter) var(--sfk-space-8)">
        @include('core::filament.company-calendar')
    </div>
</x-filament-panels::page>
