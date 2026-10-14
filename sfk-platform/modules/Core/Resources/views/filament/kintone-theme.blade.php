{{--
    kintone-like look for every Filament panel (rendered at the end of <head> by BasePanelProvider):
    light grey page, flat white boxes with thin grey borders, kintone blue (#3498db) actions,
    small corner radii, Meiryo-first Japanese font stack, spreadsheet-like list header.
    Keep the colours in sync with BasePanelProvider::KINTONE_* and the portal layout.
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

    /* Header bar: white with a hairline, like kintone's global header */
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

    /* Record list: grey header row with vertical rules, light-blue hover like kintone's list view */
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

    /* Buttons: bold labels, kintone-style square corners. Filled primary buttons get kintone's blue with
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
</style>
