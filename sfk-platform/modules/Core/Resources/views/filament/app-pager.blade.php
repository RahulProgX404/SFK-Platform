{{-- 「1 - 100（N件中）」 paging of an app list (Modules\Core\Filament\AppPages\AppListRecords). --}}
@php
    use Filament\Support\Icons\Heroicon;

    $pager = $page->appPager();
@endphp

@if ($pager)
    <div @class(['sfk-app-pager', 'sfk-app-pager-bottom' => $position === 'bottom'])>
        <button type="button" class="sfk-app-pager-btn" wire:click="previousAppPage" @disabled(! $pager['previous']) title="前へ">
            <x-filament::icon :icon="Heroicon::ChevronLeft" class="sfk-app-pager-icon" />
            <span class="sr-only">前へ</span>
        </button>
        <span class="sfk-app-pager-text">{{ $pager['from'] }} - {{ $pager['to'] }}（{{ number_format($pager['total']) }}件中）</span>
        <button type="button" class="sfk-app-pager-btn" wire:click="nextAppPage" @disabled(! $pager['next']) title="次へ">
            <x-filament::icon :icon="Heroicon::ChevronRight" class="sfk-app-pager-icon" />
            <span class="sr-only">次へ</span>
        </button>
    </div>
@endif
