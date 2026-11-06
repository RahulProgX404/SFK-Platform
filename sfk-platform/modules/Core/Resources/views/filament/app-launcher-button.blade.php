{{--
    アプリ in the top bar of the dashboard (ErpPanelProvider, where the search box was): opens the panel of all the apps
    over the whole screen (core::filament.portal, event `sfk-open-apps`).
--}}
<style>
    .sfk-apps-trigger { display: inline-flex; align-items: center; gap: 8px; height: 38px; padding: 0 14px 0 10px; border-radius: 9999px; background: var(--sfk-page); box-shadow: inset 0 0 0 1px var(--sfk-line); color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); }
    .sfk-apps-trigger:hover { background: var(--sfk-primary-soft); box-shadow: inset 0 0 0 1px var(--sfk-primary-soft-strong); color: var(--sfk-primary-dark); }
    .sfk-apps-trigger .fi-icon { width: 20px; height: 20px; color: var(--sfk-primary); }
</style>
<button type="button" class="sfk-apps-trigger" x-data x-on:click="$dispatch('sfk-open-apps')" title="アプリの一覧を開く">
    <x-filament::icon icon="heroicon-o-squares-2x2" />
    <span>アプリ</span>
</button>
