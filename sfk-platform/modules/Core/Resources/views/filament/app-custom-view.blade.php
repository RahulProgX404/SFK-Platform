{{-- カスタマイズ形式 view of an app list (Modules\Core\Filament\AppPages\AppListRecords::customListViews()): the app's screen, drawn with $page. --}}
@php($template = $this->customViewTemplate())

<div class="sfk-app-custom-view">
    @if ($template !== null)
        @include($template, ['page' => $this])
    @endif
</div>
