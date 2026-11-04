{{--
    Under an app list (Modules\Core\Filament\AppPages\AppListRecords, design_ref/app-index.png): 表示件数 (each user's
    choice, every app) on the left; the count and the page numbers on the right.
--}}
@php
    use Filament\Support\Icons\Heroicon;
    use Modules\Core\Filament\AppPages\AppListRecords;

    $pager = $page->appPager();
@endphp

@if ($pager)
    <div class="sfk-list-footer">
        <label class="sfk-list-size">
            <span>表示件数</span>
            <select class="sfk-list-size-select" x-on:change="$wire.setPageSize(Number($event.target.value))">
                @foreach (AppListRecords::PAGE_SIZES as $size)
                    <option value="{{ $size }}" @selected($size === $page->pageSize())>{{ $size }}</option>
                @endforeach
            </select>
            <span>件</span>
        </label>

        <div class="sfk-list-pages">
            <span class="sfk-list-count">{{ number_format($pager['from']) }} - {{ number_format($pager['to']) }}（{{ number_format($pager['total']) }}件中）</span>

            <nav class="sfk-pages" aria-label="ページ">
                <button type="button" class="sfk-page-btn" wire:click="previousAppPage" @disabled(! $pager['previous']) title="前へ">
                    <x-filament::icon :icon="Heroicon::ArrowLeft" class="sfk-page-icon" />
                    <span class="fi-sr-only">前へ</span>
                </button>
                @foreach ($pager['pages'] as $number)
                    @if ($number === null)
                        <span class="sfk-page-gap" aria-hidden="true">…</span>
                    @else
                        <button type="button" @class(['sfk-page-btn', 'is-current' => $number === $pager['current']])
                            wire:click="gotoAppPage({{ $number }})"
                            @if ($number === $pager['current']) aria-current="page" @endif>{{ $number }}</button>
                    @endif
                @endforeach
                <button type="button" class="sfk-page-btn" wire:click="nextAppPage" @disabled(! $pager['next']) title="次へ">
                    <x-filament::icon :icon="Heroicon::ArrowRight" class="sfk-page-icon" />
                    <span class="fi-sr-only">次へ</span>
                </button>
            </nav>
        </div>
    </div>
@endif
