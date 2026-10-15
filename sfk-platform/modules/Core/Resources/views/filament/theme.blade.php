{{--
    legacy-style look for every Filament panel (rendered at the end of <head> by BasePanelProvider):
    light grey page, flat white boxes with thin grey borders, legacy blue (#3498db) actions,
    small corner radii, Meiryo-first Japanese font stack, spreadsheet-like list header.
    Keep the colours in sync with BasePanelProvider::BRAND_* and the portal layout.
--}}
<style>
    :root {
        --font-family: "メイリオ", Meiryo, "Hiragino Kaku Gothic ProN", "Hiragino Sans", "Noto Sans JP", "Yu Gothic UI", sans-serif !important;
        --radius-sm: 2px;
        --radius-md: 3px;
        --radius-lg: 4px;
        --radius-xl: 4px;
        --radius-2xl: 4px;
        --kt-blue: #3498db;
        --kt-blue-dark: #217dbb;
        --kt-button: #1f78b8;
        --kt-button-hover: #1a6699;
        --kt-blue-soft: #ebf5fb;
        --kt-border: #e3e7e8;
        --kt-page: #f5f5f5;
        --kt-header: #f7f9fa;
        --kt-text: #333333;
        --kt-muted: #888888;
    }

    body.fi-body { background-color: var(--kt-page); color: var(--kt-text); }

    /* Header bar: white with a hairline, like the legacy system's global header */
    .fi-topbar { box-shadow: 0 1px 0 var(--kt-border) !important; }

    /* Sidebar: white column, active item marked with a blue bar */
    .fi-sidebar { background-color: #ffffff; border-inline-end: 1px solid var(--kt-border); }
    .fi-sidebar-group-label { color: var(--kt-muted); font-weight: 700; }
    .fi-sidebar-item-btn { border-radius: 0; }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background-color: var(--kt-blue-soft);
        box-shadow: inset 3px 0 0 var(--kt-blue);
    }

    /* Boxes: flat, 1px border, no drop shadow */
    .fi-section:not(.fi-section-not-contained):not(.fi-aside),
    .fi-ta-ctn,
    .fi-wi-stats-overview-stat,
    .fi-wi-chart .fi-section,
    .fi-simple-main,
    .fi-dropdown-panel,
    .fi-modal-window {
        box-shadow: 0 0 0 1px var(--kt-border) !important;
    }
    .fi-section-header { background-color: var(--kt-header); }
    .fi-section-header-heading { font-weight: 700; }

    /* Record list: grey header row with vertical rules, light-blue hover like the legacy system's list view */
    .fi-ta-table > thead > tr { background-color: var(--kt-header); }
    .fi-ta-header-cell {
        color: #666666;
        font-weight: 700;
        border-inline-end: 1px solid var(--kt-border);
    }
    .fi-ta-header-cell:last-child { border-inline-end: 0; }
    .fi-ta-table > tbody > tr > td { border-inline-end: 1px solid #f0f2f3; }
    .fi-ta-table > tbody > tr > td:last-child { border-inline-end: 0; }
    .fi-ta-row:hover > td { background-color: var(--kt-blue-soft); }

    /* Buttons: bold labels, legacy-style square corners. Filled primary buttons get the legacy system's blue with
       white text; one step darker than #3498db so the label keeps 4.5:1 contrast. */
    .fi-btn { font-weight: 700; }
    .fi-btn.fi-color-primary[class*="fi-bg-color-"] {
        background-color: var(--kt-button) !important;
        color: #ffffff !important;
        --text: #ffffff;
        --hover-text: #ffffff;
    }
    .fi-btn.fi-color-primary[class*="fi-bg-color-"]:hover { background-color: var(--kt-button-hover) !important; }
    .fi-btn.fi-color-primary[class*="fi-bg-color-"] .fi-icon { color: #ffffff !important; }

    /* Headings */
    .fi-header-heading { font-size: 1.375rem; font-weight: 700; color: var(--kt-text); }

    /* Login page: grey background, plain white box */
    .fi-simple-layout { background-color: var(--kt-page); }

    /* legacy record forms and record pages (Modules\Core\Filament\Records\RecordLayout, class kt-record) */
    .kt-record .fi-in-entry-label { font-size: .75rem; font-weight: 400; color: var(--kt-muted); }
    .kt-record .fi-in-entry-content { min-height: 1.75rem; padding: .125rem 0 .25rem; border-bottom: 1px solid var(--kt-border); font-size: .875rem; color: var(--kt-text); }
    .kt-record .fi-fo-field-label { font-size: .8125rem; }
    .kt-record-label { padding-top: .5rem; font-size: .8125rem; font-weight: 700; color: var(--kt-text); }

    /* App screens (Modules\Core\Filament\AppPages, core::filament.app-header): title bar, breadcrumb, description, toolbar */
    .sfk-app-page .fi-page-header-main-ctn { row-gap: 0; }
    .sfk-app-header { display: flex; flex-direction: column; margin-bottom: .75rem; }
    .sfk-app-titlebar {
        display: flex; align-items: center; gap: .625rem; padding: .5rem .875rem;
        background-color: var(--kt-blue); color: #ffffff;
    }
    .sfk-app-icon {
        display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
        width: 2rem; height: 2rem; border-radius: var(--radius-md); background: #ffffff; color: var(--kt-blue-dark);
        font-size: .6875rem; font-weight: 700; line-height: 1; overflow: hidden; white-space: nowrap;
    }
    .sfk-app-title { min-width: 0; font-size: 1.125rem; font-weight: 700; line-height: 1.4; }
    .sfk-app-title a { color: #ffffff; }
    .sfk-app-title a:hover { text-decoration: underline; }
    .sfk-app-crumbs {
        display: flex; flex-wrap: wrap; align-items: center; gap: .375rem; padding: .375rem .875rem;
        background: #ffffff; border-inline: 1px solid var(--kt-border); font-size: .8125rem; color: var(--kt-muted);
    }
    .sfk-app-crumbs a { color: var(--kt-blue-dark); }
    .sfk-app-crumbs a:hover { text-decoration: underline; }
    .sfk-app-home { display: inline-flex; }
    .sfk-app-home-icon { width: 1.125rem; height: 1.125rem; }
    .sfk-app-crumb-sep { color: #bbbbbb; }
    .sfk-app-description {
        padding: .625rem .875rem; background: #f0f0f0; border: 1px solid var(--kt-border);
        font-size: .8125rem; line-height: 1.6; color: var(--kt-text); white-space: pre-line;
    }
    .sfk-app-toolbar {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem;
        padding: .625rem 0 0;
    }
    .sfk-app-toolbar-start, .sfk-app-toolbar-end { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .sfk-app-toolbar .fi-ac { gap: .5rem; }
    .sfk-app-view { position: relative; display: inline-flex; align-items: center; }
    .sfk-app-view-select {
        appearance: none; min-width: 12rem; padding: .4375rem 2.25rem .4375rem .75rem;
        background: #ffffff; border: 1px solid #c8cdd0; border-radius: var(--radius-sm);
        font-size: .9375rem; font-weight: 700; color: var(--kt-text); cursor: pointer;
    }
    .sfk-app-view-select:focus-visible { outline: 2px solid var(--kt-blue); outline-offset: 1px; }
    .sfk-app-view-chevron { position: absolute; right: .625rem; width: 1rem; height: 1rem; color: var(--kt-muted); pointer-events: none; }
    .sfk-app-add {
        display: inline-flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem;
        border-radius: 9999px; background: var(--kt-button); color: #ffffff;
    }
    .sfk-app-add:hover { background: var(--kt-button-hover); }
    .sfk-app-add:focus-visible, .sfk-app-tool:focus-visible, .sfk-app-pager-btn:focus-visible { outline: 2px solid var(--kt-blue); outline-offset: 2px; }
    .sfk-app-add-icon { width: 1.5rem; height: 1.5rem; }
    .sfk-app-tool {
        display: inline-flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem;
        border: 1px solid #c8cdd0; border-radius: var(--radius-sm); background: #ffffff; color: #666666;
    }
    .sfk-app-tool:hover { background: var(--kt-blue-soft); color: var(--kt-blue-dark); }
    .sfk-app-tool-icon { width: 1.375rem; height: 1.375rem; }
    .sfk-app-toolbar-end .fi-icon-btn {
        width: 2.5rem; height: 2.5rem; border: 1px solid #c8cdd0; border-radius: var(--radius-sm); background: #ffffff;
    }
    .sfk-app-pager { display: inline-flex; align-items: center; gap: .25rem; font-size: .8125rem; color: var(--kt-text); }
    .sfk-app-pager-bottom { display: flex; justify-content: flex-end; padding-top: .5rem; }
    .sfk-app-pager-text { padding: 0 .25rem; white-space: nowrap; }
    .sfk-app-pager-btn {
        display: inline-flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem;
        border-radius: var(--radius-sm); color: var(--kt-blue-dark);
    }
    .sfk-app-pager-btn:hover:not(:disabled) { background: var(--kt-blue-soft); }
    .sfk-app-pager-btn:disabled { color: #cccccc; cursor: default; }
    .sfk-app-pager-icon { width: 1rem; height: 1rem; }

    /* App list: the paging is in the toolbar; light-blue striped rows with vertical rules */
    .sfk-app-page-list .fi-ta-ctn .fi-pagination { display: none; }
    .sfk-app-page-list .fi-ta-row.fi-striped > td { background-color: #f2f8fc; }
    .sfk-app-page-list .fi-ta-row:hover > td { background-color: var(--kt-blue-soft); }
    .sfk-app-page-list .fi-ta-table > tbody > tr > td { border-inline-end: 1px solid var(--kt-border); }

    /* App record form / record page: light-blue ground, white inputs, tabs above the fields */
    .sfk-app-page-create .fi-page-content, .sfk-app-page-edit .fi-page-content, .sfk-app-page-view .fi-page-content {
        padding: 1rem; background: #e8f1f8; border: 1px solid #d3e3ef;
    }
    .sfk-app-page-create .kt-record, .sfk-app-page-edit .kt-record, .sfk-app-page-view .kt-record {
        background: transparent; box-shadow: none !important;
    }
    .sfk-app-page-create .kt-record .fi-section-content, .sfk-app-page-edit .kt-record .fi-section-content,
    .sfk-app-page-view .kt-record .fi-section-content { padding: .5rem 0; }
    .sfk-app-page-view .kt-record .fi-in-entry-content { background: #ffffff; padding-inline: .5rem; }
    .fi-required-mark, .fi-fo-field-label-required-mark { color: var(--kt-red, #e74c3c) !important; }
    .kt-record-tabbed { display: flex; flex-direction: column; gap: .5rem; }
    .kt-record-tabs { display: flex; flex-wrap: wrap; gap: 2px; border-bottom: 2px solid var(--kt-blue); }
    .kt-record-tab {
        padding: .5rem 1.25rem; background: #d6e6f2; color: var(--kt-text); font-size: .875rem; font-weight: 700;
        border-radius: var(--radius-sm) var(--radius-sm) 0 0;
    }
    .kt-record-tab:hover { background: #c4dbec; }
    .kt-record-tab.kt-active { background: var(--kt-blue); color: #ffffff; }
    .kt-record-tab:focus-visible { outline: 2px solid var(--kt-blue-dark); outline-offset: 1px; }
    .kt-record-label { padding: .125rem 0 .125rem .5rem; border-inline-start: 4px solid #555555; }
    .kt-record-empty { padding: .5rem 0; font-size: .875rem; color: var(--kt-muted); }

    /* Dashboard name with switcher (core::filament.dashboard-switcher) replaces the panel brand */
    .fi-sidebar-header-logo-ctn, .fi-topbar-start > a:has(> .fi-logo) { display: none !important; }
    .sfk-dashboard-switcher { display: flex; align-items: center; min-width: 0; }
    .sfk-dashboard-switcher-btn {
        display: inline-flex; align-items: center; gap: .375rem; max-width: 100%; padding: .25rem .5rem;
        border-radius: var(--radius-sm); font-size: 1.125rem; font-weight: 700; color: var(--kt-text);
    }
    .sfk-dashboard-switcher-btn:hover { background: var(--kt-blue-soft); color: var(--kt-blue); }
    .sfk-dashboard-switcher-btn:focus-visible { outline: 2px solid var(--kt-blue); outline-offset: 2px; }
    .sfk-dashboard-switcher-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sfk-dashboard-switcher-chevron { width: 1rem; height: 1rem; flex-shrink: 0; color: var(--kt-muted); }
</style>
