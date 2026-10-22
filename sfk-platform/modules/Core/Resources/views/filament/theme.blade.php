{{--
    legacy-style look for every Filament panel (rendered at the end of <head> by BasePanelProvider).
    Colours and sizes are the legacy vendor's published UI parts (MIT-licensed plugin stylesheet "51-modern-default"
    and UI component library): #3498db submit buttons (hover #1d6fa5), #f7f9fa "normal" buttons with blue labels
    (hover #c8d6dd), #e74c3c alert buttons (hover #bf2718), #d4d7d7 disabled, #e3e7e8 hairlines, inset-shadow inputs
    with a blue focus border, red error bars, grey tabs with a white selected tab, blue sub-table headers, square
    corners and a Meiryo-first font stack.
    Keep the colours in sync with BasePanelProvider::BRAND_* and the account layout (core::components.layouts.account).
--}}
<style>
    :root {
        --font-family: "メイリオ", Meiryo, "Hiragino Kaku Gothic ProN", "Hiragino Sans", "Noto Sans JP", "Yu Gothic UI", sans-serif !important;
        /* text sizes: Filament's text-sm (the everyday text) is 16px; the rest of the scale and every size of this
           theme follow it (× 16/14, whole pixels). Line heights stay relative to the size. */
        --text-xs: .875rem;
        --text-sm: 1rem;
        --text-base: 1.125rem;
        --text-lg: 1.3125rem;
        --text-xl: 1.4375rem;
        --text-2xl: 1.6875rem;
        --text-3xl: 2.125rem;
        --radius-sm: 0px;
        --radius-md: 0px;
        --radius-lg: 0px;
        --radius-xl: 0px;
        --radius-2xl: 0px;
        --kt-blue: #3498db;
        --kt-blue-dark: #1d6fa5;
        --kt-blue-soft: #e2f2fe;
        --kt-normal: #f7f9fa;
        --kt-normal-hover: #c8d6dd;
        --kt-red: #e74c3c;
        --kt-red-dark: #bf2718;
        --kt-green: #91c36c;
        --kt-disabled: #d4d7d7;
        --kt-border: #e3e7e8;
        --kt-tab-border: #c7cbcb;
        --kt-page: #f5f5f5;
        --kt-header: #f7f9fa;
        --kt-text: #333333;
        --kt-muted: #888888;
        /* table headers and field labels (key names): black, easy to read */
        --kt-label: #000000;
        --kt-input-shadow: 2px 2px 4px #f5f5f5 inset, -2px -2px 4px #f5f5f5 inset;
    }

    body.fi-body {
        background-color: var(--kt-page);
        color: var(--kt-text);
    }

    [x-cloak] {
        display: none !important;
    }

    /* No rounded corners anywhere (also Filament's own parts); radio buttons stay round so they read as radio buttons,
       and toasts are rounded (their own design, see the toasts below) */
    *,
    *::before,
    *::after {
        border-radius: 0 !important;
    }

    input[type="radio"],
    .fi-radio-input {
        border-radius: 9999px !important;
    }

    /* Header bar: white with a hairline */
    .fi-topbar {
        box-shadow: 0 1px 0 var(--kt-border) !important;
    }

    /* Sidebar: white column, active item marked with a blue bar */
    .fi-sidebar {
        background-color: #ffffff;
        border-inline-end: 1px solid var(--kt-border);
    }

    .fi-sidebar-group-label {
        color: var(--kt-muted);
        font-weight: 700;
    }

    .fi-sidebar-item-btn {
        border-radius: 0;
    }

    .fi-sidebar-item.fi-active>.fi-sidebar-item-btn {
        background-color: var(--kt-blue-soft);
        box-shadow: inset 3px 0 0 var(--kt-blue);
    }

    /* Boxes: flat, 1px border, no drop shadow */
    .fi-section:not(.fi-section-not-contained):not(.fi-aside),
    .fi-ta-ctn,
    .fi-wi-stats-overview-stat,
    .fi-wi-chart .fi-section,
    .fi-simple-main,
    .fi-modal-window {
        box-shadow: 0 0 0 1px var(--kt-border) !important;
    }

    .fi-section-header {
        background-color: var(--kt-header);
    }

    .fi-section-header-heading {
        font-weight: 700;
    }

    /* Record list: grey header row with vertical rules, light-blue hover */
    .fi-ta-table>thead>tr {
        background-color: var(--kt-header);
    }

    .fi-ta-header-cell {
        color: var(--kt-label);
        font-weight: 700;
        border-inline-end: 1px solid var(--kt-border);
    }

    .fi-ta-header-cell:last-child {
        border-inline-end: 0;
    }

    .fi-ta-table>tbody>tr>td {
        border-inline-end: 1px solid #f0f2f3;
    }

    /* no bold values in any table column (only the header row is bold) */
    .fi-ta-table>tbody td,
    .fi-ta-table>tbody td * {
        font-weight: 400 !important;
    }

    .fi-ta-table>tbody>tr>td:last-child {
        border-inline-end: 0;
    }

    .fi-ta-row:hover>td {
        background-color: var(--kt-blue-soft);
    }

    /* Row icons (編集する / 削除する) at the right end: grey, blue on hover */
    /* .fi-ta-actions .fi-icon-btn { color: var(--kt-muted); } */
    .fi-ta-actions .fi-icon-btn:hover {
        color: var(--kt-blue);
        background: transparent;
    }

    /* Buttons: submit (filled blue), normal (light grey with blue label), alert (red), disabled (grey) */
    .fi-btn {
        font-weight: 400;
        border-radius: 0;
    }

    .fi-btn.fi-color-primary[class*="fi-bg-color-"] {
        background-color: var(--kt-blue) !important;
        color: #ffffff !important;
        box-shadow: 0 0 0 1px var(--kt-border) !important;
        --text: #ffffff;
        --hover-text: #ffffff;
    }

    .fi-btn.fi-color-primary[class*="fi-bg-color-"]:hover {
        background-color: var(--kt-blue-dark) !important;
    }

    .fi-btn.fi-color-primary[class*="fi-bg-color-"] .fi-icon {
        color: #ffffff !important;
    }

    .fi-btn.fi-color-danger[class*="fi-bg-color-"] {
        background-color: var(--kt-red) !important;
        color: #ffffff !important;
        box-shadow: 0 0 0 1px var(--kt-border) !important;
        --text: #ffffff;
        --hover-text: #ffffff;
    }

    .fi-btn.fi-color-danger[class*="fi-bg-color-"]:hover {
        background-color: var(--kt-red-dark) !important;
    }

    .fi-btn.fi-color-danger[class*="fi-bg-color-"] .fi-icon {
        color: #ffffff !important;
    }

    .fi-btn:not(.fi-outlined):not([class*="fi-bg-color-"]),
    .fi-btn.fi-color-gray:not(.fi-outlined) {
        background-color: var(--kt-normal) !important;
        color: var(--kt-blue) !important;
        box-shadow: 1px 1px 1px #ffffff inset, 0 0 0 1px var(--kt-border) !important;
        --text: var(--kt-blue);
        --hover-text: var(--kt-blue);
    }

    .fi-btn:not(.fi-outlined):not([class*="fi-bg-color-"]):hover,
    .fi-btn.fi-color-gray:not(.fi-outlined):hover {
        background-color: var(--kt-normal-hover) !important;
    }

    .fi-btn:not(.fi-outlined):not([class*="fi-bg-color-"]) .fi-icon,
    .fi-btn.fi-color-gray:not(.fi-outlined) .fi-icon {
        color: var(--kt-blue) !important;
    }

    .fi-btn.fi-disabled,
    .fi-btn[disabled] {
        background-color: var(--kt-disabled) !important;
        color: var(--kt-muted) !important;
        opacity: 1;
    }

    .fi-btn:focus-visible,
    .fi-icon-btn:focus-visible {
        outline: 1px solid var(--kt-blue);
        outline-offset: 1px;
    }

    /* Inputs: hairline border with a soft inset shadow, blue border on focus, grey when disabled */
    .fi-input-wrp {
        border-radius: 0;
        background-color: #ffffff;
        box-shadow: var(--kt-input-shadow), 0 0 0 1px var(--kt-border) !important;
    }

    /* date inputs keep the legacy narrow width (117px): less side padding so 「2026/10/22」 and the calendar fit at 16px */
    input[type=date].fi-input,
    input[type=datetime-local].fi-input {
        padding-inline: .5rem;
    }

    /* the whole date / time field opens its picker (script at the end) */
    input:is([type=date], [type=time], [type=datetime-local], [type=month], [type=week]):not(:disabled):not([readonly]) {
        cursor: pointer;
    }

    input[type=date]::-webkit-calendar-picker-indicator,
    input[type=datetime-local]::-webkit-calendar-picker-indicator {
        margin-inline-start: 2px;
    }

    .fi-input-wrp:not(.fi-disabled):focus-within {
        box-shadow: var(--kt-input-shadow), 0 0 0 1px var(--kt-blue) !important;
    }

    .fi-input-wrp.fi-invalid {
        box-shadow: var(--kt-input-shadow), 0 0 0 1px var(--kt-red) !important;
    }

    .fi-input-wrp.fi-disabled {
        background-color: var(--kt-disabled);
        box-shadow: 0 0 0 1px var(--kt-border) !important;
    }

    .fi-input-wrp.fi-disabled .fi-input {
        color: var(--kt-muted);
    }

    /* Fields that cannot be typed into say so: disabled inputs and, on record forms, the values shown read-only
       (calculated fields, lookup copies such as 締日) look like a disabled input with the not-allowed cursor */
    .fi-input-wrp.fi-disabled,
    .fi-input-wrp.fi-disabled *,
    .fi-input:disabled,
    select:disabled,
    textarea:disabled,
    input[type="checkbox"]:disabled,
    input[type="radio"]:disabled,
    .fi-checkbox-input:disabled,
    .fi-radio-input:disabled {
        cursor: not-allowed !important;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit) .fi-in-entry-content {
        display: flex;
        align-items: center;
        min-height: 36px !important;
        padding: 4px 10px !important;
        border: 0 !important;
        background-color: var(--kt-disabled);
        box-shadow: 0 0 0 1px var(--kt-border);
        color: var(--kt-text);
        cursor: not-allowed;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit) .fi-in-entry-content * {
        cursor: not-allowed;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit) .fi-in-entry-content.fi-align-end {
        justify-content: flex-end;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit) .fi-in-entry-label {
        color: var(--kt-label);
        font-size: .9375rem;
    }

    .fi-input {
        color: #000000;
    }

    /* Drop-down fields: light grey with a blue value */
    .fi-input-wrp:has(> .fi-input-wrp-content-ctn > .fi-select-input):not(.fi-disabled),
    .fi-input-wrp:has(> .fi-select-input):not(.fi-disabled) {
        background-color: var(--kt-normal);
        box-shadow: 1px 1px 1px #ffffff inset, 0 0 0 1px var(--kt-border) !important;
    }

    .fi-input-wrp:not(.fi-disabled) .fi-select-input {
        color: var(--kt-blue);
    }

    .fi-checkbox-input,
    .fi-radio-input {
        border-radius: 0;
    }

    .fi-radio-input {
        border-radius: 9999px;
    }

    /* Field labels, required asterisk and the red error bar under a field */
    .fi-fo-field-label-content {
        color: var(--kt-label);
    }

    .fi-required-mark,
    .fi-fo-field-label-required-mark {
        color: var(--kt-red) !important;
    }

    .fi-fo-field-wrp-error-message {
        padding: 4px 18px;
        background-color: var(--kt-red);
        color: #ffffff !important;
        font-size: 1rem;
        line-height: 1.5;
    }

    /* Drop-down menus: white panel with a soft shadow, light-blue highlighted item */
    .fi-dropdown-panel {
        border-radius: 0;
        box-shadow: 0 5px 10px rgba(0, 0, 0, .1) !important;
    }

    .fi-dropdown-list-item {
        border-radius: 0;
        color: var(--kt-text);
    }

    .fi-dropdown-list-item:hover,
    .fi-dropdown-list-item:focus-visible {
        background-color: var(--kt-blue-soft) !important;
    }

    /* Dialogs: white header with a large plain title, light-grey body, footer with the buttons */
    .fi-modal-window {
        border-radius: 0;
    }

    .fi-modal-window:has(.fi-modal-content) .fi-modal-header {
        min-height: 64px;
        border-bottom: 1px solid var(--kt-border);
    }

    .fi-modal-heading {
        font-size: 1.6875rem;
        font-weight: 400;
        color: var(--kt-text);
    }

    .fi-modal-content {
        background-color: var(--kt-header);
        padding: 24px;
    }

    .fi-modal-footer {
        padding: 24px;
        border-top: 1px solid var(--kt-border);
    }

    .fi-modal-footer .fi-btn {
        min-width: 163px;
        max-width: 100%;
        min-height: 48px;
        font-size: 1.125rem;
    }

    /*
     * Toasts (design_ref toad-notification.png), top middle of the screen (CoreServiceProvider): a tinted box with a
     * border of its colour, the solid status icon drawn white on a coloured tile (the glyph shows the tile through it),
     * the text and ×. The one exception to the square corners: toasts and their icon tile are rounded.
     */
    .fi-no-notification:not(.fi-inline) {
        --sfk-toast: #64748b;
        --sfk-toast-bg: #f8fafc;
        --sfk-toast-border: #cbd5e1;
        align-items: center;
        gap: 1rem;
        width: auto;
        min-width: min(22rem, calc(100vw - 2rem));
        max-width: min(40rem, calc(100vw - 2rem));
        padding: .75rem;
        background-color: var(--sfk-toast-bg) !important;
        border: 1.5px solid var(--sfk-toast-border);
        border-radius: 1rem !important;
        box-shadow: 0 8px 20px -6px rgba(15, 23, 42, .12) !important;
        --tw-ring-color: transparent !important;
    }

    .fi-no-notification.fi-status-success:not(.fi-inline) {
        --sfk-toast: #34b45a;
        --sfk-toast-bg: #eaf6ed;
        --sfk-toast-border: #b5e0c1;
    }

    .fi-no-notification.fi-status-info:not(.fi-inline) {
        --sfk-toast: #0b6bdc;
        --sfk-toast-bg: #e6eff9;
        --sfk-toast-border: #a9c8ec;
    }

    .fi-no-notification.fi-status-warning:not(.fi-inline) {
        --sfk-toast: #f0960e;
        --sfk-toast-bg: #fef7ea;
        --sfk-toast-border: #f5dca8;
    }

    .fi-no-notification.fi-status-danger:not(.fi-inline) {
        --sfk-toast: #e8502f;
        --sfk-toast-bg: #fbeceb;
        --sfk-toast-border: #f0bdb6;
    }

    .fi-no-notification:not(.fi-inline) .fi-no-notification-icon {
        flex: none;
        box-sizing: border-box;
        width: 3rem;
        height: 3rem;
        padding: .375rem;
        background: var(--sfk-toast);
        border-radius: .875rem !important;
        box-shadow: 0 4px 10px -2px color-mix(in srgb, var(--sfk-toast) 45%, transparent);
        color: #ffffff !important;
    }

    .fi-no-notification:not(.fi-inline) .fi-no-notification-main {
        margin-top: 0;
        padding-inline-start: .25rem;
    }

    .fi-no-notification:not(.fi-inline) .fi-no-notification-title {
        color: #1f2937;
        font-size: 1.125rem;
        font-weight: 400;
        line-height: 1.5;
    }

    .fi-no-notification:not(.fi-inline) .fi-no-notification-body {
        color: #4b5563 !important;
        font-size: 1rem;
    }

    .fi-no-notification:not(.fi-inline) .fi-no-notification-close-btn {
        flex: none;
        align-self: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: .625rem !important;
        color: #5f6b7a;
        transition: background-color .15s, color .15s, box-shadow .15s;
    }

    .fi-no-notification:not(.fi-inline) .fi-no-notification-close-btn .fi-icon {
        width: 1.375rem;
        height: 1.375rem;
    }

    .fi-no-notification:not(.fi-inline) .fi-no-notification-close-btn:hover,
    .fi-no-notification:not(.fi-inline) .fi-no-notification-close-btn:focus-visible {
        background: #ffffff;
        color: #111827;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .08);
    }

    /* Tabs: grey tabs with a white selected tab */
    .fi-tabs {
        gap: 0;
        padding: 0;
        border-radius: 0;
        background: transparent;
        box-shadow: none !important;
        border-bottom: 1px solid var(--kt-tab-border);
    }

    .fi-tabs-item {
        min-height: 48px;
        padding: 0 24px;
        border-radius: 0;
        background-color: var(--kt-disabled);
        color: var(--kt-text);
        border-top: 1px solid var(--kt-tab-border);
        border-left: 1px solid var(--kt-tab-border);
        box-shadow: 1px 0 3px var(--kt-tab-border) inset;
    }

    .fi-tabs-item:last-child {
        border-right: 1px solid var(--kt-tab-border);
    }

    .fi-tabs-item:hover {
        background-color: #e1e3e3;
    }

    .fi-tabs-item.fi-active {
        background-color: #ffffff;
        box-shadow: none;
    }

    .fi-tabs-item .fi-tabs-item-label,
    .fi-tabs-item.fi-active .fi-tabs-item-label {
        color: var(--kt-text);
    }

    /* Sub-tables (table repeaters): blue header row, small white labels, hairline cells */
    .fi-fo-table-repeater table {
        border-collapse: collapse;
    }

    .fi-fo-table-repeater thead th {
        height: 40px;
        padding: 4px 8px;
        background-color: var(--kt-blue);
        color: #ffffff;
        font-size: .875rem;
        font-weight: 400;
        border-inline: 1px solid var(--kt-blue);
    }

    .fi-fo-table-repeater thead th.fi-fo-table-repeater-empty-header-cell {
        background-color: transparent;
        border-color: transparent;
    }

    .fi-fo-table-repeater tbody td {
        border: 1px solid var(--kt-border);
    }

    /* legacy table fields (Modules\Core\Filament\Records\RecordTable): legacy column widths, sideways scroll inside the
       record, ⊕ / ⊖ at the end of each row */
    .kt-record .fi-grid>*,
    .kt-record .fi-grid-col {
        min-width: 0;
    }

    /* position: the hidden cell labels (absolute) stay inside the scroll box instead of widening the page */
    .kt-subtable {
        position: relative;
        max-width: 100%;
        overflow-x: auto;
        padding-bottom: 4px;
    }

    .kt-subtable>table {
        display: table !important;
        width: var(--kt-subtable-width) !important;
        min-width: var(--kt-subtable-width);
        table-layout: fixed;
        border-collapse: collapse;
        box-shadow: none !important;
    }

    .kt-subtable>table>thead {
        display: table-header-group !important;
    }

    .kt-subtable>table>thead>tr>th {
        height: 32px;
        padding: 4px 6px;
        background-color: var(--kt-blue);
        color: #ffffff;
        font-size: .875rem;
        font-weight: 400;
        text-align: start;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .kt-subtable>table>thead>tr>th.fi-fo-table-repeater-empty-header-cell {
        width: 64px;
        background: transparent;
        border-color: transparent;
    }

    .kt-subtable>table>tbody {
        display: table-row-group !important;
    }

    .kt-subtable>table>tbody>tr {
        display: table-row !important;
        padding: 0 !important;
    }

    .kt-subtable>table>tbody>tr>td {
        display: table-cell !important;
        padding: 4px 6px;
        vertical-align: middle;
        border: 1px solid var(--kt-border);
        background: #ffffff;
    }

    .kt-subtable>table>tbody>tr>td:last-child:has(.fi-fo-table-repeater-actions) {
        border-color: transparent;
        background: transparent;
    }

    .kt-subtable .fi-fo-table-repeater-actions {
        gap: 2px;
    }

    .kt-subtable .fi-in-entry-content {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .kt-subtable-label {
        font-size: .9375rem;
        color: var(--kt-label);
        margin-bottom: 4px;
    }

    /* Headings */
    .fi-header-heading {
        font-size: 1.5625rem;
        font-weight: 700;
        color: var(--kt-text);
    }

    /* Login page: grey background, plain white box */
    .fi-simple-layout {
        background-color: var(--kt-page);
    }

    /* legacy record forms and record pages (Modules\Core\Filament\Records\RecordLayout, class kt-record) */
    .kt-record .fi-in-entry-label {
        font-size: .875rem;
        font-weight: 400;
        color: var(--kt-label);
    }

    .kt-record .fi-in-entry-content {
        min-height: 1.75rem;
        padding: .125rem 0 .25rem;
        border-bottom: 1px solid var(--kt-border);
        font-size: 1rem;
        color: var(--kt-text);
    }

    .kt-record .fi-fo-field-label {
        font-size: .9375rem;
    }


    /* App screens (Modules\Core\Filament\AppPages, core::filament.app-header): title bar, breadcrumb, description, toolbar */
    /* The app covers the whole content area: no spacing around it, full width, stretched to the screen height */
    .fi-main:has(.sfk-app-page) {
        display: flex;
        flex-direction: column;
        padding: 0;
    }

    .sfk-app-page {
        display: flex;
        flex: 1;
        flex-direction: column;
        background: #ffffff;
    }

    .sfk-app-page>.fi-page-header-main-ctn {
        display: flex;
        flex: 1;
        flex-direction: column;
        row-gap: 0;
        padding: 0;
    }

    .sfk-app-page .fi-page-main {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 0;
    }

    .sfk-app-page .fi-page-content {
        flex: 1;
        align-content: start;
    }

    .sfk-app-header {
        display: flex;
        flex-direction: column;
    }

    .sfk-app-titlebar {
        display: flex;
        align-items: center;
        gap: .625rem;
        padding: .5rem .875rem;
        background-color: var(--kt-blue);
        color: #ffffff;
    }

    .sfk-app-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 2rem;
        height: 2rem;
        background: #ffffff;
        color: var(--kt-blue-dark);
        font-size: .8125rem;
        font-weight: 700;
        line-height: 1;
        overflow: hidden;
        white-space: nowrap;
    }

    .sfk-app-title {
        min-width: 0;
        font-size: 1.3125rem;
        font-weight: 700;
        line-height: 1.4;
    }

    .sfk-app-title a {
        color: #ffffff;
    }

    .sfk-app-title a:hover {
        text-decoration: underline;
    }

    .sfk-app-crumbs {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .375rem;
        padding: .375rem .875rem;
        background: #ffffff;
        border-bottom: 1px solid var(--kt-border);
        font-size: .9375rem;
        color: var(--kt-muted);
    }

    .sfk-app-crumbs a {
        color: var(--kt-blue-dark);
    }

    .sfk-app-crumbs a:hover {
        text-decoration: underline;
    }

    .sfk-app-home {
        display: inline-flex;
    }

    .sfk-app-home-icon {
        width: 1.125rem;
        height: 1.125rem;
    }

    .sfk-app-crumb-sep {
        color: #bbbbbb;
    }

    .sfk-app-description {
        padding: .625rem .875rem;
        background: #f0f0f0;
        border-bottom: 1px solid var(--kt-border);
        font-size: .9375rem;
        line-height: 1.6;
        color: var(--kt-text);
        white-space: pre-line;
    }

    /*
     * The app's legacy buttons (core::filament.app-menu, MenuButtons): segmented groups of icon + label buttons in the
     * look of the toolbar tools (40px, --kt-normal, inset highlight), the buttons on the checked rows tinted once rows
     * are checked.
     */
    .sfk-app-menu-divider {
        align-self: stretch;
        width: 1px;
        margin: .25rem .125rem;
        background: var(--kt-border);
    }

    .sfk-app-menu {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem;
    }

    .sfk-app-menu-group {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: stretch;
        column-gap: 6px;
    }

    .sfk-app-menu-group>*+* {
        margin-inline-start: -1px;
    }

    .sfk-app-toolbar .sfk-menu-btn.fi-btn {
        position: relative;
        height: 2.5rem;
        gap: .375rem;
        padding: 0 .875rem;
        background: var(--kt-normal);
        border: 1px solid var(--kt-border);
        box-shadow: 1px 1px 1px #ffffff inset;
        color: var(--kt-blue-dark);
        font-size: .9375rem;
        font-weight: 400;
        white-space: nowrap;
    }

    .sfk-app-toolbar .sfk-menu-btn.fi-btn .fi-icon {
        width: 1.125rem;
        height: 1.125rem;
        color: var(--kt-blue);
    }

    .sfk-app-toolbar .sfk-menu-btn.fi-btn:hover {
        z-index: 1;
        background: var(--kt-normal-hover);
        color: var(--kt-blue-dark);
    }

    .sfk-app-toolbar .sfk-menu-btn.fi-btn:focus-visible {
        z-index: 2;
        outline: 2px solid var(--kt-blue);
        outline-offset: 1px;
    }

    /* 「N件」 badge of the checked rows on the corner of a button (MenuButtons::withCheckedCount()); red once rows are checked */
    .sfk-menu-btn-counted {
        position: relative;
        display: inline-flex;
    }

    .sfk-menu-badge {
        position: absolute;
        top: -.5rem;
        right: -.375rem;
        z-index: 3;
        min-width: 1.25rem;
        padding: .0625rem .3125rem;
        background: var(--kt-muted);
        border: 2px solid #ffffff;
        color: #ffffff;
        font-size: .8125rem;
        font-weight: 700;
        line-height: 1rem;
        text-align: center;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        pointer-events: none;
    }

    .sfk-app-menu-rows.has-checked .sfk-menu-badge {
        background: #e41e3f;
    }

    .sfk-app-menu-rows.has-checked .sfk-menu-btn.fi-btn {
        background: var(--kt-blue-soft);
    }

    .sfk-app-menu-rows.has-checked .sfk-menu-btn.fi-btn:hover {
        background: var(--kt-normal-hover);
    }

    /* ボタンの説明 (core::filament.app-menu-help): the legacy menu text as a collapsible list under the toolbar */
    .sfk-app-help {
        margin: 0 .875rem .625rem;
        background: #ffffff;
        border: 1px solid var(--kt-border);
        border-left: 3px solid var(--kt-blue);
    }

    .sfk-app-help-bar {
        display: flex;
        align-items: center;
        gap: .375rem;
        width: 100%;
        padding: .375rem .75rem;
        font-size: .9375rem;
        color: var(--kt-blue-dark);
        text-align: left;
    }

    .sfk-app-help-bar:hover {
        background: var(--kt-normal);
    }

    .sfk-app-help-bar:focus-visible {
        outline: 2px solid var(--kt-blue);
        outline-offset: -2px;
    }

    .sfk-app-help-icon {
        width: 1rem;
        height: 1rem;
        color: var(--kt-blue);
    }

    .sfk-app-help-title {
        flex: 1;
    }

    .sfk-app-help-chevron {
        width: 1rem;
        height: 1rem;
        color: var(--kt-muted);
        transition: transform .15s ease;
    }

    .sfk-app-help-chevron.is-open {
        transform: rotate(180deg);
    }

    .sfk-app-help-list {
        display: grid;
        grid-template-columns: max-content 1fr;
        column-gap: 1rem;
        margin: 0;
        padding: .25rem .75rem .625rem;
        border-top: 1px solid var(--kt-border);
        font-size: .9375rem;
        line-height: 1.6;
    }

    .sfk-app-help-item {
        display: contents;
    }

    .sfk-app-help-item dt {
        padding-top: .375rem;
        color: var(--kt-label);
        white-space: nowrap;
    }

    .sfk-app-help-item dd {
        margin: 0;
        padding-top: .375rem;
        color: var(--kt-text);
    }

    @media (max-width: 640px) {
        .sfk-app-help-list {
            grid-template-columns: 1fr;
        }

        .sfk-app-help-item dd {
            padding-top: 0;
        }
    }

    /* a notice inside a dialog (e.g. 「これはテスト表示です」): white box, red rule on the left */
    .sfk-notice {
        display: flex;
        align-items: flex-start;
        gap: .5rem;
        margin: 0;
        padding: .5rem .75rem;
        background: #ffffff;
        border: 1px solid var(--kt-border);
        border-left: 3px solid var(--kt-red);
        color: var(--kt-text);
        font-size: .9375rem;
        line-height: 1.6;
    }

    .sfk-notice-icon {
        flex: none;
        width: 1.125rem;
        height: 1.125rem;
        margin-top: .125rem;
        color: var(--kt-red);
    }

    /* a read-only table inside a dialog, in the look of the app lists (grey header, black bold labels, ruled cells) */
    .sfk-data-table-wrap {
        max-height: 60vh;
        overflow: auto;
        border: 1px solid var(--kt-border);
    }

    .sfk-data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: .9375rem;
        white-space: nowrap;
    }

    .sfk-data-table th {
        position: sticky;
        top: 0;
        z-index: 1;
        padding: .5rem .75rem;
        background: var(--kt-header);
        border-bottom: 1px solid var(--kt-border);
        border-inline-end: 1px solid var(--kt-border);
        color: var(--kt-label);
        font-weight: 700;
        text-align: left;
    }

    .sfk-data-table td {
        padding: .5rem .75rem;
        border-bottom: 1px solid var(--kt-border);
        border-inline-end: 1px solid #f0f2f3;
        color: var(--kt-text);
    }

    .sfk-data-table th:last-child,
    .sfk-data-table td:last-child {
        border-inline-end: 0;
    }

    .sfk-data-table .is-number {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .sfk-data-table tbody tr:hover>td {
        background: var(--kt-blue-soft);
    }

    /* a preview dialog: notice, the counts per kind, the table */
    .sfk-preview {
        display: grid;
        gap: .75rem;
    }

    .sfk-preview-counts {
        display: flex;
        flex-wrap: wrap;
        gap: .375rem;
        margin: 0;
        padding: 0;
        list-style: none;
        font-size: .9375rem;
    }

    .sfk-preview-counts li {
        padding: .25rem .625rem;
        background: var(--kt-normal);
        border: 1px solid var(--kt-border);
        color: var(--kt-text);
    }

    .sfk-preview-counts strong {
        color: var(--kt-blue-dark);
        font-variant-numeric: tabular-nums;
    }

    .sfk-app-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        padding: .625rem .875rem;
    }

    .sfk-app-toolbar-start,
    .sfk-app-toolbar-end {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem;
    }

    /* the right group stays right also when there is nothing on the left (record page) */
    .sfk-app-toolbar-end {
        margin-left: auto;
    }

    .sfk-app-toolbar .fi-ac {
        gap: .5rem;
    }

    /* an action given the class sfk-toolbar-end sits at the right end of the record-form toolbar */
    .sfk-app-toolbar-start:has(.sfk-toolbar-end) {
        flex: 1;
    }

    .sfk-app-toolbar-start:has(.sfk-toolbar-end)>.fi-ac {
        flex: 1;
    }

    .sfk-app-toolbar .sfk-toolbar-end {
        margin-inline-start: auto;
    }

    .sfk-app-view {
        position: relative;
        display: inline-flex;
        align-items: center;
    }

    .sfk-app-view-select {
        appearance: none;
        min-width: 12rem;
        height: 40px;
        padding: 0 2.5rem 0 1.5rem;
        background: var(--kt-normal);
        border: 1px solid var(--kt-border);
        box-shadow: 1px 1px 1px #ffffff inset;
        font-size: 1rem;
        color: var(--kt-blue);
        cursor: pointer;
    }

    .sfk-app-view-select:hover {
        background-color: var(--kt-normal-hover);
    }

    .sfk-app-view-select:focus-visible {
        outline: none;
        border-color: var(--kt-blue);
    }

    .sfk-app-view-chevron {
        position: absolute;
        right: .75rem;
        width: 1rem;
        height: 1rem;
        color: var(--kt-blue);
        pointer-events: none;
    }

    .sfk-app-add {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        background: var(--kt-blue);
        color: #ffffff;
    }

    .sfk-app-add:hover {
        background: var(--kt-blue-dark);
    }

    .sfk-app-add:focus-visible,
    .sfk-app-tool:focus-visible,
    .sfk-app-pager-btn:focus-visible {
        outline: 2px solid var(--kt-blue);
        outline-offset: 2px;
    }

    .sfk-app-add-icon {
        width: 1.5rem;
        height: 1.5rem;
    }

    .sfk-app-tool {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        border: 1px solid var(--kt-border);
        background: var(--kt-normal);
        box-shadow: 1px 1px 1px #ffffff inset;
        color: var(--kt-muted);
    }

    .sfk-app-tool:hover {
        background: var(--kt-normal-hover);
        color: var(--kt-blue-dark);
    }

    .sfk-app-tool-icon {
        width: 1.375rem;
        height: 1.375rem;
    }

    .sfk-app-toolbar .fi-icon-btn {
        width: 2.5rem;
        height: 2.5rem;
        border: 1px solid var(--kt-border);
        background: var(--kt-normal);
        box-shadow: 1px 1px 1px #ffffff inset;
        color: var(--kt-muted);
    }

    .sfk-app-toolbar .fi-icon-btn:hover {
        background: var(--kt-normal-hover);
        color: var(--kt-blue-dark);
    }

    .sfk-app-pager {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        font-size: .9375rem;
        color: var(--kt-text);
    }

    .sfk-app-pager-bottom {
        display: flex;
        justify-content: flex-end;
        padding: .5rem .875rem;
    }

    .sfk-app-pager-text {
        padding: 0 .25rem;
        white-space: nowrap;
    }

    .sfk-app-pager-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        color: var(--kt-blue);
    }

    .sfk-app-pager-btn:hover:not(:disabled):not(.is-disabled) {
        background: var(--kt-blue-soft);
    }

    .sfk-app-pager-btn:disabled,
    .sfk-app-pager-btn.is-disabled {
        color: #cccccc;
        cursor: default;
    }

    .sfk-app-pager-icon {
        width: 1rem;
        height: 1rem;
    }

    /* 絞り込む (core::filament.app-filter, design_ref/filter1-3.png): funnel in the list toolbar, blue while a filter is on */
    .sfk-app-tool.is-active {
        background: var(--kt-blue);
        border-color: var(--kt-blue);
        box-shadow: none;
        color: #ffffff;
    }

    .sfk-app-tool.is-active:hover {
        background: var(--kt-blue-dark);
        color: #ffffff;
    }

    .sfk-filter-backdrop {
        position: fixed;
        inset: 0;
        z-index: 40;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        background: rgba(0, 0, 0, .6);
    }

    .sfk-filter-dialog {
        position: relative;
        display: flex;
        flex-direction: column;
        width: min(830px, 100%);
        height: min(900px, 100%);
        background: #ffffff;
        color: var(--kt-text);
        box-shadow: 0 5px 10px rgba(0, 0, 0, .1);
    }

    .sfk-filter-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        min-height: 60px;
        padding: 0 14px 0 18px;
        border-bottom: 1px solid var(--kt-border);
    }

    .sfk-filter-title {
        margin: 0;
        font-size: 1.6875rem;
        font-weight: 400;
        line-height: 1.3;
    }

    .sfk-filter-close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        flex-shrink: 0;
        background: #f2f4f5;
        color: var(--kt-muted);
    }

    .sfk-filter-close:hover {
        background: var(--kt-normal-hover);
        color: var(--kt-text);
    }

    .sfk-filter-close-icon {
        width: 14px;
        height: 14px;
    }

    .sfk-filter-body {
        flex: 1;
        overflow-x: hidden;
        overflow-y: auto;
    }

    .sfk-filter-section {
        padding: 22px 18px 24px;
        background: var(--kt-normal);
        border-bottom: 1px solid var(--kt-border);
    }

    .sfk-filter-label {
        display: block;
        margin: 0 0 10px;
        font-size: 1rem;
        font-weight: 400;
        color: var(--kt-text);
    }

    .sfk-filter-required {
        margin-left: 2px;
        color: var(--kt-red);
    }

    .sfk-filter-match {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 24px;
        margin-bottom: 10px;
        font-size: 1rem;
    }

    .sfk-filter-match label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }

    .sfk-filter-box {
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding: 6px;
        background: #ffffff;
        border: 1px solid var(--kt-border);
    }

    .sfk-filter-row {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: 6px;
    }

    .sfk-filter-date-ctn,
    .sfk-filter-values {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: 6px;
    }

    .sfk-filter-value {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .sfk-filter-select select {
        appearance: none;
        height: 40px;
        min-width: 160px;
        max-width: 100%;
        padding: 0 34px 0 10px;
        border: 1px solid var(--kt-border);
        border-radius: 0;
        background-color: #ffffff;
        color: var(--kt-text);
        font-size: 1rem;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%233498db' stroke-width='1.5'%3E%3Cpath d='M3 6l5 5 5-5'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 16px;
        cursor: pointer;
    }

    .sfk-filter-select-op select {
        min-width: 80px;
        background-color: var(--kt-normal);
        box-shadow: 1px 1px 1px #ffffff inset;
        color: var(--kt-blue);
        font-weight: 700;
    }

    .sfk-filter-select select:hover {
        background-color: var(--kt-normal-hover);
    }

    .sfk-filter-select select:focus-visible,
    .sfk-filter-input:focus,
    .sfk-filter-listbox:focus {
        outline: none;
        border-color: var(--kt-blue);
    }

    .sfk-filter-input {
        width: 220px;
        max-width: 100%;
        height: 40px;
        padding: 0 8px;
        border: 1px solid var(--kt-border);
        border-radius: 0;
        background: #ffffff;
        color: #000000;
        font-size: 1rem;
        box-shadow: var(--kt-input-shadow);
    }

    .sfk-filter-input-date {
        width: 120px;
    }

    .sfk-filter-input-date:focus {
        background: var(--kt-blue-soft);
    }

    .sfk-filter-input-time {
        width: 88px;
    }

    .sfk-filter-listbox {
        width: 280px;
        max-width: 100%;
        height: 146px;
        padding: 2px 0;
        border: 1px solid var(--kt-border);
        border-radius: 0;
        background: #ffffff;
        color: var(--kt-text);
        font-size: 1rem;
        box-shadow: var(--kt-input-shadow);
    }

    .sfk-filter-listbox option {
        padding: 2px 14px;
    }

    .sfk-filter-listbox option:checked {
        background: var(--kt-blue-soft) linear-gradient(var(--kt-blue-soft), var(--kt-blue-soft));
        color: var(--kt-text);
    }

    .sfk-filter-round {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        flex-shrink: 0;
        margin: 11px 0;
        background: #c8c8c8;
        color: #ffffff;
    }

    .sfk-filter-round:hover {
        background: var(--kt-blue);
    }

    .sfk-filter-round:focus-visible,
    .sfk-filter-close:focus-visible,
    .sfk-filter-link:focus-visible {
        outline: 1px solid var(--kt-blue);
        outline-offset: 2px;
    }

    .sfk-filter-round-icon {
        width: 12px;
        height: 12px;
    }

    .sfk-filter-row-remove {
        margin-left: auto;
    }

    .sfk-filter-after {
        padding: 4px 18px 0;
    }

    .sfk-filter-link {
        padding: 0;
        background: none;
        color: var(--kt-blue);
        font-size: 1rem;
    }

    .sfk-filter-link:hover {
        text-decoration: underline;
    }

    .sfk-filter-clear {
        margin: 14px 0 0 16px;
    }

    .sfk-filter-foot {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 16px 18px;
    }

    .sfk-filter-foot-end {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
    }

    .sfk-filter-btn {
        min-width: 163px;
        max-width: 100%;
        height: 48px;
        padding: 0 16px;
        border: 1px solid var(--kt-border);
        border-radius: 0;
        background: var(--kt-normal);
        box-shadow: 1px 1px 1px #ffffff inset;
        color: var(--kt-blue);
        font-size: 1.125rem;
    }

    .sfk-filter-btn:hover {
        background: var(--kt-normal-hover);
    }

    .sfk-filter-btn-submit {
        background: var(--kt-blue);
        box-shadow: none;
        color: #ffffff;
    }

    .sfk-filter-btn-submit:hover {
        background: var(--kt-blue-dark);
    }

    .sfk-filter-btn:disabled {
        background: var(--kt-disabled);
        color: var(--kt-muted);
        cursor: not-allowed;
    }

    .sfk-filter-btn:focus-visible {
        outline: 1px solid var(--kt-blue);
        outline-offset: 1px;
    }

    .sfk-filter-name-backdrop {
        position: absolute;
        inset: 0;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        background: rgba(0, 0, 0, .4);
    }

    .sfk-filter-name {
        width: min(460px, 100%);
        background: #ffffff;
        box-shadow: 0 5px 10px rgba(0, 0, 0, .1);
    }

    .sfk-filter-name>.sfk-filter-title {
        padding: 16px 18px;
        border-bottom: 1px solid var(--kt-border);
    }

    .sfk-filter-name-body {
        padding: 22px 18px;
        background: var(--kt-normal);
        border-bottom: 1px solid var(--kt-border);
    }

    .sfk-filter-name-input {
        width: 100%;
    }

    .sfk-filter-error {
        margin: 0;
        padding: 4px 18px;
        background: var(--kt-red);
        color: #ffffff;
        font-size: 1rem;
        line-height: 1.5;
    }

    /* Calendar of a date condition: ‹ 2026年 ▾ 10月 ▾ ›, Sunday-first grid, 今日 / 選択を解除 */
    .sfk-filter-date {
        position: relative;
        display: inline-flex;
        gap: 0;
    }

    .sfk-filter-date .sfk-filter-input-time {
        margin-left: -1px;
    }

    .sfk-calendar {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        z-index: 3;
        width: 336px;
        padding: 24px 30px 14px;
        background: #ffffff;
        box-shadow: 0 5px 14px rgba(0, 0, 0, .18);
    }

    .sfk-calendar-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--kt-border);
    }

    .sfk-calendar-nav {
        display: inline-flex;
        padding: 4px;
        color: var(--kt-muted);
    }

    .sfk-calendar-nav:hover {
        color: var(--kt-blue);
    }

    .sfk-calendar-nav-icon {
        width: 16px;
        height: 16px;
    }

    .sfk-calendar-selects {
        display: inline-flex;
        gap: 14px;
    }

    .sfk-calendar-selects select {
        appearance: none;
        padding: 2px 18px 2px 2px;
        border: 0;
        background: transparent;
        font-size: 1rem;
        font-weight: 700;
        color: var(--kt-text);
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%23888' stroke-width='1.5'%3E%3Cpath d='M3 6l5 5 5-5'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0 center;
        background-size: 12px;
    }

    .sfk-calendar-grid {
        width: 100%;
        margin-top: 6px;
        border-collapse: collapse;
    }

    .sfk-calendar-grid th {
        padding: 8px 0;
        font-size: .8125rem;
        font-weight: 700;
        color: var(--kt-text);
        text-align: center;
    }

    .sfk-calendar-grid th.is-weekend {
        color: #bbbbbb;
    }

    .sfk-calendar-grid td {
        padding: 0;
        text-align: center;
    }

    .sfk-calendar-day {
        width: 34px;
        height: 33px;
        font-size: .9375rem;
        color: var(--kt-text);
    }

    .sfk-calendar-day:hover {
        background: var(--kt-blue-soft);
    }

    .sfk-calendar-day.is-outside {
        color: #cccccc;
    }

    .sfk-calendar-day.is-today {
        background: #888888;
        color: #ffffff;
        font-weight: 700;
    }

    .sfk-calendar-day.is-selected {
        background: var(--kt-blue);
        color: #ffffff;
        font-weight: 700;
    }

    .sfk-calendar-day:focus-visible {
        outline: 1px solid var(--kt-blue);
        outline-offset: -1px;
    }

    .sfk-calendar-foot {
        display: flex;
        justify-content: space-between;
        padding-top: 12px;
    }

    /* アプリの設定 (core::filament.app-list-views): tabs, 作成済みの一覧, the 一覧 screen */
    .sfk-settings-tabs {
        display: flex;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--kt-tab-border);
        width: 100%;
    }

    .sfk-settings-tab {
        display: inline-flex;
        align-items: center;
        min-height: 48px;
        padding: 0 24px;
        background: transparent;
        color: var(--kt-text);
        font-size: 1rem;
        border-top: 1px solid var(--kt-tab-border);
        border-left: 1px solid var(--kt-tab-border);
    }

    .sfk-settings-tab:last-child {
        border-right: 1px solid var(--kt-tab-border);
    }

    .sfk-settings-tab:hover {
        background: #eef0f0;
    }

    /* the selected tab is grey, the others transparent */
    .sfk-settings-tab.is-active {
        background: var(--kt-disabled);
    }

    .sfk-app-page-settings .fi-page-content {
        padding: 1rem .875rem 1.5rem;
        background: #ffffff;
    }

    .sfk-views {
        max-width: 960px;
    }

    .sfk-views-head {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .sfk-views-title {
        margin: 0;
        font-size: 1.3125rem;
        font-weight: 700;
    }

    .sfk-views-add {
        width: 2rem;
        height: 2rem;
    }

    .sfk-views-add .sfk-app-add-icon {
        width: 1.25rem;
        height: 1.25rem;
    }

    .sfk-views-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .sfk-views-row {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 48px;
        padding: 0 12px 0 4px;
        margin-top: -1px;
        background: #ffffff;
        border: 1px solid var(--kt-border);
        font-size: 1rem;
    }

    .sfk-views-row:hover {
        background: var(--kt-blue-soft);
    }

    .sfk-views-row.is-fixed {
        margin-top: 0;
        background: var(--kt-normal);
    }

    .sfk-views-row.fi-sortable-ghost {
        opacity: .4;
    }

    .sfk-views-handle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 36px;
        color: #bbbbbb;
        cursor: grab;
    }

    .sfk-views-handle:hover {
        color: var(--kt-blue);
    }

    .sfk-views-handle.is-placeholder {
        cursor: default;
    }

    .sfk-views-handle-icon,
    .sfk-views-type {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }

    .sfk-views-type {
        color: var(--kt-muted);
    }

    .sfk-views-name {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sfk-views-actions {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px 16px;
    }

    .sfk-views-action {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: var(--kt-blue);
        font-size: .9375rem;
    }

    .sfk-views-action:hover {
        text-decoration: underline;
    }

    .sfk-views-action-icon {
        width: 15px;
        height: 15px;
    }

    .sfk-views-actions .fi-link {
        font-size: .9375rem;
        font-weight: 400;
        color: var(--kt-red) !important;
    }

    .sfk-views-actions .fi-link .fi-icon {
        width: 15px;
        height: 15px;
        color: var(--kt-red) !important;
    }

    .sfk-view-editor {
        max-width: 1200px;
        border: 1px solid var(--kt-border);
    }

    .sfk-view-name {
        width: min(480px, 100%);
    }

    .sfk-view-types {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;
    }

    .sfk-view-type {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 48px;
        padding: 0 18px;
        border: 1px solid var(--kt-border);
        background: #ffffff;
        color: var(--kt-text);
        font-size: 1rem;
        cursor: pointer;
    }

    .sfk-view-type:hover {
        background: var(--kt-normal-hover);
    }

    .sfk-view-type.is-active {
        border-color: var(--kt-blue);
        box-shadow: inset 0 0 0 1px var(--kt-blue);
        color: var(--kt-blue);
        font-weight: 700;
    }

    .sfk-view-type:has(input:focus-visible) {
        outline: 1px solid var(--kt-blue);
        outline-offset: 2px;
    }

    .sfk-view-type-icon {
        width: 20px;
        height: 20px;
    }

    .sfk-view-layout {
        display: grid;
        grid-template-columns: minmax(180px, 240px) minmax(0, 1fr);
        gap: 16px;
        align-items: start;
    }

    @media (max-width: 720px) {
        .sfk-view-layout {
            grid-template-columns: 1fr;
        }
    }

    .sfk-view-palette-ctn,
    .sfk-view-columns-ctn {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 8px;
        background: #ffffff;
        border: 1px solid var(--kt-border);
    }

    .sfk-view-palette-ctn .sfk-filter-link,
    .sfk-view-columns-ctn .sfk-filter-link {
        align-self: flex-start;
    }

    .sfk-view-search {
        position: relative;
        display: block;
    }

    .sfk-view-search .sfk-filter-input {
        width: 100%;
        padding-left: 32px;
    }

    .sfk-view-search-icon {
        position: absolute;
        left: 9px;
        top: 12px;
        width: 16px;
        height: 16px;
        color: var(--kt-muted);
    }

    .sfk-view-palette {
        display: flex;
        flex-direction: column;
        gap: 4px;
        max-height: 360px;
        margin: 0;
        padding: 0;
        overflow-y: auto;
        list-style: none;
    }

    .sfk-view-field-btn {
        display: block;
        width: 100%;
        padding: 6px 10px;
        border: 1px solid var(--kt-border);
        background: var(--kt-normal);
        color: var(--kt-text);
        font-size: .9375rem;
        text-align: left;
        cursor: grab;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sfk-view-field-btn:hover {
        background: var(--kt-blue-soft);
        border-color: var(--kt-blue);
    }

    .sfk-view-columns {
        display: flex;
        flex-wrap: wrap;
        gap: 0;
        min-height: 44px;
        margin: 0;
        padding: 0;
        list-style: none;
        border: 1px dashed var(--kt-tab-border);
        overflow-x: auto;
    }

    .sfk-view-column {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 42px;
        padding: 0 6px 0 10px;
        background: var(--kt-header);
        border-right: 1px solid var(--kt-border);
        border-bottom: 1px solid var(--kt-border);
        color: var(--kt-label);
        font-size: .9375rem;
        font-weight: 700;
        cursor: grab;
        white-space: nowrap;
    }

    .sfk-view-columns.is-drop-target {
        border-color: var(--kt-blue);
        background: var(--kt-blue-soft);
    }

    .sfk-view-column.is-dragging {
        opacity: .4;
    }

    .sfk-view-column.is-drop-before {
        box-shadow: inset 3px 0 0 var(--kt-blue);
    }

    .sfk-view-columns.is-drop-end::after {
        content: "";
        align-self: stretch;
        width: 3px;
        background: var(--kt-blue);
    }

    .sfk-view-column-menu {
        position: relative;
        display: inline-flex;
    }

    .sfk-view-column-gear {
        display: inline-flex;
        padding: 3px;
        color: var(--kt-muted);
    }

    .sfk-view-column-gear:hover {
        color: var(--kt-blue);
    }

    .sfk-view-column-gear-icon {
        width: 15px;
        height: 15px;
    }

    .sfk-view-column-pop {
        position: absolute;
        top: 100%;
        right: 0;
        z-index: 5;
        min-width: 96px;
        padding: 6px 0;
        background: #ffffff;
        box-shadow: 0 5px 10px rgba(0, 0, 0, .1);
    }

    .sfk-view-column-pop-item {
        display: block;
        width: 100%;
        padding: 6px 16px;
        color: var(--kt-text);
        font-size: .9375rem;
        font-weight: 400;
        text-align: left;
    }

    .sfk-view-column-pop-item:hover {
        background: var(--kt-blue-soft);
    }

    .sfk-view-calendar-fields {
        display: flex;
        flex-wrap: wrap;
        gap: 16px 24px;
    }

    .sfk-view-calendar-field {
        display: flex;
        flex-direction: column;
    }

    .sfk-view-foot {
        border-top: 1px solid var(--kt-border);
        background: #ffffff;
        justify-content: flex-end;
    }

    .sfk-view-foot .sfk-filter-btn:first-child {
        margin-right: auto;
    }

    /* Settings screens (スペース, アプリの権限) in the app frame (core::filament.settings-header) */
    .sfk-settings-icon-svg {
        width: 1.25rem;
        height: 1.25rem;
    }

    .sfk-settings-page.sfk-app-page-list .fi-page-content {
        padding: 0;
    }

    /* Gear with ▾ (core::filament.app-settings-menu) */
    .sfk-app-settings {
        display: inline-flex;
    }

    .sfk-app-settings .sfk-app-tool:first-child {
        border-right: 0;
    }

    .sfk-app-settings-more {
        width: 1.5rem !important;
    }

    .sfk-app-settings-more-icon {
        width: .875rem;
        height: .875rem;
    }

    /* アプリの設定 → 設定 (core::filament.app-settings) */
    .sfk-settings {
        display: flex;
        flex-direction: column;
        gap: 24px;
        max-width: 960px;
    }

    .sfk-settings-group-title {
        margin: 0 0 10px;
        padding-left: 10px;
        border-left: 4px solid var(--kt-blue);
        font-size: 1.125rem;
        font-weight: 700;
    }

    .sfk-settings-link {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 48px;
        padding: 0 16px;
        border: 1px solid var(--kt-border);
        background: #ffffff;
        color: var(--kt-blue);
        font-size: 1rem;
    }

    .sfk-settings-link:hover {
        background: var(--kt-blue-soft);
    }

    .sfk-settings-link-icon {
        width: 18px;
        height: 18px;
    }

    .sfk-settings-form {
        display: flex;
        flex-direction: column;
        gap: 12px;
        border: 1px solid var(--kt-border);
    }

    .sfk-settings-form-title {
        font-weight: 700;
    }

    .sfk-settings-check {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 1rem;
        cursor: pointer;
    }

    .sfk-settings-form-foot {
        display: flex;
        justify-content: flex-end;
        padding-top: 8px;
    }

    /* ユーザー情報 popup (core::components.user-popup) */
    .sfk-user {
        display: inline-flex;
    }

    .sfk-user-line {
        display: block;
    }

    .sfk-user-name {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 0;
        color: var(--kt-blue-dark);
        font-size: inherit;
        text-align: left;
    }

    .sfk-user-name:hover {
        text-decoration: underline;
    }

    .sfk-user-name:focus-visible {
        outline: 1px solid var(--kt-blue);
        outline-offset: 1px;
    }

    .sfk-user-avatar {
        display: inline-grid;
        place-items: center;
        flex-shrink: 0;
        background: #8fb7d7;
        color: #ffffff;
        font-weight: 700;
    }

    .sfk-user-avatar-sm {
        width: 18px;
        height: 18px;
        font-size: 11px;
    }

    .sfk-user-avatar-lg {
        width: 56px;
        height: 56px;
        font-size: 25px;
    }

    .sfk-user-card {
        display: flex;
        gap: 14px;
        padding: 16px;
    }

    .sfk-user-card-body {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }

    .sfk-user-card-name {
        margin: 0 0 2px;
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--kt-text);
    }

    .sfk-user-card-meta {
        margin: 0;
        font-size: .9375rem;
        color: var(--kt-muted);
    }

    .sfk-user-card-link {
        margin-top: 8px;
        font-size: .9375rem;
        color: var(--kt-blue);
    }

    .sfk-user-card-link:hover {
        text-decoration: underline;
    }

    /* 変更履歴 (core::filament.record-history): panel on the right of the record; changed fields in green */
    .sfk-history {
        width: 340px;
        max-width: 100%;
        align-self: flex-start;
        background: #ffffff;
        border: 1px solid var(--kt-border);
    }

    @media (max-width: 1023px) {
        .sfk-history {
            width: 100%;
        }
    }

    .sfk-history-head {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 44px;
        padding: 0 12px 0 0;
        border-bottom: 1px solid var(--kt-border);
        background: var(--kt-header);
    }

    .sfk-history-tab {
        display: inline-grid;
        place-items: center;
        width: 44px;
        height: 44px;
        background: #ffffff;
        border-right: 1px solid var(--kt-border);
        color: var(--kt-blue);
    }

    .sfk-history-tab-icon {
        width: 20px;
        height: 20px;
    }

    .sfk-history-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
    }

    .sfk-history-shown {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 10px 12px;
        background: #e6f4ea;
        border-bottom: 1px solid #b7dfc2;
        font-size: .9375rem;
        font-weight: 700;
    }

    .sfk-history-latest {
        min-width: 0;
        height: 32px;
        padding: 0 12px;
        font-size: .9375rem;
    }

    .sfk-history-empty {
        margin: 0;
        padding: 16px 12px;
        font-size: .9375rem;
        color: var(--kt-muted);
    }

    .sfk-history-list {
        max-height: calc(100vh - 220px);
        margin: 0;
        padding: 0;
        overflow-y: auto;
        list-style: none;
    }

    .sfk-history-item {
        padding: 10px 12px;
        border-bottom: 1px solid var(--kt-border);
        font-size: .9375rem;
    }

    .sfk-history-item.is-shown {
        background: #f1f9f3;
        box-shadow: inset 3px 0 0 #3c9a5f;
    }

    .sfk-history-item-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 8px;
    }

    .sfk-history-version {
        font-weight: 700;
    }

    .sfk-history-date {
        color: var(--kt-muted);
        font-size: .875rem;
    }

    .sfk-history-who {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        margin: 4px 0 0;
    }

    .sfk-history-event {
        color: var(--kt-muted);
    }

    .sfk-history-fields {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin: 6px 0 0;
        padding: 0;
        list-style: none;
    }

    .sfk-history-fields li {
        padding: 1px 6px;
        background: #e6f4ea;
        color: #276b40;
        font-size: .875rem;
    }

    .sfk-history-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 14px;
        margin-top: 6px;
    }

    .sfk-history-actions .fi-link {
        font-size: .9375rem;
        font-weight: 400;
        color: var(--kt-blue) !important;
    }

    /* インライン編集 of a list row */
    .sfk-inline-field {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 120px;
    }

    .sfk-inline-input {
        width: 100%;
        min-height: 32px;
        padding: 4px 8px;
        border: 1px solid var(--kt-border);
        background: #ffffff;
        box-shadow: var(--kt-input-shadow);
        font-size: .9375rem;
        color: var(--kt-text);
    }

    .sfk-inline-input:focus {
        outline: none;
        border-color: var(--kt-blue);
    }

    .sfk-inline-number {
        min-width: 90px;
        text-align: right;
    }

    .sfk-inline-users {
        min-width: 160px;
    }

    .sfk-inline-checks {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 10px;
    }

    .sfk-inline-check {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: .9375rem;
        white-space: nowrap;
        cursor: pointer;
    }

    .sfk-inline-check input {
        width: 15px;
        height: 15px;
        accent-color: var(--kt-blue);
    }

    .sfk-inline-error {
        margin: 0;
        color: var(--kt-red);
        font-size: .875rem;
    }

    /* Record panel collapsed: a narrow bar with the tabs' icons */
    .sfk-sidebar.is-collapsed {
        width: 46px;
    }

    .sfk-sidebar-rail {
        display: flex;
        flex-direction: column;
    }

    .sfk-sidebar-rail-btn {
        position: relative;
        display: grid;
        place-items: center;
        width: 44px;
        height: 44px;
        border-bottom: 1px solid var(--kt-border);
        color: var(--kt-muted);
    }

    .sfk-sidebar-rail-btn:hover {
        background: var(--kt-blue-soft);
        color: var(--kt-blue);
    }

    .sfk-sidebar-rail-btn:focus-visible,
    .sfk-sidebar-close:focus-visible {
        outline: 1px solid var(--kt-blue);
        outline-offset: -2px;
    }

    .sfk-sidebar-rail-count {
        position: absolute;
        top: 4px;
        right: 3px;
        min-width: 16px;
        height: 16px;
        padding: 0 4px;
        background: var(--kt-blue);
        color: #ffffff;
        font-size: 11px;
        line-height: 16px;
        text-align: center;
    }

    .sfk-sidebar-close {
        display: grid;
        place-items: center;
        width: 44px;
        height: 44px;
        margin-left: auto;
        color: var(--kt-muted);
    }

    .sfk-sidebar-close:hover {
        color: var(--kt-blue);
    }

    @media (max-width: 1023px) {
        .sfk-sidebar.is-collapsed {
            width: 100%;
        }

        .sfk-sidebar-rail {
            flex-direction: row;
        }

        .sfk-sidebar-rail-btn {
            border-bottom: 0;
            border-right: 1px solid var(--kt-border);
        }
    }

    /* Record panel tabs: コメント | 変更履歴 */
    .sfk-sidebar-tabs {
        gap: 0;
        padding: 0;
    }

    .sfk-sidebar-tab {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 44px;
        padding: 0 14px;
        border-right: 1px solid var(--kt-border);
        color: var(--kt-muted);
    }

    .sfk-sidebar-tab:hover {
        color: var(--kt-blue);
    }

    .sfk-sidebar-tab.is-active {
        background: #ffffff;
        color: var(--kt-blue);
        box-shadow: inset 0 -2px 0 var(--kt-blue);
    }

    .sfk-sidebar-tab.is-active .sfk-history-title {
        color: var(--kt-text);
    }

    .sfk-sidebar-tab:focus-visible {
        outline: 1px solid var(--kt-blue);
        outline-offset: -2px;
    }

    /* コメント */
    .sfk-comment-form {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 12px;
        border-bottom: 1px solid var(--kt-border);
        background: #fafbfc;
    }

    .sfk-comment-form .fi-sc {
        gap: 10px;
    }

    .sfk-comment-form-foot {
        display: flex;
        justify-content: flex-end;
    }

    .sfk-comment-submit {
        min-width: 96px;
        height: 36px;
        font-size: 1rem;
    }

    .sfk-comment-who {
        margin: 0;
    }

    .sfk-comment-mentions {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin: 6px 0 0;
    }

    .sfk-comment-mention {
        padding: 1px 6px;
        background: var(--kt-blue-soft);
        color: var(--kt-blue-dark);
        font-size: .875rem;
    }

    .sfk-comment-body {
        margin: 6px 0 0;
        white-space: normal;
        overflow-wrap: anywhere;
        line-height: 1.6;
    }

    .sfk-history-actions .fi-link.fi-color-danger {
        color: var(--kt-muted) !important;
    }

    .sfk-history-actions .fi-link.fi-color-danger:hover {
        color: var(--kt-red) !important;
    }

    .fi-in-entry.sfk-history-changed .fi-in-entry-content {
        background: #dff3e4 !important;
        box-shadow: inset 3px 0 0 #3c9a5f;
    }

    /* 印刷する: print view (?print=1) without header, menus and history; 印刷 / 閉じる hidden on paper */
    body:has(.sfk-app-page-print) .fi-topbar,
    body:has(.sfk-app-page-print) .fi-sidebar,
    body:has(.sfk-app-page-print) .fi-sidebar-close-overlay {
        display: none !important;
    }

    .sfk-app-page-print .sfk-app-crumbs,
    .sfk-app-page-print .sfk-app-description {
        display: none;
    }

    /* the whole record is printed: every tab's fields, without the tab bar */
    .sfk-app-page-print .kt-record-tabs {
        display: none;
    }

    .sfk-app-page-print .kt-record-tabbed [x-show] {
        display: block !important;
    }

    .sfk-print-toolbar {
        gap: 12px;
    }

    @media print {

        .sfk-print-toolbar,
        .sfk-app-page-print .sfk-app-toolbar {
            display: none !important;
        }

        .sfk-app-page-print .sfk-app-titlebar {
            background: #ffffff !important;
            color: var(--kt-text) !important;
            border-bottom: 2px solid var(--kt-text);
        }

        .sfk-app-page-print .sfk-app-title a {
            color: var(--kt-text) !important;
        }

        .sfk-app-page-print .fi-page-content {
            background: #ffffff !important;
            border: 0 !important;
            padding: 0 !important;
        }

        .fi-body {
            background: #ffffff !important;
        }
    }

    /* カレンダー形式 of an app list (core::filament.app-calendar) */
    .sfk-calendar-view {
        padding: 0 .875rem 1rem;
    }

    .sfk-calendar-view-head {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 0 12px;
    }

    .sfk-calendar-view-month {
        margin: 0 4px;
        font-size: 1.3125rem;
        font-weight: 700;
    }

    .sfk-calendar-view-today {
        min-width: 0;
        height: 32px;
        padding: 0 14px;
        font-size: .9375rem;
    }

    .sfk-calendar-view-grid {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .sfk-calendar-view-grid th {
        padding: 6px 0;
        background: var(--kt-header);
        border: 1px solid var(--kt-border);
        color: var(--kt-label);
        font-size: .875rem;
        font-weight: 700;
    }

    .sfk-calendar-view-grid th.is-sunday {
        color: var(--kt-red);
    }

    .sfk-calendar-view-grid th.is-saturday {
        color: var(--kt-blue);
    }

    .sfk-calendar-view-grid td {
        height: 112px;
        padding: 4px 6px;
        border: 1px solid var(--kt-border);
        vertical-align: top;
        background: #ffffff;
    }

    .sfk-calendar-view-grid td.is-outside {
        background: var(--kt-normal);
        color: #bbbbbb;
    }

    .sfk-calendar-view-grid td.is-today {
        background: #fffbe5;
    }

    .sfk-calendar-view-day {
        display: block;
        font-size: .9375rem;
    }

    .sfk-calendar-view-grid td.is-today .sfk-calendar-view-day {
        font-weight: 700;
        color: var(--kt-blue-dark);
    }

    .sfk-calendar-view-records {
        margin: 2px 0 0;
        padding: 0;
        list-style: none;
        font-size: .875rem;
        line-height: 1.5;
    }

    .sfk-calendar-view-records li {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sfk-calendar-view-records a {
        color: var(--kt-blue-dark);
    }

    .sfk-calendar-view-records a:hover {
        text-decoration: underline;
    }

    /* Create / edit toolbar: キャンセル (normal) and 保存 (submit) in the vendor's large button size */
    :is(.sfk-app-page-create, .sfk-app-page-edit) .sfk-app-toolbar .fi-btn {
        min-width: 163px;
        max-width: 100%;
        min-height: 48px;
        padding-inline: 16px;
        font-size: 1.125rem;
    }

    /* App list: the paging is in the toolbar; light-blue striped rows with vertical rules */
    .sfk-app-page-list .fi-ta-ctn .fi-pagination {
        display: none;
    }

    /* Search box on the left of the list toolbar */
    .sfk-app-page-list .fi-ta-header-toolbar {
        justify-content: flex-start;
    }

    .sfk-app-page-list .fi-ta-header-toolbar> :not(.fi-ta-actions) {
        order: -1;
        margin-inline-start: 0;
    }

    .sfk-app-page-list .fi-ta-header-toolbar>.fi-ta-actions:empty {
        display: none;
    }

    /* the paging at the right end of the search row */
    .sfk-app-page-list .fi-ta-header-toolbar>.sfk-app-pager {
        order: 1;
        margin-inline-start: auto;
    }

    /* One horizontal scroll for header and rows, with its bar at the end of the table (also below the empty
       state); the toolbar and the empty state stay in view while scrolling */
    .sfk-app-page-list .fi-ta-main {
        overflow-x: auto;
    }

    .sfk-app-page-list .fi-ta-content-ctn {
        overflow-x: visible;
    }

    .sfk-app-page-list .fi-ta-header-ctn,
    .sfk-app-page-list .fi-ta-empty-state {
        position: sticky;
        left: 0;
    }

    /* …and the same scroll at the bottom of the screen while the end of the table is out of view */
    .sfk-list-scroll:not(.is-visible) {
        display: none;
    }

    .sfk-list-scroll {
        position: fixed;
        bottom: 0;
        z-index: 30;
        background: #ffffff;
        box-shadow: 0 -1px 0 var(--kt-border), 0 -2px 6px rgba(0, 0, 0, .06);
    }

    .sfk-list-scroll-bar {
        overflow-x: auto;
        overflow-y: hidden;
    }

    .sfk-list-scroll-spacer {
        height: 1px;
    }

    .sfk-app-page-list .fi-ta-ctn {
        box-shadow: 0 1px 0 var(--kt-border), 0 -1px 0 var(--kt-border) !important;
    }

    .sfk-app-page-list .fi-ta-row.fi-striped>td {
        background-color: #f2f8fc;
    }

    .sfk-app-page-list .fi-ta-row:hover>td {
        background-color: var(--kt-blue-soft);
    }

    .sfk-app-page-list .fi-ta-table>tbody>tr>td {
        border-inline-end: 1px solid var(--kt-border);
    }

    /* App record form / record page (and the settings forms): light-blue ground, white cards with room around the
       fields, even gaps between them, tabs above the fields */
    :is(.sfk-app-page-create, .sfk-app-page-edit, .sfk-app-page-view) .fi-page-content {
        padding: 24px 28px 40px;
        background: #eef4f9;
        border-top: 1px solid #d3e3ef;
    }

    @media (max-width: 640px) {
        :is(.sfk-app-page-create, .sfk-app-page-edit, .sfk-app-page-view) .fi-page-content {
            padding: 12px;
        }
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit, .sfk-app-page-view) .fi-page-content .fi-section:not(.fi-section-not-contained) {
        background: #ffffff;
        box-shadow: 0 0 0 1px #d8e4ee, 0 1px 3px rgba(20, 70, 110, .06) !important;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit, .sfk-app-page-view) .fi-page-content .fi-section-header {
        padding: 12px 20px;
        background: #f6f9fc;
        border-bottom: 1px solid #e3ebf2;
    }

    /*
     * Spacing of the record cards (create / edit / record page), one rhythm for every app: 24px around a card's content
     * and between cards, 20px between the fields of a row, 18px between rows, 28px before each group (blue bar) with
     * 16px under its rule; a field's label sits 6px above its input.
     */
    :is(.sfk-app-page-create, .sfk-app-page-edit, .sfk-app-page-view) .fi-page-content .fi-section:not(.fi-section-not-contained)>.fi-section-content-ctn>.fi-section-content {
        padding: 24px 28px 28px;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit, .sfk-app-page-view) .fi-page-content :is(.fi-sc, .kt-record-tabbed):has(> .fi-grid-col > .fi-sc-component > .kt-record, > .fi-grid-col > .kt-record) {
        row-gap: 24px;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit, .sfk-app-page-view) .kt-record .fi-grid {
        column-gap: 20px;
        row-gap: 18px;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit, .sfk-app-page-view) .kt-record .fi-fo-field {
        row-gap: 6px;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit) .fi-fo-field-label {
        margin-bottom: 0;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit) .fi-fo-field-label-content {
        font-size: .9375rem;
        color: var(--kt-label);
    }

    /* drop-downs keep their value on one line, also in narrow legacy columns */
    .fi-select-input-btn {
        min-width: 0;
    }

    .fi-select-input-value-ctn,
    .fi-select-input-placeholder,
    .fi-select-input-value-label {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sfk-app-page-view .kt-record .fi-in-entry-content {
        min-height: 2.5rem;
        padding: 8px 12px;
        border: 1px solid #e6edf3;
        background: #f8fbfd;
    }

    .sfk-app-page-view .kt-record .fi-in-entry {
        row-gap: 6px;
    }

    .kt-record-tabbed {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .kt-record-tabs {
        display: flex;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--kt-tab-border);
    }

    .kt-record-tab {
        min-height: 48px;
        padding: 0 24px;
        background: var(--kt-disabled);
        color: var(--kt-text);
        font-size: 1rem;
        border-top: 1px solid var(--kt-tab-border);
        border-left: 1px solid var(--kt-tab-border);
        box-shadow: 1px 0 3px var(--kt-tab-border) inset;
    }

    .kt-record-tab:last-child {
        border-right: 1px solid var(--kt-tab-border);
    }

    .kt-record-tab:hover {
        background: #e1e3e3;
    }

    .kt-record-tab.kt-active {
        background: #ffffff;
        box-shadow: none;
    }

    .kt-record-tab:focus-visible {
        outline: 1px solid var(--kt-blue);
        outline-offset: -1px;
    }

    /* group label (RecordSections string rows): a blue mark, the title and a hairline across the card; 28px above it
       (row gap + margin), none for the first group of a card */
    .kt-record-label {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        margin-top: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e3ebf2;
        color: var(--kt-label);
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: .02em;
    }

    .kt-record-label::before {
        content: "";
        flex: none;
        width: 4px;
        height: 1.125rem;
        background: var(--kt-blue);
    }

    .kt-record .fi-sc>.fi-grid-col:first-child .kt-record-label {
        margin-top: 0;
    }

    /* labels straight after one another (e.g. the two notes of 17) read as one block */
    .kt-record .fi-sc>.fi-grid-col:has(.kt-record-label)+.fi-grid-col .kt-record-label {
        margin-top: -10px;
    }

    /* file fields: the drop text 「ファイルをドラッグ&ドロップ又はファイル選択」 small, the field stays as it is */
    .filepond--drop-label label {
        font-size: .75rem;
    }

    /* Dashboard name (core::filament.dashboard-switcher, a link to the ポータル) replaces the panel brand */
    .fi-sidebar-header-logo-ctn,
    .fi-topbar-start>a:has(> .fi-logo) {
        display: none !important;
    }

    .sfk-dashboard-switcher {
        display: flex;
        align-items: center;
        min-width: 0;
    }

    .sfk-dashboard-switcher-btn {
        display: inline-flex;
        align-items: center;
        gap: .375rem;
        max-width: 100%;
        padding: .25rem .5rem;
        font-size: 1.3125rem;
        font-weight: 700;
        color: var(--kt-text);
    }

    .sfk-dashboard-switcher-btn:hover {
        background: transparent;
        color: var(--kt-blue);
    }

    .sfk-dashboard-switcher-btn:focus-visible {
        outline: 2px solid var(--kt-blue);
        outline-offset: 2px;
    }

    .sfk-dashboard-switcher-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sfk-dashboard-switcher-chevron {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
        color: var(--kt-muted);
    }
</style>

{{-- SFK UI: the component layer (check box, radio, switch, tooltip, attachment, read-only tables …) --}}
@include('core::filament.ui')

<script>
    // Date / time inputs: a click anywhere in the field opens the browser's picker of its type (not only the icon).
    // One listener on the document, so fields added or redrawn by Livewire work too.
    document.addEventListener('click', (event) => {
        const input = event.target instanceof HTMLInputElement ? event.target : null;
        if (!input || !['date', 'time', 'datetime-local', 'month', 'week'].includes(input.type) || input
            .disabled || input.readOnly) {
            return;
        }

        try {
            input.showPicker();
        } catch (error) {
            // not supported or not allowed (e.g. inside a cross-origin frame): the icon still opens it
        }
    });
</script>
