{{--
    Under an app list (Modules\Core\Filament\AppPages\AppListRecords): 表示件数 and the pages (core::filament.app-pager), and
    the app's quick-create panel when it adds records that way (AppResource::quickCreateComponent; opened by 新規 and ?new=1).
--}}
@include('core::filament.app-pager', ['page' => $page])

@if ($quickCreate = $page->quickCreate())
    @livewire($quickCreate['component'], $quickCreate['params'], key('sfk-quick-create'))
@endif
