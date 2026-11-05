{{--
    The look of the app screens since 2026-11 (design_ref/app-index.png, add-invoice.png, customer-*.png,
    profile-page.png, welcome-card.png), layered over core::filament.theme in the cached panel stylesheet
    (Modules\Core\Filament\Support\ThemeAssets): white surfaces with hairline borders and small rounded corners, the
    brand blue (BasePanelProvider::BRAND_BLUE) as the primary colour, dark text, soft grey headers.
--}}
<style>
    /* ---- tokens ------------------------------------------------------------------------------------------------
       The design system of every screen (ui-refine.md, 2026-11). Style new parts with these tokens only — no
       hard-coded colours, sizes or spacings. The portal / login shell (core::components.layouts.account) repeats the
       colour tokens it uses. */
    :root {
        /* type: Noto Sans JP (served by this system, public/fonts/noto-sans-jp), then the system Japanese fonts */
        --sfk-font: "Noto Sans JP", "Hiragino Sans", "Hiragino Kaku Gothic ProN", "Yu Gothic UI", Meiryo, system-ui, sans-serif;
        /* the type of index-ref.png (bug.md, 2026-11-04), never above 22px: 14px text, 18px titles */
        --sfk-fs-title: 1.125rem;
        /* 18px  page title (app name, プロフィール …), dialog title */
        --sfk-fs-heading: 1rem;
        /* 16px  section / group heading */
        --sfk-fs-subheading: .9375rem;
        /* 15px  card titles, small headings */
        --sfk-fs-body: .875rem;
        /* 14px  everyday text, table cells, input values */
        --sfk-fs-label: .875rem;
        /* 14px  field labels, table headers, buttons, tabs, navigation */
        --sfk-fs-small: .8125rem;
        /* 13px  notes, metadata, breadcrumb, sidebar group names */
        --sfk-fs-badge: .75rem;
        /* 12px  badges, counters */
        --sfk-fs-figure: 1.125rem;
        /* 18px  key figures of the summary cards */
        --sfk-fw-regular: 400;
        --sfk-fw-medium: 500;
        --sfk-fw-semibold: 600;
        --sfk-fw-bold: 700;
        --sfk-lh-tight: 1.35;
        --sfk-lh-body: 1.6;
        --sfk-tracking: .01em;
        --sfk-tracking-heading: .02em;
        /* colour: neutral grounds, white surfaces, hairlines, one brand blue, meaning-only status colours */
        --sfk-primary: #05467E;
        --sfk-primary-dark: #033456;
        --sfk-primary-soft: #eaf1f8;
        --sfk-primary-soft-strong: #d4e2ef;
        --sfk-ink: #101828;
        --sfk-text: #1d2939;
        --sfk-muted: #5d6879;
        --sfk-subtle: #98a2b3;
        --sfk-disabled-text: #c4cad4;
        --sfk-line: #e2e5e9;
        --sfk-line-soft: #eff1f4;
        --sfk-line-strong: #d0d5dd;
        --sfk-surface: #ffffff;
        --sfk-page: #f5f7fa;
        --sfk-head: #f8f9fb;
        /* the header row of the tables: a little darker than the other grey grounds (bug.md, 2026-11-04) */
        --sfk-table-head: #eef1f5;
        /* the header row of the app lists (index pages): dark with white text, like the tables of a record
           (user, 2026-11-05) */
        --sfk-list-head: var(--sfk-table-head-dark);
        --sfk-hover: #f6f8fa;
        --sfk-hover-strong: #eef1f5;
        --sfk-field-disabled: #f2f4f7;
        --sfk-success: #12805c;
        --sfk-success-soft: #e8f6ef;
        --sfk-warning: #b25e09;
        --sfk-warning-soft: #fdf3e3;
        --sfk-danger: #e74c3c;
        --sfk-danger-text: #c0322a;
        --sfk-danger-soft: #fdeceb;
        --sfk-info: var(--sfk-primary);
        --sfk-info-soft: var(--sfk-primary-soft);
        --sfk-table-head-dark: #1f2937;
        /* shape and depth */
        --sfk-radius: 6px;
        --sfk-radius-lg: 10px;
        --sfk-radius-xl: 12px;
        --radius-sm: 4px;
        --radius-md: 6px;
        --radius-lg: 8px;
        --radius-xl: 10px;
        --radius-2xl: 12px;
        --sfk-shadow: 0 1px 2px rgba(16, 24, 40, .05);
        --sfk-shadow-md: 0 2px 6px rgba(16, 24, 40, .06);
        --sfk-shadow-lg: 0 10px 28px rgba(16, 24, 40, .12);
        --sfk-ring: 0 0 0 1px var(--sfk-line);
        --sfk-focus: 0 0 0 3px rgba(5, 70, 126, .18);
        /* spacing: a 4px scale; the page gutter narrows on small screens */
        --sfk-space-1: 4px;
        --sfk-space-2: 8px;
        --sfk-space-3: 12px;
        --sfk-space-4: 16px;
        --sfk-space-5: 20px;
        --sfk-space-6: 24px;
        --sfk-space-8: 32px;
        --sfk-space-10: 40px;
        --sfk-gutter: 24px;
        /* control heights: one size for every button, input and toolbar control */
        --sfk-control: 36px;
        --sfk-control-sm: 30px;
        /* the legacy tokens of core::filament.theme take the new look */
        --font-family: var(--sfk-font) !important;
        --kt-border: var(--sfk-line);
        --kt-header: var(--sfk-head);
        --kt-page: var(--sfk-page);
        --kt-text: var(--sfk-text);
        --kt-muted: var(--sfk-muted);
        --kt-normal: #ffffff;
        --kt-normal-hover: var(--sfk-hover-strong);
        --kt-input-shadow: 0 0 0 0 transparent;
        --kt-disabled: var(--sfk-field-disabled);
        --kt-blue-soft: var(--sfk-primary-soft);
        --kt-label: var(--sfk-ink);
    }

    @media (max-width: 767px) {
        :root {
            --sfk-gutter: 16px;
            --sfk-fs-title: 1.0625rem;
            --sfk-fs-figure: 1.0625rem;
        }
    }

    /* ---- base typography ---------------------------------------------------------------------------------------- */
    html {
        -webkit-text-size-adjust: 100%;
        text-size-adjust: 100%;
    }

    body.fi-body {
        background-color: var(--sfk-page);
        color: var(--sfk-text);
        font-family: var(--sfk-font);
        line-height: var(--sfk-lh-body);
        letter-spacing: var(--sfk-tracking);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        text-rendering: optimizeLegibility;
    }

    /* form controls do not inherit the font by default */
    body.fi-body :is(button, input, select, textarea, optgroup) {
        font-family: inherit;
        letter-spacing: inherit;
    }

    /* headings: proportional kana (palt) for a tighter, more even line; never heavier than bold */
    :is(.sfk-app-title, .fi-header-heading, .fi-section-header-heading, .fi-modal-heading, .kt-record-label,
        .sfk-profile-title, .sfk-profile-nav-title, .sfk-related-title, .fi-sidebar-header, .fi-wi-stats-overview-stat-value) {
        font-feature-settings: "palt";
        letter-spacing: var(--sfk-tracking-heading);
        line-height: var(--sfk-lh-tight);
    }

    /* figures line up in columns: tabular numerals wherever amounts, quantities, dates and codes are read */
    :is(.fi-ta-table, .kt-subtable, .sfk-related-table, .sfk-data-table, .sfk-record-tab-table, .fi-input,
        .fi-in-entry-content, .sfk-stat-value, .sfk-profile-stat-value, .sfk-record-tab-stat-value, .sfk-page-btn,
        .sfk-list-count, .fi-badge, .fi-wi-stats-overview-stat-value) {
        font-variant-numeric: tabular-nums;
    }

    ::placeholder {
        color: var(--sfk-subtle);
        opacity: 1;
    }

    ::selection {
        background: var(--sfk-primary-soft-strong);
        color: var(--sfk-ink);
    }

    /* ---- loading (core::filament.app-filter-script, ../loading.mp4): three dots rising and falling in a wave over
       the screen, which stays visible behind a transparent blur; no text ------------------------------------------ */
    .sfk-loading {
        position: fixed;
        inset: 0;
        z-index: 100;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 14px;
        background: rgba(255, 255, 255, .25);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        opacity: 0;
        visibility: hidden;
        transition: opacity .2s, visibility .2s;
    }

    .sfk-loading.is-active {
        opacity: 1;
        visibility: visible;
    }

    .sfk-loading span {
        width: 16px;
        height: 16px;
        border-radius: 9999px;
        background: var(--sfk-primary);
        box-shadow: 0 2px 6px rgba(5, 70, 126, .3);
        animation: sfk-loading-wave 1.2s ease-in-out infinite;
    }

    .sfk-loading span:nth-child(2) {
        animation-delay: .15s;
    }

    .sfk-loading span:nth-child(3) {
        animation-delay: .3s;
    }

    @keyframes sfk-loading-wave {

        0%,
        60%,
        100% {
            transform: translateY(0);
        }

        30% {
            transform: translateY(-22px);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .sfk-loading span {
            animation-duration: 2.4s;
        }
    }

    /* ---- an app list while its data loads (core::filament.app-filter-script): placeholder rows in the table, grey
       bars with a light sweeping over them — under the header row while it updates, in place of Filament's spinner
       on the first load ------------------------------------------------------------------------------------------ */
    :root {
        --sfk-skeleton-bars: linear-gradient(90deg,
                transparent 0 16px, #e2e7ee 16px 18%, transparent 18% 22%, #e2e7ee 22% 40%, transparent 40% 44%,
                #e2e7ee 44% 64%, transparent 64% 68%, #e2e7ee 68% 82%, transparent 82% 86%, #e2e7ee 86% calc(100% - 16px),
                transparent calc(100% - 16px));
        --sfk-skeleton-shine: linear-gradient(100deg, transparent 30%, rgba(255, 255, 255, .75) 50%, transparent 70%);
        --sfk-skeleton-rows: repeating-linear-gradient(180deg, transparent 0 17px, #000 17px 31px, transparent 31px 48px);
    }

    .sfk-app-page-list .fi-ta-content-ctn.sfk-table-loading {
        position: relative;
    }

    /* a list without rows (e.g. switching from an empty 一覧 to one with data): the table is only its header row, so it
       makes room for the placeholder rows, and the empty message under it waits until the data is there */
    .sfk-app-page-list .fi-ta-main:has(> .fi-ta-empty-state)>.fi-ta-content-ctn.sfk-table-loading {
        flex: 1;
        min-height: 480px;
    }

    .sfk-app-page-list .fi-ta-main:has(.fi-ta-content-ctn.sfk-table-loading) .fi-ta-empty-state {
        display: none;
    }

    .sfk-app-page-list .fi-ta-content-ctn.sfk-table-loading>table>tbody {
        visibility: hidden;
    }

    .sfk-app-page-list .fi-ta-content-ctn.sfk-table-loading::after,
    .sfk-app-page-list .fi-ta-table-loading-ctn::after {
        content: "";
        position: absolute;
        inset: var(--sfk-thead, 0px) 0 0;
        z-index: 2;
        min-height: 240px;
        background-image: var(--sfk-skeleton-shine), var(--sfk-skeleton-bars);
        background-size: 200% 100%, 100% 100%;
        background-repeat: no-repeat;
        -webkit-mask-image: var(--sfk-skeleton-rows);
        mask-image: var(--sfk-skeleton-rows);
        animation: sfk-skeleton 1.4s ease-in-out infinite;
        pointer-events: none;
    }

    /* the first load: a header strip, then the rows */
    .sfk-app-page-list .fi-ta-table-loading-ctn {
        position: relative;
        display: block;
        min-height: 480px;
        padding: 0;
        --sfk-thead: 40px;
    }

    .sfk-app-page-list .fi-ta-table-loading-ctn> :not(.fi-sr-only) {
        display: none;
    }

    .sfk-app-page-list .fi-ta-table-loading-ctn::before {
        content: "";
        position: absolute;
        inset: 0 0 auto;
        height: 40px;
        background: var(--sfk-list-head);
        border-bottom: 1px solid var(--sfk-line);
    }

    @keyframes sfk-skeleton {
        from {
            background-position: 150% 0, 0 0;
        }

        to {
            background-position: -50% 0, 0 0;
        }
    }

    @media (prefers-reduced-motion: reduce) {

        .sfk-app-page-list .fi-ta-content-ctn.sfk-table-loading::after,
        .sfk-app-page-list .fi-ta-table-loading-ctn::after {
            animation: none;
        }
    }

    /* ---- no top bar: the floating button that opens the sidebar (Filament's toggle while the sidebar is closed) --- */
    .fi-layout-sidebar-toggle-btn-ctn {
        position: fixed;
        left: 16px;
        bottom: 20px;
        z-index: 35;
        padding: 0;
        background: transparent;
        transition: bottom .2s ease;
    }

    /* a form keeps キャンセル / 保存 in a bar at the bottom of the screen: the button sits above that bar */
    body:has(:is(.sfk-app-page-create, .sfk-app-page-edit) .fi-sc-form .fi-sc-actions > .fi-ac) .fi-layout-sidebar-toggle-btn-ctn {
        bottom: 88px;
    }

    .fi-layout-sidebar-toggle-btn-ctn .fi-icon-btn {
        width: 44px;
        height: 44px;
        border-radius: 9999px;
        background: var(--sfk-primary);
        box-shadow: 0 6px 16px -4px rgba(5, 70, 126, .55), 0 0 0 3px rgba(255, 255, 255, .9);
        color: #ffffff;
    }

    .fi-layout-sidebar-toggle-btn-ctn .fi-icon-btn:hover {
        background: var(--sfk-primary-dark);
        color: #ffffff;
    }

    .fi-layout-sidebar-toggle-btn-ctn .fi-icon-btn .fi-icon {
        color: #ffffff;
    }

    /* ---- buttons: one height, medium weight; primary filled, secondary white with a hairline ----------------------- */
    .fi-btn {
        gap: 6px;
        border-radius: var(--sfk-radius) !important;
        font-size: var(--sfk-fs-label);
        font-weight: var(--sfk-fw-medium);
        letter-spacing: var(--sfk-tracking);
        transition: background-color .15s, box-shadow .15s, color .15s;
    }

    .fi-btn:not(.fi-size-xs):not(.fi-size-sm):not(.fi-ac-link-action) {
        min-height: var(--sfk-control);
    }

    .fi-btn:not(.fi-outlined):not([class*="fi-bg-color-"]),
    .fi-btn.fi-color-gray:not(.fi-outlined) {
        background-color: var(--sfk-surface) !important;
        color: var(--sfk-text) !important;
        box-shadow: 0 0 0 1px var(--sfk-line-strong), var(--sfk-shadow) !important;
        --text: var(--sfk-text);
        --hover-text: var(--sfk-ink);
    }

    .fi-btn:not(.fi-outlined):not([class*="fi-bg-color-"]):hover,
    .fi-btn.fi-color-gray:not(.fi-outlined):hover {
        background-color: var(--sfk-hover) !important;
        color: var(--sfk-ink) !important;
    }

    .fi-btn:not(.fi-outlined):not([class*="fi-bg-color-"]) .fi-icon,
    .fi-btn.fi-color-gray:not(.fi-outlined) .fi-icon {
        color: var(--sfk-muted) !important;
    }

    .fi-btn.fi-color-primary[class*="fi-bg-color-"],
    .fi-btn.fi-color-danger[class*="fi-bg-color-"] {
        box-shadow: var(--sfk-shadow) !important;
        font-weight: var(--sfk-fw-semibold);
    }

    .fi-btn.fi-disabled,
    .fi-btn[disabled] {
        background-color: var(--sfk-field-disabled) !important;
        box-shadow: 0 0 0 1px var(--sfk-line) !important;
        color: var(--sfk-subtle) !important;
    }

    .fi-icon-btn {
        border-radius: var(--sfk-radius);
        transition: background-color .15s, color .15s;
    }

    .fi-btn:focus-visible,
    .fi-icon-btn:focus-visible {
        outline: 2px solid var(--sfk-primary);
        outline-offset: 2px;
    }

    /* links styled as actions */
    .fi-link {
        font-weight: var(--sfk-fw-medium);
    }

    /* ---- inputs: one height, a hairline that darkens on hover, a soft blue ring on focus ------------------------- */
    .fi-input-wrp {
        border-radius: var(--sfk-radius) !important;
        box-shadow: 0 0 0 1px var(--sfk-line-strong) !important;
        transition: box-shadow .15s;
    }

    .fi-input-wrp:not(.fi-disabled):not(.fi-invalid):hover {
        box-shadow: 0 0 0 1px #b9c0cb !important;
    }

    .fi-input-wrp:not(.fi-disabled):focus-within,
    .fi-input-wrp:not(.fi-disabled):focus-within:hover {
        box-shadow: 0 0 0 1px var(--sfk-primary), var(--sfk-focus) !important;
    }

    .fi-input-wrp.fi-invalid {
        box-shadow: 0 0 0 1px var(--sfk-danger), 0 0 0 3px var(--sfk-danger-soft) !important;
    }

    .fi-input-wrp.fi-disabled {
        background-color: var(--sfk-field-disabled);
        box-shadow: 0 0 0 1px var(--sfk-line) !important;
    }

    .fi-input {
        min-height: var(--sfk-control);
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-body);
    }

    .fi-input-wrp-prefix,
    .fi-input-wrp-suffix {
        color: var(--sfk-muted);
    }

    .fi-checkbox-input {
        border-radius: 4px;
    }

    .fi-radio-input,
    input[type="radio"] {
        border-radius: 9999px;
    }

    /* ---- drop-downs and action menus ------------------------------------------------------------------------------ */
    .fi-dropdown-panel {
        border-radius: var(--sfk-radius-lg);
        box-shadow: var(--sfk-ring), var(--sfk-shadow-lg) !important;
        overflow: hidden;
    }

    .fi-dropdown-list {
        padding: var(--sfk-space-1);
    }

    .fi-dropdown-list-item {
        min-height: 38px;
        padding: 8px 10px;
        gap: 10px;
        border-radius: var(--sfk-radius);
        font-size: var(--sfk-fs-label);
    }

    .fi-dropdown-list-item:hover,
    .fi-dropdown-list-item:focus-visible {
        background-color: var(--sfk-hover-strong) !important;
    }

    .fi-dropdown-list-item .fi-icon {
        color: var(--sfk-muted);
    }

    .fi-dropdown-list-item.fi-color-danger,
    .fi-dropdown-list-item.fi-color-danger .fi-icon {
        color: var(--sfk-danger-text) !important;
    }

    .fi-dropdown-list-item.fi-color-danger:hover {
        background-color: var(--sfk-danger-soft) !important;
    }

    .fi-dropdown-header {
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-small);
        font-weight: var(--sfk-fw-semibold);
    }

    /* ---- dialogs: white throughout, a clear heading, the buttons in a soft footer ---------------------------------- */
    .fi-modal-window {
        border-radius: var(--sfk-radius-xl);
        box-shadow: var(--sfk-ring), 0 24px 48px -12px rgba(16, 24, 40, .25) !important;
        overflow: hidden;
    }

    .fi-modal-window .fi-modal-header {
        gap: var(--sfk-space-3);
        padding: var(--sfk-space-5) var(--sfk-space-6);
    }

    .fi-modal-window:has(.fi-modal-content) .fi-modal-header {
        min-height: 0;
        border-bottom: 1px solid var(--sfk-line);
    }

    .fi-modal-heading {
        color: var(--sfk-ink);
        font-size: 1.0625rem;
        font-weight: var(--sfk-fw-bold);
    }

    .fi-modal-description {
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-label);
    }

    .fi-modal-content {
        padding: var(--sfk-space-6);
        background-color: var(--sfk-surface);
    }

    .fi-modal-footer {
        padding: var(--sfk-space-4) var(--sfk-space-6);
        background: var(--sfk-head);
        border-top: 1px solid var(--sfk-line);
    }

    .fi-modal-footer .fi-btn {
        min-width: 120px;
        min-height: var(--sfk-control);
        font-size: var(--sfk-fs-body);
    }

    /* a confirmation (no content): the icon, heading and text centred, buttons side by side */
    .fi-modal-window:not(:has(.fi-modal-content)) .fi-modal-footer {
        background: transparent;
        border-top: 0;
        padding-top: 0;
    }

    .fi-modal-icon-bg {
        background: var(--sfk-danger-soft);
    }

    .fi-modal-close-btn {
        color: var(--sfk-subtle);
    }

    .fi-modal-close-overlay {
        background-color: rgba(16, 24, 40, .45) !important;
        backdrop-filter: blur(2px);
    }

    /* ---- sections (cards) -------------------------------------------------------------------------------------- */
    .fi-section:not(.fi-section-not-contained):not(.fi-aside) {
        border-radius: var(--sfk-radius-lg);
    }

    .fi-section-header-heading {
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-heading);
        font-weight: var(--sfk-fw-bold);
    }

    /* ---- the app frame ----------------------------------------------------------------------------------------- */
    .sfk-app-page {
        background: var(--sfk-surface);
    }

    .sfk-app-header {
        display: flex;
        flex-direction: column;
        gap: var(--sfk-space-5);
        padding: var(--sfk-space-6) var(--sfk-gutter) 0;
        background: var(--sfk-surface);
    }

    .sfk-app-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px 16px;
        min-height: var(--sfk-control);
    }

    .sfk-app-head-title {
        position: relative;
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }

    .sfk-app-title {
        min-width: 0;
        margin: 0;
        font-size: var(--sfk-fs-title);
        font-weight: var(--sfk-fw-bold);
        color: var(--sfk-ink);
    }

    .sfk-app-title a {
        color: inherit;
    }

    .sfk-app-title a:hover {
        color: var(--sfk-primary-dark);
    }

    .sfk-app-subtitle {
        font-size: .875rem;
        color: var(--sfk-muted);
        white-space: nowrap;
    }

    .sfk-app-home {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: var(--sfk-radius);
        color: var(--sfk-muted);
    }

    .sfk-app-home:hover {
        background: var(--sfk-hover-strong);
        color: var(--sfk-ink);
    }

    .sfk-app-home-icon {
        width: 20px;
        height: 20px;
    }

    .sfk-app-crumb-sep {
        width: 16px;
        height: 16px;
        color: var(--sfk-subtle);
        flex: none;
    }

    .sfk-app-info {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 9999px;
        color: var(--sfk-muted);
        flex: none;
    }

    .sfk-app-info:hover,
    .sfk-app-info.is-active {
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary-dark);
    }

    .sfk-app-info-icon {
        width: 20px;
        height: 20px;
    }

    .sfk-app-info-panel {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        z-index: 40;
        width: min(560px, calc(100vw - 48px));
        padding: 16px 44px 16px 18px;
        background: #ffffff;
        border-radius: var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow-lg);
    }

    .sfk-app-info-text {
        font-size: var(--sfk-fs-label);
        line-height: 1.7;
        color: var(--sfk-text);
        white-space: pre-line;
    }

    .sfk-app-info-close {
        position: absolute;
        top: 10px;
        right: 10px;
        display: inline-flex;
        padding: 4px;
        border-radius: var(--sfk-radius);
        color: var(--sfk-muted);
    }

    .sfk-app-info-close:hover {
        background: var(--sfk-hover-strong);
        color: var(--sfk-ink);
    }

    .sfk-app-info-close-icon {
        width: 18px;
        height: 18px;
    }

    .sfk-app-head-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-inline-start: auto;
    }

    .sfk-app-head-actions .fi-ac,
    .sfk-app-record-menu .fi-ac {
        gap: 8px;
    }

    /* Filament pulls icon buttons in by their padding; here they are framed, so they keep their place */
    :is(.sfk-app-head-actions, .sfk-app-record-menu, .fi-ta-header-toolbar) .fi-ac>*,
    :is(.sfk-app-head-actions, .sfk-app-record-menu, .fi-ta-header-toolbar) .fi-icon-btn {
        margin: 0 !important;
    }

    :is(.sfk-app-head-actions, .fi-ta-header-toolbar) .fi-dropdown,
    :is(.sfk-app-head-actions, .fi-ta-header-toolbar) .fi-dropdown-trigger {
        display: flex;
    }

    .sfk-app-head-actions .fi-btn,
    .sfk-app-record-menu .fi-btn {
        min-height: var(--sfk-control);
        font-size: var(--sfk-fs-label);
    }

    .sfk-app-head-actions .fi-icon-btn,
    .sfk-app-tool {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: var(--sfk-control);
        height: var(--sfk-control);
        border-radius: var(--sfk-radius);
        background: #ffffff;
        box-shadow: var(--sfk-ring), var(--sfk-shadow);
        color: var(--sfk-muted);
    }

    .sfk-app-head-actions .fi-icon-btn:hover,
    .sfk-app-tool:hover {
        background: var(--sfk-hover);
        color: var(--sfk-ink);
    }

    .sfk-app-tool-icon {
        width: 20px;
        height: 20px;
    }

    .sfk-app-new {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: var(--sfk-control);
        padding: 0 16px;
        border-radius: var(--sfk-radius);
        background: var(--sfk-primary);
        box-shadow: var(--sfk-shadow);
        color: #ffffff;
        font-size: var(--sfk-fs-label);
        font-weight: var(--sfk-fw-semibold);
        white-space: nowrap;
    }

    .sfk-app-new:hover {
        background: var(--sfk-primary-dark);
    }

    .sfk-app-new:focus-visible {
        outline: 2px solid var(--sfk-primary);
        outline-offset: 2px;
    }

    .sfk-app-new-icon {
        width: 18px;
        height: 18px;
    }

    /* エクスポート (core::filament.app-export): a menu with sub-menus of the サブボタン名 */
    .sfk-export {
        position: relative;
    }

    .sfk-export-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: var(--sfk-control);
        padding: 0 14px;
        border-radius: var(--sfk-radius);
        background: #ffffff;
        box-shadow: var(--sfk-ring), var(--sfk-shadow);
        color: var(--sfk-text);
        font-size: var(--sfk-fs-label);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-export-btn:hover {
        background: var(--sfk-hover);
    }

    .sfk-export-icon {
        width: 18px;
        height: 18px;
        color: var(--sfk-muted);
    }

    .sfk-export-chevron {
        width: 16px;
        height: 16px;
        color: var(--sfk-muted);
    }

    .sfk-export-menu,
    .sfk-export-submenu {
        position: absolute;
        z-index: 45;
        min-width: 220px;
        padding: 6px;
        background: #ffffff;
        border-radius: var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow-lg);
    }

    .sfk-export-menu {
        top: calc(100% + 6px);
        right: 0;
    }

    .sfk-export-group {
        position: relative;
    }

    /* the menu sits at the right edge of the screen: sub-menus open to its left */
    .sfk-export-submenu {
        top: -6px;
        right: calc(100% + 4px);
    }

    .sfk-export-item {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 8px 10px;
        border-radius: var(--sfk-radius);
        color: var(--sfk-text);
        font-size: var(--sfk-fs-label);
        text-align: start;
        white-space: nowrap;
    }

    .sfk-export-item:hover,
    .sfk-export-group:hover>.sfk-export-item {
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary-dark);
        text-decoration: none;
    }

    .sfk-export-item span {
        flex: 1;
    }

    .sfk-export-item-icon {
        width: 18px;
        height: 18px;
        color: var(--sfk-muted);
    }

    .sfk-export-item-more {
        width: 16px;
        height: 16px;
        color: var(--sfk-subtle);
    }

    /* ‹ › of a record */
    .sfk-app-pager {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .sfk-app-pager-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: var(--sfk-control);
        border-radius: var(--sfk-radius);
        color: var(--sfk-text);
    }

    .sfk-app-pager-btn:hover:not(.is-disabled) {
        background: var(--sfk-hover-strong);
    }

    .sfk-app-pager-btn.is-disabled {
        color: var(--sfk-disabled-text);
    }

    .sfk-app-pager-icon {
        width: 18px;
        height: 18px;
    }

    /* the page's own buttons and the app's legacy buttons under the name (record / form) */
    .sfk-app-record-menu {
        position: relative;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        padding-bottom: 4px;
    }

    .sfk-app-header-view,
    .sfk-app-header-create,
    .sfk-app-header-edit {
        padding-bottom: 16px;
        border-bottom: 1px solid var(--sfk-line);
    }

    /* ---- summary cards (design_ref/app-index.png): label, key figure, icon in the card's tone; the cards share one
       row while they fit (auto-fit: 4 cards fill the width, 9 small ones stay in one line on a desktop) ----------- */
    .sfk-app-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr));
        gap: var(--sfk-space-4);
    }

    .sfk-stat {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--sfk-space-3);
        min-width: 0;
        min-height: 100px;
        padding: var(--sfk-space-5) var(--sfk-space-5) var(--sfk-space-5) var(--sfk-space-6);
        overflow: hidden;
        background: var(--sfk-surface);
        border-radius: var(--sfk-radius-lg);
        box-shadow: var(--sfk-ring), var(--sfk-shadow);
    }

    /* the soft wedge of the card's tone at its lower right corner */
    .sfk-stat::after {
        content: "";
        position: absolute;
        right: -28px;
        bottom: -46px;
        width: 128px;
        height: 84px;
        background: linear-gradient(90deg, transparent, var(--sfk-tone-soft));
        transform: rotate(-30deg);
        pointer-events: none;
    }

    .sfk-stat-text {
        position: relative;
        z-index: 1;
        min-width: 0;
    }

    .sfk-stat-label {
        overflow: hidden;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-label);
        line-height: var(--sfk-lh-tight);
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sfk-stat-value {
        margin-top: var(--sfk-space-2);
        overflow: hidden;
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-figure);
        font-weight: var(--sfk-fw-semibold);
        line-height: 1.2;
        letter-spacing: 0;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sfk-stat-icon {
        position: relative;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: none;
        align-self: flex-start;
        width: var(--sfk-control);
        height: var(--sfk-control);
        border-radius: 9999px;
        background: var(--sfk-tone);
        box-shadow: 0 4px 10px -4px var(--sfk-tone);
        color: #ffffff;
    }

    .sfk-stat-icon-svg {
        width: 20px;
        height: 20px;
    }

    /* many cards (e.g. 04 / 06): a small tile per figure — the icon beside the label, the figure under them — so
       all of them stay in one row and the list is not pushed down */
    .sfk-app-summary.is-compact {
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 150px), 1fr));
        gap: var(--sfk-space-3);
    }

    .sfk-app-summary.is-compact .sfk-stat {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        grid-template-areas: "icon label" "value value";
        align-items: center;
        gap: var(--sfk-space-2);
        min-height: 0;
        padding: var(--sfk-space-3) var(--sfk-space-4);
    }

    .sfk-app-summary.is-compact .sfk-stat::after {
        width: 96px;
        height: 64px;
        right: -30px;
        bottom: -40px;
    }

    .sfk-app-summary.is-compact .sfk-stat-text {
        display: contents;
    }

    .sfk-app-summary.is-compact .sfk-stat-label {
        grid-area: label;
        position: relative;
        z-index: 1;
        font-size: var(--sfk-fs-small);
    }

    .sfk-app-summary.is-compact .sfk-stat-value {
        grid-area: value;
        position: relative;
        z-index: 1;
        margin-top: 2px;
        font-size: 1.0625rem;
    }

    .sfk-app-summary.is-compact .sfk-stat-icon {
        grid-area: icon;
        align-self: center;
        width: 26px;
        height: 26px;
        border-radius: var(--sfk-radius);
        box-shadow: none;
    }

    .sfk-app-summary.is-compact .sfk-stat-icon-svg {
        width: 16px;
        height: 16px;
    }

    /* the tones: one meaning each (blue = amounts / counts, green = profit / done, amber = waiting, red = overdue / loss) */
    .sfk-tone-blue {
        --sfk-tone: var(--sfk-primary);
        --sfk-tone-soft: var(--sfk-primary-soft);
    }

    .sfk-tone-green {
        --sfk-tone: #16956b;
        --sfk-tone-soft: #e6f5ee;
    }

    .sfk-tone-amber {
        --sfk-tone: #d48806;
        --sfk-tone-soft: #fdf3e1;
    }

    .sfk-tone-red {
        --sfk-tone: #e5484d;
        --sfk-tone-soft: #fdeceb;
    }

    .sfk-tone-purple {
        --sfk-tone: #6e56cf;
        --sfk-tone-soft: #f0edfb;
    }

    .sfk-tone-slate {
        --sfk-tone: #64748b;
        --sfk-tone-soft: #eff1f5;
    }

    /* ---- 一覧 tabs ----------------------------------------------------------------------------------------------- */
    .sfk-app-tabs {
        display: flex;
        gap: 4px;
        overflow-x: auto;
        overflow-y: hidden;
        border-bottom: 1px solid var(--sfk-line);
        scrollbar-width: thin;
    }

    .sfk-app-tab {
        flex: none;
        padding: 10px 14px;
        margin-bottom: -1px;
        border-bottom: 2px solid transparent;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-label);
        white-space: nowrap;
    }

    .sfk-app-tab:hover {
        color: var(--sfk-ink);
    }

    .sfk-app-tab.is-active {
        border-bottom-color: var(--sfk-ink);
        color: var(--sfk-ink);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-app-tab:focus-visible {
        outline: 2px solid var(--sfk-primary);
        outline-offset: -2px;
    }

    /* ---- list content and toolbar ------------------------------------------------------------------------------ */
    .sfk-app-page-list .fi-page-content {
        padding: 0 24px 24px;
    }

    .sfk-app-page-list .fi-ta-ctn,
    .sfk-app-page-list .fi-ta-ctn.fi-ta-ctn-with-header {
        background: transparent;
        box-shadow: none !important;
        border: 0;
        border-radius: 0;
    }

    .sfk-app-page-list .fi-ta-header-ctn {
        background: var(--sfk-surface);
    }

    .sfk-app-page-list .fi-ta-header-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        padding: 16px 0;
        border: 0;
    }

    .sfk-app-page-list .fi-ta-header-toolbar>div:has(.fi-ta-search-field) {
        display: flex;
        flex: 1;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }

    .sfk-app-page-list .fi-ta-search-field {
        width: 260px;
        max-width: 100%;
    }

    .sfk-app-page-list .fi-ta-search-field .fi-input-wrp {
        min-height: var(--sfk-control);
    }

    .sfk-list-tools {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }

    .sfk-list-tool {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: var(--sfk-control);
        padding: 0 12px;
        border-radius: var(--sfk-radius);
        background: #ffffff;
        box-shadow: var(--sfk-ring), var(--sfk-shadow);
        color: var(--sfk-text);
        font-size: var(--sfk-fs-label);
        white-space: nowrap;
    }

    .sfk-list-tool:hover {
        background: var(--sfk-hover);
    }

    .sfk-list-tool.is-active {
        background: var(--sfk-primary-soft);
        box-shadow: 0 0 0 1px var(--sfk-primary);
        color: var(--sfk-primary-dark);
    }

    .sfk-list-tool:focus-visible {
        outline: 2px solid var(--sfk-primary);
        outline-offset: 2px;
    }

    .sfk-list-tool-square {
        justify-content: center;
        width: var(--sfk-control);
        padding: 0;
    }

    .sfk-list-tool-icon {
        width: 18px;
        height: 18px;
        color: var(--sfk-muted);
    }

    .sfk-list-tool.is-active .sfk-list-tool-icon {
        color: var(--sfk-primary-dark);
    }

    .sfk-list-tool-chevron {
        width: 16px;
        height: 16px;
        color: var(--sfk-muted);
    }

    /* 並び替え, 列 and ⚙ at the right end of the row */
    .sfk-list-sort {
        margin-inline-start: auto;
    }

    .sfk-list-sort-value {
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-list-sort-dir {
        padding: 1px 6px;
        border-radius: 4px;
        background: var(--sfk-hover-strong);
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-badge);
    }

    .sfk-list-sort-dirs {
        display: flex;
        gap: 4px;
        padding: 8px 8px 4px;
    }

    .sfk-list-sort-dir-btn {
        flex: 1;
        height: 32px;
        border-radius: var(--sfk-radius);
        box-shadow: 0 0 0 1px var(--sfk-line);
        font-size: var(--sfk-fs-small);
        color: var(--sfk-text);
    }

    .sfk-list-sort-dir-btn.is-active {
        background: var(--sfk-primary-soft);
        box-shadow: 0 0 0 1px var(--sfk-primary);
        color: var(--sfk-primary-dark);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-app-page-list .fi-ta-header-toolbar .fi-dropdown-trigger .fi-btn {
        min-height: var(--sfk-control);
    }

    .sfk-app-page-list .fi-ta-header-toolbar .fi-btn {
        height: var(--sfk-control);
        font-weight: 400;
        font-size: var(--sfk-fs-label);
    }

    .sfk-app-settings-btn.fi-icon-btn,
    .sfk-app-page-list .fi-ta-header-toolbar .sfk-app-settings-btn {
        width: var(--sfk-control);
        height: var(--sfk-control);
        background: #ffffff;
        box-shadow: var(--sfk-ring), var(--sfk-shadow);
        color: var(--sfk-muted);
    }

    .sfk-app-settings-btn.fi-icon-btn:hover {
        background: var(--sfk-hover);
        color: var(--sfk-ink);
    }

    /* the toolbar of a calendar / custom view */
    .sfk-app-list-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding-bottom: 12px;
    }

    .sfk-list-tools-end {
        margin-inline-start: auto;
    }

    /* the legacy buttons under the toolbar */
    .sfk-app-menu-row {
        position: relative;
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: 8px;
        padding: 0 0 16px;
    }

    .sfk-app-menu .sfk-menu-btn.fi-btn {
        height: 38px;
        padding: 0 14px;
        border-radius: var(--sfk-radius) !important;
        background: #ffffff !important;
        border: 0;
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow) !important;
        color: var(--sfk-text) !important;
        font-weight: 500;
    }

    .sfk-app-menu .sfk-menu-btn.fi-btn .fi-icon {
        color: var(--sfk-primary) !important;
    }

    .sfk-app-menu .sfk-menu-btn.fi-btn:hover {
        background: var(--sfk-hover) !important;
        box-shadow: 0 0 0 1px var(--sfk-line-strong), var(--sfk-shadow) !important;
    }

    .sfk-app-menu-group {
        gap: 8px;
    }

    .sfk-app-menu-group>*+* {
        margin-inline-start: 0;
    }

    /* ボタンの説明 popup */
    .sfk-app-help {
        position: absolute;
        top: calc(100% - 8px);
        left: 0;
        z-index: 40;
        width: min(640px, calc(100vw - 48px));
        max-height: 60vh;
        overflow: auto;
        background: #ffffff;
        border-radius: var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow-lg);
    }

    .sfk-app-header .sfk-app-help {
        top: calc(100% + 4px);
    }

    .sfk-app-help-head {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--sfk-line);
    }

    .sfk-app-help-icon {
        width: 20px;
        height: 20px;
        color: var(--sfk-primary);
    }

    .sfk-app-help-title {
        flex: 1;
        font-weight: var(--sfk-fw-bold);
        color: var(--sfk-ink);
    }

    .sfk-app-help-close {
        display: inline-flex;
        padding: 4px;
        border-radius: var(--sfk-radius);
        color: var(--sfk-muted);
    }

    .sfk-app-help-close:hover {
        background: var(--sfk-hover-strong);
    }

    .sfk-app-help-close-icon {
        width: 18px;
        height: 18px;
    }

    .sfk-app-help-list {
        display: flex;
        flex-direction: column;
        margin: 0;
        padding: 8px 16px 12px;
    }

    .sfk-app-help-item {
        display: grid;
        grid-template-columns: minmax(160px, auto) 1fr;
        gap: 4px 16px;
        padding: 8px 0;
        border-bottom: 1px solid var(--sfk-line-soft);
        font-size: var(--sfk-fs-label);
    }

    .sfk-app-help-item:last-child {
        border-bottom: 0;
    }

    .sfk-app-help-item dt {
        font-weight: var(--sfk-fw-semibold);
        color: var(--sfk-ink);
    }

    .sfk-app-help-item dd {
        margin: 0;
        color: var(--sfk-text);
        white-space: pre-line;
    }

    /* ---- the table ---------------------------------------------------------------------------------------------- */
    .sfk-app-page-list .fi-ta-main {
        padding: 1px;
    }

    /* the table reaches down to the paging at the bottom of the screen, also with few or no rows: every box from the
       page down to the table stretches */
    .fi-main-ctn:has(.sfk-app-page-list),
    .fi-main:has(.sfk-app-page-list) {
        min-height: 100dvh;
    }

    .sfk-app-page-list .fi-page-content,
    .sfk-app-page-list .fi-page-content>.fi-sc,
    .sfk-app-page-list .fi-page-content>.fi-sc>.fi-grid-col:has(.fi-ta),
    .sfk-app-page-list .fi-page-content>.fi-sc>.fi-grid-col>.fi-sc-component:has(> .fi-ta),
    .sfk-app-page-list .fi-ta:has(> .fi-ta-ctn),
    .sfk-app-page-list .fi-ta-ctn,
    .sfk-app-page-list .fi-ta-main {
        display: flex;
        flex: 1;
        flex-direction: column;
        min-height: 0;
    }

    .sfk-app-page-list .fi-page-content>.fi-sc {
        gap: 0;
    }

    .sfk-app-page-list .fi-ta-content-ctn {
        flex: 1;
        min-height: 240px;
    }

    /* no rows: the header row, then the message filling the rest of the same box */
    .sfk-app-page-list .fi-ta-main:has(> .fi-ta-empty-state)>.fi-ta-content-ctn {
        flex: none;
        min-height: 0;
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
    }

    .sfk-app-page-list .fi-ta-main>.fi-ta-empty-state {
        display: flex;
        flex: 1;
        align-items: center;
        justify-content: center;
        min-height: 240px;
        background: #ffffff;
        border-radius: 0 0 var(--sfk-radius-lg) var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line);
    }

    .sfk-app-page-list .fi-ta-content-ctn {
        min-width: 100%;
        width: max-content;
        border-radius: var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line);
        background: #ffffff;
        overflow: hidden;
    }

    .sfk-app-page-list .fi-ta-table {
        min-width: 100%;
    }

    .sfk-app-page-list .fi-ta-table :is(td, th) {
        border-inline: 0 !important;
    }

    .sfk-app-page-list .fi-ta-table>thead>tr {
        background-color: var(--sfk-list-head);
    }

    /* white labels and light sort marks on the dark header; the sorted column's mark is white */
    .sfk-app-page-list .fi-ta-header-cell-sort-btn {
        color: inherit;
    }

    .sfk-app-page-list .fi-ta-header-cell-sort-btn>.fi-icon {
        color: #8b96a8;
    }

    .sfk-app-page-list .fi-ta-header-cell-sort-btn:hover>.fi-icon,
    .sfk-app-page-list .fi-ta-header-cell[aria-sort="ascending"] .fi-ta-header-cell-sort-btn>.fi-icon,
    .sfk-app-page-list .fi-ta-header-cell[aria-sort="descending"] .fi-ta-header-cell-sort-btn>.fi-icon {
        color: #ffffff;
    }

    .sfk-app-page-list .fi-ta-header-cell {
        padding-block: 12px;
        border-bottom: 1px solid var(--sfk-line);
        color: #ffffff;
        font-size: var(--sfk-fs-label);
        font-weight: var(--sfk-fw-semibold);
        white-space: nowrap;
    }

    .sfk-app-page-list .fi-ta-table>thead>tr>th {
        border-inline-end: 0;
    }

    .sfk-app-page-list .fi-ta-table>tbody>tr>td {
        border-bottom: 1px solid var(--sfk-line-soft);
    }

    .sfk-app-page-list .fi-ta-row:hover>td {
        background-color: var(--sfk-hover);
    }

    .sfk-app-page-list .fi-ta-cell {
        color: var(--sfk-text);
    }

    .sfk-app-page-list .fi-ta-text-item {
        padding-block: 6px;
    }

    /* a value longer than its column stays on one line, cut off with 「…」; the whole text shows on hover (the title
       is set by sfkCellTitles in core::filament.app-filter-script) */
    .sfk-app-page-list .fi-ta-table>tbody>tr>td.fi-ta-cell:not(.fi-ta-selection-cell):not(:has(.fi-ta-actions)) {
        max-width: 22rem;
    }

    .sfk-app-page-list .fi-ta-table>tbody .fi-ta-text,
    .sfk-app-page-list .fi-ta-table>tbody .fi-ta-text-item,
    .sfk-app-page-list .fi-ta-table>tbody .fi-ta-text-item-label,
    .sfk-app-page-list .fi-ta-table>tbody .fi-ta-col {
        max-width: 100%;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap !important;
    }

    /* several values of a cell (e.g. 取引先カテゴリ) side by side, separated by 「、」, instead of one per line */
    .sfk-app-page-list .fi-ta-table>tbody ul.fi-ta-text {
        display: block;
    }

    .sfk-app-page-list .fi-ta-table>tbody ul.fi-ta-text>li {
        display: inline;
    }

    .sfk-app-page-list .fi-ta-table>tbody ul.fi-ta-text>li+li::before {
        content: "、";
    }

    .sfk-row-menu.fi-icon-btn {
        width: 30px;
        height: 30px;
        border-radius: 9999px;
        box-shadow: 0 0 0 1px var(--sfk-line);
        color: var(--sfk-muted);
    }

    .sfk-row-menu.fi-icon-btn:hover {
        background: var(--sfk-hover-strong);
        color: var(--sfk-ink);
    }

    /* badges: soft tint with the text in the colour */
    .fi-badge {
        border-radius: var(--sfk-radius);
    }

    /* ---- under the table: 表示件数 and the page numbers ----------------------------------------------------------- */
    .sfk-list-footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 24px 24px;
        background: var(--sfk-surface);
    }

    .sfk-list-size {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: var(--sfk-fs-label);
        color: var(--sfk-text);
    }

    .sfk-list-size-select {
        height: 36px;
        padding: 0 32px 0 12px;
        border: 1px solid var(--sfk-line);
        border-radius: var(--sfk-radius);
        background-color: #ffffff;
        font-size: var(--sfk-fs-label);
        color: var(--sfk-ink);
        cursor: pointer;
    }

    .sfk-list-size-select:focus-visible {
        outline: 2px solid var(--sfk-primary);
        outline-offset: 1px;
    }

    .sfk-list-pages {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 16px;
    }

    .sfk-list-count {
        font-size: var(--sfk-fs-label);
        color: var(--sfk-muted);
        white-space: nowrap;
    }

    .sfk-pages {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .sfk-page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 34px;
        padding: 0 8px;
        border-radius: var(--sfk-radius);
        box-shadow: 0 0 0 1px var(--sfk-line);
        background: #ffffff;
        color: var(--sfk-text);
        font-size: var(--sfk-fs-label);
        font-variant-numeric: tabular-nums;
    }

    .sfk-page-btn:hover:not(:disabled) {
        background: var(--sfk-hover);
    }

    .sfk-page-btn.is-current {
        box-shadow: 0 0 0 1px var(--sfk-primary);
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary-dark);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-page-btn:disabled {
        color: var(--sfk-disabled-text);
        cursor: default;
    }

    .sfk-page-btn:focus-visible {
        outline: 2px solid var(--sfk-primary);
        outline-offset: 1px;
    }

    .sfk-page-icon {
        width: 16px;
        height: 16px;
    }

    .sfk-page-gap {
        padding: 0 2px;
        color: var(--sfk-muted);
    }

    /* ---- record form and record page (design_ref/add-invoice.png) ---------------------------------------------- */
    :is(.sfk-app-page-create, .sfk-app-page-edit, .sfk-app-page-view) .fi-page-content {
        padding: 24px;
        background: var(--sfk-page);
        border-top: 0;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit, .sfk-app-page-view) .fi-page-content .fi-section:not(.fi-section-not-contained) {
        background: #ffffff;
        border-radius: var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow) !important;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit, .sfk-app-page-view) .fi-page-content .fi-section-header {
        padding: 18px 28px 0;
        background: transparent;
        border-bottom: 0;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit, .sfk-app-page-view) .fi-section-header-heading {
        font-size: 1rem;
        font-weight: var(--sfk-fw-bold);
        color: var(--sfk-ink);
    }

    /* group headings (RecordSections string rows): a bold title with a rule above it, like 「Bill From」 / 「Items & Details」 */
    .kt-record-label {
        margin-top: 12px;
        padding: 20px 0 0;
        border-top: 1px solid var(--sfk-line);
        border-bottom: 0;
        color: var(--sfk-ink);
        font-size: 1rem;
        font-weight: var(--sfk-fw-bold);
        letter-spacing: 0;
    }

    .kt-record-label::before {
        display: none;
    }

    .kt-record .fi-sc>.fi-grid-col:first-child .kt-record-label {
        margin-top: 0;
        padding-top: 0;
        border-top: 0;
    }

    .kt-record .fi-sc>.fi-grid-col:has(.kt-record-label)+.fi-grid-col .kt-record-label {
        margin-top: -6px;
        padding-top: 0;
        border-top: 0;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit) .fi-fo-field-label-content,
    .sfk-app-page-view .kt-record .fi-in-entry-label {
        font-size: var(--sfk-fs-label);
        font-weight: 500;
        color: var(--kt-label);
    }

    /* read-only values on the record page: plain text on a soft ground, no input border */
    .sfk-app-page-view .kt-record .fi-in-entry-content {
        min-height: 2.5rem;
        padding: 8px 12px;
        border: 0;
        border-radius: var(--sfk-radius);
        background: var(--sfk-head);
        color: var(--sfk-ink);
    }

    /* tabs above the fields */
    .kt-record-tabs {
        gap: 4px;
        border-bottom: 1px solid var(--sfk-line);
    }

    .kt-record-tab,
    .kt-record-tab:last-child {
        min-height: 44px;
        padding: 0 16px;
        margin-bottom: -1px;
        background: transparent;
        border: 0;
        border-bottom: 2px solid transparent;
        border-radius: 0;
        box-shadow: none;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-label);
    }

    .kt-record-tab:hover {
        background: transparent;
        color: var(--sfk-ink);
    }

    .kt-record-tab.kt-active {
        background: transparent;
        border-bottom-color: var(--sfk-ink);
        color: var(--sfk-ink);
        font-weight: var(--sfk-fw-semibold);
    }

    /* tables of a record (サブテーブル): a dark header row, rounded box */
    .kt-subtable {
        border-radius: var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line);
        overflow: auto;
    }

    .kt-subtable>table>thead>tr>th {
        height: var(--sfk-control);
        padding: 6px 10px;
        background-color: var(--sfk-table-head-dark);
        color: #ffffff;
        font-size: var(--sfk-fs-small);
        font-weight: var(--sfk-fw-semibold);
    }

    .kt-subtable>table>thead>tr>th.fi-fo-table-repeater-empty-header-cell {
        background: var(--sfk-table-head-dark);
    }

    .kt-subtable>table>tbody>tr>td {
        padding-block: 8px;
        border-color: var(--sfk-line-soft);
    }

    /* every other table of a record, form or dialog (related lists, 過去の受注履歴検索, previews, 受注書 FAX…): the same
       look as the サブテーブル — rounded box, dark header row, hairline rows */
    :is(.sfk-related-table, .sfk-order-past-table, .sfk-data-table-wrap) {
        overflow-x: auto;
        border-radius: var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line);
        background: #ffffff;
    }

    :is(.sfk-related-table, .sfk-order-past-table) table,
    table:is(.sfk-data-table, .sfk-fax-table) {
        width: 100%;
        border-collapse: collapse;
        font-size: var(--sfk-fs-label);
    }

    :is(.sfk-related-table, .sfk-order-past-table) th,
    table:is(.sfk-data-table, .sfk-fax-table) th {
        height: var(--sfk-control);
        padding: 6px 12px !important;
        background: var(--sfk-table-head-dark) !important;
        border: 0 !important;
        color: #ffffff !important;
        font-size: var(--sfk-fs-small);
        font-weight: var(--sfk-fw-semibold);
        text-align: start;
        white-space: nowrap;
    }

    :is(.sfk-related-table, .sfk-order-past-table) td,
    table:is(.sfk-data-table, .sfk-fax-table) td {
        padding: 10px 12px !important;
        border: 0 !important;
        border-top: 1px solid var(--sfk-line-soft) !important;
        color: var(--sfk-text);
    }

    :is(.sfk-related-table, .sfk-order-past-table) tbody tr:hover td,
    table.sfk-data-table tbody tr:hover td {
        background: var(--sfk-hover);
    }

    :is(.sfk-related-table, .sfk-order-past-table) :is(th, td).is-number {
        text-align: end;
    }

    :is(.sfk-related-table, .sfk-order-past-table) a {
        color: var(--sfk-primary-dark);
    }

    .sfk-related-title {
        margin: 0 0 8px;
        color: var(--sfk-ink);
        font-size: .875rem;
        font-weight: var(--sfk-fw-bold);
    }

    /* 過去の受注履歴検索: a card with a plain heading, the search row on a soft ground */
    .sfk-order-past {
        overflow: hidden;
        border: 0 !important;
        border-radius: var(--sfk-radius-lg);
        box-shadow: var(--sfk-ring), var(--sfk-shadow);
    }

    .sfk-order-past-head {
        padding: 14px 18px !important;
        background: #ffffff !important;
        border-bottom: 1px solid var(--sfk-line);
    }

    .sfk-order-past-title {
        color: var(--sfk-ink) !important;
        font-size: .9375rem !important;
    }

    .sfk-order-past-toggle {
        border-radius: var(--sfk-radius);
        background: #ffffff !important;
        box-shadow: 0 0 0 1px var(--sfk-line);
        color: var(--sfk-text) !important;
    }

    .sfk-order-past-search {
        background: var(--sfk-head) !important;
        border-bottom: 1px solid var(--sfk-line) !important;
    }

    .sfk-order-past-field input {
        border-radius: var(--sfk-radius);
    }

    /* 検索 dark like the table header, リセット with dark text */
    .sfk-order-past-search .sfk-filter-btn {
        border-radius: var(--sfk-radius);
    }

    .sfk-order-past-search .sfk-filter-btn.sfk-filter-btn-submit {
        background: var(--sfk-table-head-dark) !important;
        border-color: var(--sfk-table-head-dark) !important;
        color: #ffffff !important;
    }

    .sfk-order-past-search .sfk-filter-btn.sfk-filter-btn-submit:hover {
        background: #000000 !important;
    }

    .sfk-order-past-search .sfk-filter-btn:not(.sfk-filter-btn-submit) {
        color: var(--sfk-table-head-dark) !important;
    }

    .sfk-order-past-page {
        border-radius: var(--sfk-radius);
    }

    .sfk-order-past .sfk-order-past-table {
        margin: 0 16px 16px;
    }

    /* the footer of a form: キャンセル on the left, 保存 on the right, kept at the bottom of the screen (sticky in the
       page instead of Filament's fixed bar, so it stays inside the content next to the sidebar) */
    :is(.sfk-app-page-create, .sfk-app-page-edit) .fi-sc-form>.fi-sc:has(> .fi-grid-col > .fi-sc-component > .fi-sc-actions > .fi-ac) {
        position: sticky;
        bottom: 0;
        z-index: 25;
        margin: 8px -24px -24px;
        padding: 14px 24px;
        background: #ffffff;
        border-top: 1px solid var(--sfk-line);
        box-shadow: 0 -4px 12px rgba(16, 24, 40, .06);
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit) .fi-sc-actions>.fi-ac {
        position: static !important;
        justify-content: space-between;
        width: 100%;
        padding: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        transform: none !important;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit) .fi-sc-actions>.fi-ac .fi-btn {
        min-width: 120px;
        min-height: 42px;
        font-size: .875rem;
    }

    /* ---- a record with tabs (design_ref/customer-*.png; core::filament.record-profile / record-tab) ---------------- */
    .sfk-profile-card {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 620px);
        gap: 24px;
        padding: 22px 24px;
        margin-bottom: 20px;
        background: #ffffff;
        border-radius: var(--sfk-radius-lg);
        box-shadow: var(--sfk-ring), var(--sfk-shadow);
    }

    @media (max-width: 1100px) {
        .sfk-profile-card {
            grid-template-columns: 1fr;
        }
    }

    .sfk-profile-card-main {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 24px;
        min-width: 0;
    }

    .sfk-profile-id {
        display: flex;
        align-items: center;
        gap: 18px;
    }

    .sfk-profile-avatar {
        display: grid;
        place-items: center;
        flex: none;
        width: 88px;
        height: 88px;
        border-radius: 9999px;
        background: linear-gradient(135deg, #2b6aa0, var(--sfk-primary-dark));
        color: #ffffff;
        font-size: 1.25rem;
        font-weight: var(--sfk-fw-bold);
    }

    .sfk-profile-code {
        color: var(--sfk-primary);
        font-size: var(--sfk-fs-label);
    }

    .sfk-profile-name {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-top: 2px;
        font-size: 1.125rem;
        font-weight: var(--sfk-fw-bold);
        color: var(--sfk-ink);
    }

    .sfk-profile-badge {
        padding: 2px 8px;
        border-radius: 9999px;
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary-dark);
        font-size: var(--sfk-fs-badge);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-profile-meta {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 6px;
        font-size: var(--sfk-fs-label);
        color: var(--sfk-muted);
    }

    .sfk-profile-meta-icon {
        width: 16px;
        height: 16px;
    }

    .sfk-profile-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin: 0;
    }

    .sfk-profile-info dt {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: var(--sfk-fs-label);
        font-weight: var(--sfk-fw-semibold);
        color: var(--sfk-ink);
    }

    .sfk-profile-info-icon {
        width: 16px;
        height: 16px;
        color: var(--sfk-muted);
    }

    .sfk-profile-info dd {
        margin: 4px 0 0;
        font-size: var(--sfk-fs-label);
        color: var(--sfk-text);
        overflow-wrap: anywhere;
    }

    .sfk-profile-cards {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        align-content: start;
    }

    .sfk-profile-stat {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 18px;
        background: #ffffff;
        border-radius: var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line), 0 4px 12px rgba(16, 24, 40, .06);
    }

    .sfk-profile-stat-value {
        font-size: 1.125rem;
        font-weight: var(--sfk-fw-bold);
        color: var(--sfk-ink);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sfk-profile-stat-label {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 2px;
        font-size: var(--sfk-fs-label);
        color: var(--sfk-text);
    }

    .sfk-profile-stat-dot {
        width: 10px;
        height: 10px;
        border-radius: 9999px;
        background: var(--sfk-tone);
    }

    .sfk-profile-stat-icon {
        display: grid;
        place-items: center;
        flex: none;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: var(--sfk-tone);
        color: #ffffff;
    }

    .sfk-profile-stat-svg {
        width: 20px;
        height: 20px;
    }

    .sfk-profile-tabs {
        display: flex;
        gap: 4px;
        overflow-x: auto;
        margin-bottom: 20px;
        /* padding: 0 8px; */
        background: #ffffff;
        border-radius: var(--sfk-radius-lg);
        box-shadow: var(--sfk-ring), var(--sfk-shadow);
    }

    .sfk-profile-tab {
        display: inline-flex;
        flex: none;
        align-items: center;
        gap: 8px;
        padding: 14px 16px;
        border-bottom: 2px solid transparent;
        color: var(--sfk-text);
        font-size: var(--sfk-fs-label);
        white-space: nowrap;
    }

    .sfk-profile-tab:hover {
        color: var(--sfk-primary-dark);
    }

    .sfk-profile-tab.is-active {
        border-bottom-color: var(--sfk-primary);
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary-dark);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-profile-tab-icon {
        width: 18px;
        height: 18px;
    }

    .sfk-record-tab {
        display: flex;
        flex-direction: column;
        gap: 16px;
        padding: 20px;
        background: #ffffff;
        border-radius: var(--sfk-radius-lg);
        box-shadow: var(--sfk-ring), var(--sfk-shadow);
    }

    .sfk-record-tab-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 16px;
        padding: 18px 20px;
        border-radius: var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line);
    }

    .sfk-record-tab-stat-label {
        font-size: var(--sfk-fs-label);
        color: var(--sfk-muted);
    }

    .sfk-record-tab-stat-value {
        margin-top: 4px;
        font-size: 1rem;
        font-weight: var(--sfk-fw-bold);
        color: var(--sfk-ink);
        font-variant-numeric: tabular-nums;
    }

    .sfk-record-tab-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .sfk-record-tab-search {
        position: relative;
        display: flex;
        align-items: center;
        width: 280px;
        max-width: 100%;
    }

    .sfk-record-tab-search input {
        width: 100%;
        height: var(--sfk-control);
        padding: 0 12px 0 36px;
        border: 1px solid var(--sfk-line-strong);
        border-radius: var(--sfk-radius);
        font-size: var(--sfk-fs-label);
    }

    .sfk-record-tab-search input:focus {
        outline: none;
        border-color: var(--sfk-primary);
        box-shadow: 0 0 0 4px var(--sfk-primary-soft);
    }

    .sfk-record-tab-search-icon {
        position: absolute;
        left: 12px;
        width: 18px;
        height: 18px;
        color: var(--sfk-muted);
    }

    .sfk-record-tab-count {
        font-size: var(--sfk-fs-label);
        color: var(--sfk-muted);
    }

    .sfk-record-tab-table {
        overflow-x: auto;
        border-radius: var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line);
    }

    .sfk-record-tab-table table {
        width: 100%;
        border-collapse: collapse;
        font-size: var(--sfk-fs-label);
    }

    .sfk-record-tab-table th {
        padding: 0;
        background: var(--sfk-table-head);
        border-bottom: 1px solid var(--sfk-line);
        text-align: start;
        white-space: nowrap;
    }

    .sfk-record-tab-table th button {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        width: 100%;
        padding: 12px 16px;
        color: var(--sfk-ink);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-record-tab-sort {
        width: 14px;
        height: 14px;
        color: var(--sfk-subtle);
    }

    .sfk-record-tab-table td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--sfk-line-soft);
        color: var(--sfk-text);
        white-space: nowrap;
    }

    .sfk-record-tab-table tr:last-child td {
        border-bottom: 0;
    }

    .sfk-record-tab-table tr.is-link {
        cursor: pointer;
    }

    .sfk-record-tab-table tr.is-link:hover td {
        background: var(--sfk-hover);
    }

    .sfk-record-tab-table .is-number {
        text-align: end;
        font-variant-numeric: tabular-nums;
    }

    .sfk-record-tab-table .is-number button {
        justify-content: flex-end;
    }

    .sfk-record-tab-table td a {
        color: var(--sfk-primary-dark);
    }

    .sfk-record-tab-badge {
        display: inline-block;
        padding: 1px 8px;
        border-radius: var(--sfk-radius);
        background: var(--sfk-tone-soft);
        color: var(--sfk-tone);
        font-size: var(--sfk-fs-badge);
        font-weight: var(--sfk-fw-semibold);
    }

    /* ---- プロフィール (design_ref/profile-page.png) ---------------------------------------------------------------- */
    .sfk-profile {
        display: grid;
        grid-template-columns: 300px minmax(0, 1fr);
        gap: 28px;
        align-items: start;
    }

    @media (max-width: 900px) {
        .sfk-profile {
            grid-template-columns: 1fr;
        }
    }

    .sfk-profile-nav {
        background: #ffffff;
        border-radius: var(--sfk-radius-lg);
        box-shadow: var(--sfk-ring), var(--sfk-shadow);
        overflow: hidden;
    }

    .sfk-profile-nav-title {
        margin: 0;
        padding: 16px 20px;
        border-bottom: 1px solid var(--sfk-line);
        font-size: 1.0625rem;
        font-weight: var(--sfk-fw-bold);
        color: var(--sfk-ink);
    }

    .sfk-profile-nav ul {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin: 0;
        padding: 12px;
        list-style: none;
    }

    .sfk-profile-nav-item {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 10px 12px;
        border-radius: var(--sfk-radius);
        color: var(--sfk-text);
        font-size: var(--sfk-fs-label);
        text-align: start;
    }

    .sfk-profile-nav-item:hover {
        background: var(--sfk-hover);
    }

    .sfk-profile-nav-item.is-active {
        background: var(--sfk-primary-soft);
        box-shadow: inset 3px 0 0 var(--sfk-primary);
        color: var(--sfk-primary-dark);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-profile-nav-icon {
        width: 18px;
        height: 18px;
        flex: none;
    }

    .sfk-profile-nav-item span {
        flex: 1;
    }

    .sfk-profile-nav-chevron {
        width: 16px;
        height: 16px;
        color: var(--sfk-subtle);
    }

    .sfk-profile-title {
        margin: 0 0 16px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--sfk-line);
        font-size: 1.0625rem;
        font-weight: var(--sfk-fw-bold);
        color: var(--sfk-ink);
    }

    /* each part of a setting: no box, a dark icon tile before its title, a rule between the parts */
    .sfk-profile-main .fi-section:not(.fi-section-not-contained) {
        background: transparent;
        box-shadow: none !important;
        border-radius: 0;
    }

    .sfk-profile-main .fi-section+.fi-section,
    .sfk-profile-main .fi-grid-col+.fi-grid-col .fi-section {
        border-top: 1px solid var(--sfk-line);
    }

    .sfk-profile-main .fi-section-header {
        padding: 16px 0 4px;
        background: transparent;
        border-bottom: 0;
    }

    .sfk-profile-main .fi-section-content-ctn {
        border-top: 0;
    }

    .sfk-profile-main .fi-section-header>.fi-icon {
        width: 32px;
        height: 32px;
        padding: 7px;
        border-radius: var(--sfk-radius);
        background: var(--sfk-ink);
        color: #ffffff;
    }

    .sfk-profile-main .fi-section-header-heading {
        font-size: 1rem;
        font-weight: var(--sfk-fw-bold);
        color: var(--sfk-ink);
    }

    .sfk-profile-main .fi-section-content-ctn>.fi-section-content {
        padding: 12px 0 20px;
    }

    .sfk-profile-main .fi-in-entry-content {
        min-height: 2.5rem;
        padding: 8px 12px;
        border-radius: var(--sfk-radius);
        background: var(--sfk-head);
    }

    .sfk-profile-main .fi-sc-actions {
        padding-top: 16px;
        border-top: 1px solid var(--sfk-line);
    }

    .sfk-profile-main .fi-sc-actions .fi-btn {
        min-width: 120px;
        min-height: 42px;
    }

    /* ---- the record side panel (コメント | 変更履歴) -------------------------------------------------------------- */
    .sfk-record-sidebar,
    .sfk-record-sidebar-collapsed {
        border-radius: var(--sfk-radius-lg);
    }

    /* =============================================================================================================
       Shared components (ui-refine.md, 2026-11): the same sizes, colours and spacing on every screen
       ============================================================================================================= */

    /* ---- sidebar: white column, quiet group names, 38px items, the active item tinted with a bar ---------------- */
    .fi-sidebar {
        background-color: var(--sfk-surface);
        border-inline-end: 1px solid var(--sfk-line);
    }

    .fi-sidebar-nav {
        padding-inline: var(--sfk-space-3);
    }

    .fi-sidebar-group-label {
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-small);
        font-weight: var(--sfk-fw-semibold);
        letter-spacing: .04em;
    }

    .fi-sidebar-item-btn {
        min-height: 38px;
        padding-block: 7px;
        border-radius: var(--sfk-radius);
        transition: background-color .15s, color .15s;
    }

    .fi-sidebar-item-label {
        color: var(--sfk-text);
        font-size: var(--sfk-fs-label);
        font-weight: var(--sfk-fw-regular);
    }

    .fi-sidebar-item-icon {
        color: var(--sfk-subtle);
    }

    .fi-sidebar-item:not(.fi-active)>.fi-sidebar-item-btn:hover {
        background-color: var(--sfk-hover);
    }

    .fi-sidebar-item:not(.fi-active)>.fi-sidebar-item-btn:hover :is(.fi-sidebar-item-label, .fi-sidebar-item-icon) {
        color: var(--sfk-ink);
    }

    .fi-sidebar-item.fi-active>.fi-sidebar-item-btn {
        background-color: var(--sfk-primary-soft);
        box-shadow: inset 3px 0 0 var(--sfk-primary);
    }

    .fi-sidebar-item.fi-active :is(.fi-sidebar-item-label, .fi-sidebar-item-icon) {
        color: var(--sfk-primary-dark);
        font-weight: var(--sfk-fw-semibold);
    }

    .fi-sidebar-footer {
        border-top: 1px solid var(--sfk-line);
    }

    .sfk-dashboard-switcher-btn {
        color: var(--sfk-ink);
        font-size: 1.0625rem;
        font-feature-settings: "palt";
        letter-spacing: var(--sfk-tracking-heading);
    }

    /* ---- pages that are not app screens (settings, dashboards, profile): the same gutter and title ---------------- */
    .fi-header-heading {
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-title);
        font-weight: var(--sfk-fw-bold);
    }

    .fi-header-subheading {
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-label);
    }

    .fi-breadcrumbs-item-label {
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-small);
    }

    .fi-breadcrumbs-item:last-child .fi-breadcrumbs-item-label {
        color: var(--sfk-ink);
    }

    /* ---- tables (every Filament table; the app lists add their frame above) --------------------------------------- */
    .fi-ta-header-cell {
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-label);
        font-weight: var(--sfk-fw-semibold);
        letter-spacing: var(--sfk-tracking);
    }

    .fi-ta-table>thead>tr {
        background-color: var(--sfk-table-head);
    }

    .fi-ta-cell {
        color: var(--sfk-text);
    }

    .fi-ta-row:hover>td {
        background-color: var(--sfk-hover);
    }

    .fi-ta-row.fi-selected>td,
    .fi-ta-row:has(.fi-ta-record-checkbox:checked)>td {
        background-color: var(--sfk-primary-soft);
    }

    /* sort marks: quiet until the column is sorted or the header is pointed at */
    .fi-ta-header-cell-sort-btn {
        gap: 4px;
    }

    .fi-ta-header-cell-sort-btn>.fi-icon {
        width: 14px;
        height: 14px;
        color: var(--sfk-disabled-text);
        transition: color .15s;
    }

    .fi-ta-header-cell-sort-btn:hover>.fi-icon,
    .fi-ta-header-cell[aria-sort="ascending"] .fi-ta-header-cell-sort-btn>.fi-icon,
    .fi-ta-header-cell[aria-sort="descending"] .fi-ta-header-cell-sort-btn>.fi-icon {
        color: var(--sfk-primary);
    }

    /* the app list: comfortable 48px rows, header 40px */
    .sfk-app-page-list .fi-ta-table>tbody>tr>td.fi-ta-cell {
        height: 48px;
    }

    .sfk-app-page-list .fi-ta-table>tbody>tr:last-child>td {
        border-bottom: 0;
    }

    .sfk-app-page-list .fi-ta-header-cell,
    .sfk-app-page-list .fi-ta-table>thead>tr>th.fi-ta-selection-cell {
        height: 40px;
        padding-block: 8px;
    }

    /* the search box and the column-search button beside it: one control height */
    .sfk-app-page-list .fi-ta-search-field .fi-input-wrp {
        background: var(--sfk-surface);
    }

    .sfk-search-column-btn {
        width: var(--sfk-control);
        min-height: var(--sfk-control);
        border: 0;
        border-radius: var(--sfk-radius);
        background: var(--sfk-surface);
        box-shadow: 0 0 0 1px var(--sfk-line-strong), var(--sfk-shadow);
        color: var(--sfk-muted);
    }

    .sfk-search-column-btn .fi-icon {
        width: 18px;
        height: 18px;
    }

    .sfk-search-column-btn:hover {
        background: var(--sfk-hover);
        color: var(--sfk-ink);
    }

    .sfk-search-column-btn.is-active {
        background: var(--sfk-primary-soft);
        box-shadow: 0 0 0 1px var(--sfk-primary);
        color: var(--sfk-primary-dark);
    }

    /* Filament's own paging (tables outside the app lists) */
    .fi-pagination-item-btn {
        font-variant-numeric: tabular-nums;
    }

    .fi-pagination-item.fi-active .fi-pagination-item-btn {
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary-dark);
    }

    /* ---- empty states: a soft icon tile and one line, centred ---------------------------------------------------- */
    .fi-ta-empty-state-icon-bg,
    .fi-empty-state-icon-bg {
        background: var(--sfk-head);
        box-shadow: var(--sfk-ring);
    }

    .fi-ta-empty-state-icon-bg .fi-icon,
    .fi-empty-state-icon-bg .fi-icon {
        color: var(--sfk-subtle);
    }

    .fi-ta-empty-state-heading,
    .fi-empty-state-heading {
        color: var(--sfk-text);
        font-size: var(--sfk-fs-body);
        font-weight: var(--sfk-fw-medium);
    }

    /* ---- badges: soft tint, the text in a darker shade of the colour (readable), pill-shaped ----------------------- */
    .fi-badge {
        min-height: 24px;
        padding: 2px 8px;
        gap: 4px;
        border-radius: 9999px;
        box-shadow: none !important;
        font-size: var(--sfk-fs-badge);
        font-weight: var(--sfk-fw-medium);
        line-height: 1.4;
        --tw-ring-shadow: 0 0 #0000 !important;
    }

    .fi-badge.fi-color-gray {
        background: var(--sfk-hover-strong);
        color: var(--sfk-text);
    }

    .fi-badge.fi-color-primary,
    .fi-badge.fi-color-info {
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary-dark);
    }

    .fi-badge.fi-color-success {
        background: var(--sfk-success-soft);
        color: var(--sfk-success);
    }

    .fi-badge.fi-color-warning {
        background: var(--sfk-warning-soft);
        color: var(--sfk-warning);
    }

    .fi-badge.fi-color-danger {
        background: var(--sfk-danger-soft);
        color: var(--sfk-danger-text);
    }

    .fi-badge .fi-icon {
        color: inherit;
    }

    /* ---- Filament tabs (settings, profile, dialogs): underlined tabs like the 一覧 tabs ----------------------------- */
    .fi-tabs {
        gap: var(--sfk-space-1);
        padding: 0;
        border-radius: 0;
        background: transparent;
        box-shadow: none !important;
        border-bottom: 1px solid var(--sfk-line);
    }

    .fi-tabs-item,
    .fi-tabs-item:last-child {
        min-height: 44px;
        padding: 0 var(--sfk-space-4);
        margin-bottom: -1px;
        border: 0;
        border-bottom: 2px solid transparent;
        border-radius: 0;
        background: transparent;
        box-shadow: none;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-label);
    }

    .fi-tabs-item:hover {
        background: transparent;
        color: var(--sfk-ink);
    }

    .fi-tabs-item.fi-active {
        background: transparent;
        border-bottom-color: var(--sfk-ink);
        color: var(--sfk-ink);
        font-weight: var(--sfk-fw-semibold);
    }

    .fi-tabs-item .fi-tabs-item-label {
        color: inherit;
    }

    .fi-tabs-item .fi-badge {
        min-height: 20px;
        padding: 0 6px;
    }

    /* ---- forms: labels, help-free spacing, the error line ------------------------------------------------------ */
    .fi-fo-field-label-content {
        color: var(--kt-label);
        font-size: var(--sfk-fs-label);
        font-weight: var(--sfk-fw-medium);
        line-height: var(--sfk-lh-tight);
    }

    /* every one-line field has the control height: inputs, selects, multi-selects and the read-only values */
    .fi-input-wrp:not(:has(textarea)) {
        min-height: var(--sfk-control);
    }

    .fi-input-wrp:not(:has(textarea))>.fi-input-wrp-content-ctn {
        display: flex;
        align-items: center;
    }

    .fi-input-wrp:not(:has(textarea))>.fi-input-wrp-content-ctn>* {
        flex: 1;
        min-width: 0;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit) .fi-in-entry-content {
        min-height: var(--sfk-control) !important;
        padding: 6px 12px !important;
        border-radius: var(--sfk-radius);
        color: var(--sfk-text);
    }

    /* labels of the read-only values look like the labels of the inputs next to them */
    :is(.sfk-app-page-create, .sfk-app-page-edit, .sfk-app-page-view) .fi-in-entry-label {
        color: var(--kt-label);
        font-size: var(--sfk-fs-label);
        font-weight: var(--sfk-fw-medium);
        line-height: var(--sfk-lh-tight);
    }

    .fi-fo-field-wrp-error-message {
        border-radius: 0 0 var(--sfk-radius) var(--sfk-radius);
        background-color: var(--sfk-danger);
        font-size: var(--sfk-fs-small);
    }

    .fi-fo-checkbox-list-option-label,
    .fi-fo-radio-option-label,
    .fi-fo-toggle-label {
        color: var(--sfk-text);
    }

    /* ---- the record pages: a little more air between the groups and inside the cards ------------------------------ */
    :is(.sfk-app-page-create, .sfk-app-page-edit, .sfk-app-page-view) .fi-page-content {
        padding: var(--sfk-gutter);
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit) .fi-sc-form>.fi-sc:has(> .fi-grid-col > .fi-sc-component > .fi-sc-actions > .fi-ac) {
        margin-inline: calc(var(--sfk-gutter) * -1);
        margin-bottom: calc(var(--sfk-gutter) * -1);
        padding-inline: var(--sfk-gutter);
    }

    .kt-record-label {
        font-size: var(--sfk-fs-heading);
    }

    /* the dark header of the record tables: a little lighter, rounded with the box */
    .kt-subtable>table>thead>tr>th,
    :is(.sfk-related-table, .sfk-order-past-table) th,
    table:is(.sfk-data-table, .sfk-fax-table) th {
        letter-spacing: var(--sfk-tracking);
    }

    /* ---- the database notifications (bell) ------------------------------------------------------------------------ */
    .fi-no-database .fi-modal-heading {
        font-size: var(--sfk-fs-heading);
    }

    .fi-no-database .fi-no-notification.fi-inline {
        border-radius: var(--sfk-radius);
    }

    /* ---- widgets of the dashboards (stats, charts) -------------------------------------------------------------- */
    .fi-wi-stats-overview-stat {
        border-radius: var(--sfk-radius-lg);
        box-shadow: var(--sfk-ring), var(--sfk-shadow) !important;
    }

    .fi-wi-stats-overview-stat-label {
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-label);
    }

    .fi-wi-stats-overview-stat-value {
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-figure);
        font-weight: var(--sfk-fw-semibold);
    }

    /* ---- small screens: the gutter narrows, toolbars wrap, controls stay full-size -------------------------------- */
    @media (max-width: 767px) {
        .sfk-app-page-list .fi-page-content {
            padding: 0 var(--sfk-gutter) var(--sfk-gutter);
        }

        .sfk-list-footer {
            padding: var(--sfk-space-4) var(--sfk-gutter) var(--sfk-gutter);
        }

        .sfk-app-page-list .fi-ta-search-field {
            width: 100%;
            flex: 1 1 100%;
        }

        .sfk-list-sort {
            margin-inline-start: 0;
        }

        .sfk-app-head-actions {
            margin-inline-start: 0;
        }

        .sfk-stat {
            min-height: 0;
            padding: var(--sfk-space-4);
        }

        /* the summary cards become one row that swipes sideways, so the list stays near the top */
        .sfk-app-summary,
        .sfk-app-summary.is-compact {
            display: flex;
            gap: var(--sfk-space-3);
            margin-inline: calc(var(--sfk-gutter) * -1);
            padding: 2px var(--sfk-gutter) var(--sfk-space-2);
            overflow-x: auto;
            scroll-snap-type: x proximity;
            scrollbar-width: none;
        }

        .sfk-app-summary::-webkit-scrollbar {
            display: none;
        }

        .sfk-app-summary>.sfk-stat {
            flex: 0 0 min(240px, 78%);
            scroll-snap-align: start;
        }

        .sfk-app-summary.is-compact>.sfk-stat {
            flex-basis: 160px;
        }

        .fi-modal-window .fi-modal-header,
        .fi-modal-content {
            padding: var(--sfk-space-4);
        }

        .fi-modal-footer {
            padding: var(--sfk-space-3) var(--sfk-space-4);
        }
    }
</style>
