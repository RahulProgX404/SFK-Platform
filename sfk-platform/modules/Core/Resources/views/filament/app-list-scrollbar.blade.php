{{--
    Horizontal scroll bar of an app list kept at the bottom of the screen (Modules\Core\Filament\AppPages\AppListRecords):
    while the list is wider than the screen and its own bar at the end of the table is out of view (e.g. at the top
    of 100 rows), this bar is shown over the table's width and scrolls the table with it. Alpine sfkListScrollbar.
--}}
{{-- shown by its class (the position styles would undo an x-show) --}}
<div x-data="sfkListScrollbar" class="sfk-list-scroll" x-bind:class="{ 'is-visible': visible }" x-bind:style="{ left: left + 'px', width: width + 'px' }" aria-hidden="true">
    <div x-ref="bar" class="sfk-list-scroll-bar" x-on:scroll="fromBar()">
        <div class="sfk-list-scroll-spacer" x-bind:style="{ width: scrollWidth + 'px' }"></div>
    </div>
</div>
