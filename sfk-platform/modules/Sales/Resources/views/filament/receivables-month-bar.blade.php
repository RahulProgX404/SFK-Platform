{{--
    基準月 of 23. 売掛管理 / 23-2. 売掛管理(締日別) (Modules\Sales\Filament\Resources\Receivables\Pages\ListReceivableMonths):
    the 一覧 with the month it shows (「先月売掛一覧（2026年10月）」), then ‹ 基準月 › and 当月 (back to the current month).
--}}
@php
    use Filament\Support\Icons\Heroicon;

    $page = $getLivewire();
    $base = $page->baseMonth();
@endphp

<style>
    .sfk-receivables-month-label {
        margin-left: var(--sfk-space-4);
        color: var(--kt-label);
        font-size: var(--sfk-fs-label);
    }

    .sfk-receivables-month-input {
        height: var(--sfk-control);
        padding: 0 var(--sfk-space-2);
        border: 1px solid var(--sfk-line);
        border-radius: var(--sfk-radius);
        background: var(--sfk-surface);
        color: var(--sfk-text);
        font: inherit;
        font-variant-numeric: tabular-nums;
    }
</style>

<div class="sfk-calendar-view-head sfk-receivables-month">
    <h2 class="sfk-calendar-view-month" title="{{ $page->basisRule() }}">{{ $page->listHeading() }}</h2>
    <label class="sfk-receivables-month-label" for="sfk-receivables-base">基準月</label>
    <button type="button" class="sfk-app-pager-btn" wire:click="shiftBaseMonth(-1)" title="前の月">
        <x-filament::icon :icon="Heroicon::ChevronLeft" class="sfk-app-pager-icon" />
        <span class="fi-sr-only">前の月</span>
    </button>
    <input
        id="sfk-receivables-base"
        type="month"
        class="sfk-receivables-month-input"
        value="{{ $base->key() }}"
        wire:change="setBaseMonth($event.target.value)"
    />
    <button type="button" class="sfk-app-pager-btn" wire:click="shiftBaseMonth(1)" title="次の月">
        <x-filament::icon :icon="Heroicon::ChevronRight" class="sfk-app-pager-icon" />
        <span class="fi-sr-only">次の月</span>
    </button>
    @unless ($page->isCurrentBaseMonth())
        <button type="button" class="sfk-filter-btn sfk-calendar-view-today" wire:click="resetBaseMonth">当月</button>
    @endunless
</div>
