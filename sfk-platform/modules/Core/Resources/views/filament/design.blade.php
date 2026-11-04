{{--
    The look of the app screens since 2026-11 (design_ref/app-index.png, add-invoice.png, customer-*.png,
    profile-page.png, welcome-card.png), layered over core::filament.theme in the cached panel stylesheet
    (Modules\Core\Filament\Support\ThemeAssets): white surfaces with hairline borders and small rounded corners, the
    brand blue (BasePanelProvider::BRAND_BLUE) as the primary colour, dark text, soft grey headers.
--}}
<style>
    /* ---- tokens ------------------------------------------------------------------------------------------------ */
    :root {
        --sfk-radius: 6px;
        --sfk-radius-lg: 10px;
        --radius-sm: 4px;
        --radius-md: 6px;
        --radius-lg: 8px;
        --radius-xl: 10px;
        --radius-2xl: 12px;
        --sfk-primary: #05467E;
        --sfk-primary-dark: #033456;
        --sfk-primary-soft: #e8eff6;
        --sfk-ink: #111827;
        --sfk-text: #1f2937;
        --sfk-muted: #6b7280;
        --sfk-line: #e5e7eb;
        --sfk-line-soft: #f0f1f3;
        --sfk-surface: #ffffff;
        --sfk-page: #f6f7f9;
        --sfk-head: #f9fafb;
        --sfk-field-disabled: #f1f3f5;
        --sfk-shadow: 0 1px 2px rgba(16, 24, 40, .05);
        --sfk-shadow-lg: 0 8px 24px rgba(16, 24, 40, .10);
        /* the legacy tokens of core::filament.theme take the new look */
        --kt-border: var(--sfk-line);
        --kt-header: var(--sfk-head);
        --kt-page: var(--sfk-page);
        --kt-text: var(--sfk-text);
        --kt-muted: var(--sfk-muted);
        --kt-normal: #ffffff;
        --kt-normal-hover: #f3f4f6;
        --kt-input-shadow: 0 0 0 0 transparent;
        --kt-disabled: #eef0f3;
    }

    body.fi-body {
        background-color: var(--sfk-page);
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
        0%, 60%, 100% { transform: translateY(0); }
        30% { transform: translateY(-22px); }
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

    .sfk-app-page-list .fi-ta-content-ctn.sfk-table-loading > table > tbody {
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
        --sfk-thead: 46px;
    }

    .sfk-app-page-list .fi-ta-table-loading-ctn > :not(.fi-sr-only) {
        display: none;
    }

    .sfk-app-page-list .fi-ta-table-loading-ctn::before {
        content: "";
        position: absolute;
        inset: 0 0 auto;
        height: 46px;
        background: var(--sfk-head);
        border-bottom: 1px solid var(--sfk-line);
    }

    @keyframes sfk-skeleton {
        from { background-position: 150% 0, 0 0; }
        to { background-position: -50% 0, 0 0; }
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
    }

    .fi-layout-sidebar-toggle-btn-ctn .fi-icon-btn {
        width: 48px;
        height: 48px;
        border-radius: 9999px;
        background: var(--sfk-primary);
        box-shadow: 0 6px 18px rgba(5, 70, 126, .45);
        color: #ffffff;
    }

    .fi-layout-sidebar-toggle-btn-ctn .fi-icon-btn:hover {
        background: var(--sfk-primary-dark);
        color: #ffffff;
    }

    .fi-layout-sidebar-toggle-btn-ctn .fi-icon-btn .fi-icon {
        color: #ffffff;
    }

    /* ---- buttons ----------------------------------------------------------------------------------------------- */
    .fi-btn {
        border-radius: var(--sfk-radius) !important;
        font-weight: 600;
    }

    .fi-btn:not(.fi-outlined):not([class*="fi-bg-color-"]),
    .fi-btn.fi-color-gray:not(.fi-outlined) {
        background-color: #ffffff !important;
        color: var(--sfk-text) !important;
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow) !important;
        --text: var(--sfk-text);
        --hover-text: var(--sfk-ink);
    }

    .fi-btn:not(.fi-outlined):not([class*="fi-bg-color-"]):hover,
    .fi-btn.fi-color-gray:not(.fi-outlined):hover {
        background-color: #f9fafb !important;
    }

    .fi-btn:not(.fi-outlined):not([class*="fi-bg-color-"]) .fi-icon,
    .fi-btn.fi-color-gray:not(.fi-outlined) .fi-icon {
        color: var(--sfk-muted) !important;
    }

    .fi-btn.fi-color-primary[class*="fi-bg-color-"],
    .fi-btn.fi-color-danger[class*="fi-bg-color-"] {
        box-shadow: var(--sfk-shadow) !important;
    }

    .fi-btn.fi-disabled,
    .fi-btn[disabled] {
        background-color: var(--sfk-field-disabled) !important;
    }

    .fi-icon-btn {
        border-radius: var(--sfk-radius);
    }

    .fi-btn:focus-visible,
    .fi-icon-btn:focus-visible {
        outline: 2px solid var(--sfk-primary);
        outline-offset: 2px;
    }

    /* ---- inputs ------------------------------------------------------------------------------------------------ */
    .fi-input-wrp {
        border-radius: var(--sfk-radius) !important;
        box-shadow: 0 0 0 1px #dfe3e8 !important;
    }

    .fi-input-wrp:not(.fi-disabled):focus-within {
        box-shadow: 0 0 0 1px var(--sfk-primary), 0 0 0 4px var(--sfk-primary-soft) !important;
    }

    .fi-input-wrp.fi-invalid {
        box-shadow: 0 0 0 1px var(--kt-red), 0 0 0 4px #fdecea !important;
    }

    .fi-input-wrp.fi-disabled {
        background-color: var(--sfk-field-disabled);
        box-shadow: 0 0 0 1px var(--sfk-line) !important;
    }

    .fi-input {
        min-height: 2.5rem;
    }

    .fi-checkbox-input {
        border-radius: 4px;
    }

    .fi-radio-input,
    input[type="radio"] {
        border-radius: 9999px;
    }

    .fi-dropdown-panel {
        border-radius: var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow-lg) !important;
        overflow: hidden;
    }

    .fi-modal-window {
        border-radius: 12px;
        overflow: hidden;
    }

    .fi-section:not(.fi-section-not-contained):not(.fi-aside) {
        border-radius: var(--sfk-radius-lg);
    }

    /* ---- the app frame ----------------------------------------------------------------------------------------- */
    .sfk-app-page {
        background: var(--sfk-surface);
    }

    .sfk-app-header {
        display: flex;
        flex-direction: column;
        gap: 16px;
        padding: 20px 24px 0;
        background: var(--sfk-surface);
    }

    .sfk-app-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px 16px;
        min-height: 40px;
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
        font-size: 1.375rem;
        font-weight: 700;
        line-height: 1.3;
        color: var(--sfk-ink);
    }

    .sfk-app-title a {
        color: inherit;
    }

    .sfk-app-title a:hover {
        color: var(--sfk-primary-dark);
    }

    .sfk-app-subtitle {
        font-size: 1rem;
        color: var(--sfk-muted);
        white-space: nowrap;
    }

    .sfk-app-home,
    .sfk-app-back {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: var(--sfk-radius);
        color: var(--sfk-muted);
    }

    .sfk-app-home:hover,
    .sfk-app-back:hover {
        background: #f3f4f6;
        color: var(--sfk-ink);
    }

    .sfk-app-home-icon,
    .sfk-app-back-icon {
        width: 20px;
        height: 20px;
    }

    .sfk-app-crumb-sep {
        width: 16px;
        height: 16px;
        color: #9ca3af;
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
        font-size: .9375rem;
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
        background: #f3f4f6;
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
        min-height: 40px;
        font-size: .9375rem;
    }

    .sfk-app-head-actions .fi-icon-btn,
    .sfk-app-tool {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: var(--sfk-radius);
        background: #ffffff;
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow);
        color: var(--sfk-muted);
    }

    .sfk-app-head-actions .fi-icon-btn:hover,
    .sfk-app-tool:hover {
        background: #f9fafb;
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
        height: 40px;
        padding: 0 16px;
        border-radius: var(--sfk-radius);
        background: var(--sfk-primary);
        box-shadow: var(--sfk-shadow);
        color: #ffffff;
        font-size: .9375rem;
        font-weight: 600;
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
        height: 40px;
        padding: 0 14px;
        border-radius: var(--sfk-radius);
        background: #ffffff;
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow);
        color: var(--sfk-text);
        font-size: .9375rem;
        font-weight: 600;
    }

    .sfk-export-btn:hover {
        background: #f9fafb;
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
        font-size: .9375rem;
        text-align: start;
        white-space: nowrap;
    }

    .sfk-export-item:hover,
    .sfk-export-group:hover > .sfk-export-item {
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
        color: #9ca3af;
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
        height: 40px;
        border-radius: var(--sfk-radius);
        color: var(--sfk-text);
    }

    .sfk-app-pager-btn:hover:not(.is-disabled) {
        background: #f3f4f6;
    }

    .sfk-app-pager-btn.is-disabled {
        color: #d1d5db;
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

    /* ---- summary cards ----------------------------------------------------------------------------------------- */
    .sfk-app-summary {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 16px;
    }

    .sfk-stat {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-height: 96px;
        padding: 18px 20px;
        overflow: hidden;
        background: #ffffff;
        border-radius: var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow);
    }

    .sfk-stat::after {
        content: "";
        position: absolute;
        right: -24px;
        bottom: -40px;
        width: 120px;
        height: 80px;
        background: var(--sfk-tone-soft);
        transform: rotate(-30deg);
        opacity: .7;
        pointer-events: none;
    }

    .sfk-stat-text {
        position: relative;
        z-index: 1;
        min-width: 0;
    }

    .sfk-stat-label {
        font-size: .9375rem;
        color: var(--sfk-muted);
    }

    .sfk-stat-value {
        margin-top: 4px;
        font-size: 1.375rem;
        font-weight: 700;
        color: var(--sfk-ink);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sfk-stat-icon {
        position: relative;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: none;
        width: 44px;
        height: 44px;
        border-radius: 9999px;
        background: var(--sfk-tone);
        color: #ffffff;
    }

    .sfk-stat-icon-svg {
        width: 22px;
        height: 22px;
    }

    /* many cards (e.g. 04 / 06): smaller, so they do not push the list down */
    .sfk-app-summary.is-compact {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 12px;
    }

    .sfk-app-summary.is-compact .sfk-stat {
        min-height: 72px;
        padding: 12px 16px;
    }

    .sfk-app-summary.is-compact .sfk-stat-value {
        font-size: 1.125rem;
    }

    .sfk-app-summary.is-compact .sfk-stat-label {
        font-size: .875rem;
    }

    .sfk-app-summary.is-compact .sfk-stat-icon {
        width: 36px;
        height: 36px;
    }

    .sfk-app-summary.is-compact .sfk-stat-icon-svg {
        width: 18px;
        height: 18px;
    }

    .sfk-tone-blue { --sfk-tone: #05467E; --sfk-tone-soft: #e6eef5; }
    .sfk-tone-green { --sfk-tone: #22a565; --sfk-tone-soft: #e3f6ec; }
    .sfk-tone-amber { --sfk-tone: #e3a008; --sfk-tone-soft: #fdf3d8; }
    .sfk-tone-red { --sfk-tone: #e5484d; --sfk-tone-soft: #fde7e8; }
    .sfk-tone-purple { --sfk-tone: #7c5cdb; --sfk-tone-soft: #eee9fb; }
    .sfk-tone-slate { --sfk-tone: #64748b; --sfk-tone-soft: #eef1f5; }

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
        font-size: .9375rem;
        white-space: nowrap;
    }

    .sfk-app-tab:hover {
        color: var(--sfk-ink);
    }

    .sfk-app-tab.is-active {
        border-bottom-color: var(--sfk-ink);
        color: var(--sfk-ink);
        font-weight: 600;
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
        min-height: 40px;
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
        height: 40px;
        padding: 0 12px;
        border-radius: var(--sfk-radius);
        background: #ffffff;
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow);
        color: var(--sfk-text);
        font-size: .9375rem;
        white-space: nowrap;
    }

    .sfk-list-tool:hover {
        background: #f9fafb;
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
        width: 40px;
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
        font-weight: 600;
    }

    .sfk-list-sort-dir {
        padding: 1px 6px;
        border-radius: 4px;
        background: #f3f4f6;
        color: var(--sfk-muted);
        font-size: .8125rem;
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
        font-size: .875rem;
        color: var(--sfk-text);
    }

    .sfk-list-sort-dir-btn.is-active {
        background: var(--sfk-primary-soft);
        box-shadow: 0 0 0 1px var(--sfk-primary);
        color: var(--sfk-primary-dark);
        font-weight: 600;
    }

    .sfk-app-page-list .fi-ta-header-toolbar .fi-ta-col-manager-trigger,
    .sfk-app-page-list .fi-ta-header-toolbar .fi-dropdown-trigger .fi-btn {
        min-height: 40px;
    }

    .sfk-app-page-list .fi-ta-header-toolbar .fi-btn {
        height: 40px;
        font-weight: 400;
        font-size: .9375rem;
    }

    .sfk-app-settings-btn.fi-icon-btn,
    .sfk-app-page-list .fi-ta-header-toolbar .sfk-app-settings-btn {
        width: 40px;
        height: 40px;
        background: #ffffff;
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow);
        color: var(--sfk-muted);
    }

    .sfk-app-settings-btn.fi-icon-btn:hover {
        background: #f9fafb;
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
        background: #f9fafb !important;
        box-shadow: 0 0 0 1px #cfd4da, var(--sfk-shadow) !important;
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
        font-weight: 700;
        color: var(--sfk-ink);
    }

    .sfk-app-help-close {
        display: inline-flex;
        padding: 4px;
        border-radius: var(--sfk-radius);
        color: var(--sfk-muted);
    }

    .sfk-app-help-close:hover {
        background: #f3f4f6;
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
        font-size: .9375rem;
    }

    .sfk-app-help-item:last-child {
        border-bottom: 0;
    }

    .sfk-app-help-item dt {
        font-weight: 600;
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
    .sfk-app-page-list .fi-page-content > .fi-sc,
    .sfk-app-page-list .fi-page-content > .fi-sc > .fi-grid-col:has(.fi-ta),
    .sfk-app-page-list .fi-page-content > .fi-sc > .fi-grid-col > .fi-sc-component:has(> .fi-ta),
    .sfk-app-page-list .fi-ta:has(> .fi-ta-ctn),
    .sfk-app-page-list .fi-ta-ctn,
    .sfk-app-page-list .fi-ta-main {
        display: flex;
        flex: 1;
        flex-direction: column;
        min-height: 0;
    }

    .sfk-app-page-list .fi-page-content > .fi-sc {
        gap: 0;
    }

    .sfk-app-page-list .fi-ta-content-ctn {
        flex: 1;
        min-height: 240px;
    }

    /* no rows: the header row, then the message filling the rest of the same box */
    .sfk-app-page-list .fi-ta-main:has(> .fi-ta-empty-state) > .fi-ta-content-ctn {
        flex: none;
        min-height: 0;
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
    }

    .sfk-app-page-list .fi-ta-main > .fi-ta-empty-state {
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
        background-color: var(--sfk-head);
    }

    .sfk-app-page-list .fi-ta-header-cell {
        padding-block: 12px;
        border-bottom: 1px solid var(--sfk-line);
        color: var(--sfk-ink);
        font-size: .9375rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .sfk-app-page-list .fi-ta-table>thead>tr>th {
        border-inline-end: 0;
    }

    .sfk-app-page-list .fi-ta-table>tbody>tr>td {
        border-bottom: 1px solid var(--sfk-line-soft);
    }

    .sfk-app-page-list .fi-ta-row:hover>td {
        background-color: #f9fafb;
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

    .sfk-app-page-list .fi-ta-table>tbody ul.fi-ta-text > li {
        display: inline;
    }

    .sfk-app-page-list .fi-ta-table>tbody ul.fi-ta-text > li + li::before {
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
        background: #f3f4f6;
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
        font-size: .9375rem;
        color: var(--sfk-text);
    }

    .sfk-list-size-select {
        height: 36px;
        padding: 0 32px 0 12px;
        border: 1px solid var(--sfk-line);
        border-radius: var(--sfk-radius);
        background-color: #ffffff;
        font-size: .9375rem;
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
        font-size: .9375rem;
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
        font-size: .9375rem;
        font-variant-numeric: tabular-nums;
    }

    .sfk-page-btn:hover:not(:disabled) {
        background: #f9fafb;
    }

    .sfk-page-btn.is-current {
        box-shadow: 0 0 0 1px var(--sfk-primary);
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary-dark);
        font-weight: 600;
    }

    .sfk-page-btn:disabled {
        color: #d1d5db;
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
        font-size: 1.1875rem;
        font-weight: 700;
        color: var(--sfk-ink);
    }

    /* group headings (RecordSections string rows): a bold title with a rule above it, like 「Bill From」 / 「Items & Details」 */
    .kt-record-label {
        margin-top: 12px;
        padding: 20px 0 0;
        border-top: 1px solid var(--sfk-line);
        border-bottom: 0;
        color: var(--sfk-ink);
        font-size: 1.125rem;
        font-weight: 700;
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
        font-size: .9375rem;
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
        font-size: .9375rem;
    }

    .kt-record-tab:hover {
        background: transparent;
        color: var(--sfk-ink);
    }

    .kt-record-tab.kt-active {
        background: transparent;
        border-bottom-color: var(--sfk-ink);
        color: var(--sfk-ink);
        font-weight: 600;
    }

    /* tables of a record (サブテーブル): a dark header row, rounded box */
    .kt-subtable {
        border-radius: var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line);
        overflow: auto;
    }

    .kt-subtable>table>thead>tr>th {
        height: 40px;
        padding: 6px 10px;
        background-color: #1f2328;
        color: #ffffff;
        font-size: .875rem;
        font-weight: 600;
    }

    .kt-subtable>table>thead>tr>th.fi-fo-table-repeater-empty-header-cell {
        background: #1f2328;
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
        font-size: .9375rem;
    }

    :is(.sfk-related-table, .sfk-order-past-table) th,
    table:is(.sfk-data-table, .sfk-fax-table) th {
        height: 40px;
        padding: 6px 12px !important;
        background: #1f2328 !important;
        border: 0 !important;
        color: #ffffff !important;
        font-size: .875rem;
        font-weight: 600;
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
        background: #f9fafb;
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
        font-size: 1rem;
        font-weight: 700;
    }

    /* 過去の受注履歴検索: a card with a plain heading, the search row on a soft ground */
    .sfk-order-past {
        overflow: hidden;
        border: 0 !important;
        border-radius: var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow);
    }

    .sfk-order-past-head {
        padding: 14px 18px !important;
        background: #ffffff !important;
        border-bottom: 1px solid var(--sfk-line);
    }

    .sfk-order-past-title {
        color: var(--sfk-ink) !important;
        font-size: 1.0625rem !important;
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
        background: #1f2328 !important;
        border-color: #1f2328 !important;
        color: #ffffff !important;
    }

    .sfk-order-past-search .sfk-filter-btn.sfk-filter-btn-submit:hover {
        background: #000000 !important;
    }

    .sfk-order-past-search .sfk-filter-btn:not(.sfk-filter-btn-submit) {
        color: #1f2328 !important;
    }

    .sfk-order-past-page {
        border-radius: var(--sfk-radius);
    }

    .sfk-order-past .sfk-order-past-table {
        margin: 0 16px 16px;
    }

    /* the footer of a form: キャンセル on the left, 保存 on the right, kept at the bottom of the screen (sticky in the
       page instead of Filament's fixed bar, so it stays inside the content next to the sidebar) */
    :is(.sfk-app-page-create, .sfk-app-page-edit) .fi-sc-form > .fi-sc:has(> .fi-grid-col > .fi-sc-component > .fi-sc-actions > .fi-ac) {
        position: sticky;
        bottom: 0;
        z-index: 25;
        margin: 8px -24px -24px;
        padding: 14px 24px;
        background: #ffffff;
        border-top: 1px solid var(--sfk-line);
        box-shadow: 0 -4px 12px rgba(16, 24, 40, .06);
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit) .fi-sc-actions > .fi-ac {
        position: static !important;
        justify-content: space-between;
        width: 100%;
        padding: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        transform: none !important;
    }

    :is(.sfk-app-page-create, .sfk-app-page-edit) .fi-sc-actions > .fi-ac .fi-btn {
        min-width: 120px;
        min-height: 42px;
        font-size: 1rem;
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
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow);
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
        font-size: 2.25rem;
        font-weight: 700;
    }

    .sfk-profile-code {
        color: var(--sfk-primary);
        font-size: .9375rem;
    }

    .sfk-profile-name {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-top: 2px;
        font-size: 1.375rem;
        font-weight: 700;
        color: var(--sfk-ink);
    }

    .sfk-profile-badge {
        padding: 2px 8px;
        border-radius: 9999px;
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary-dark);
        font-size: .8125rem;
        font-weight: 600;
    }

    .sfk-profile-meta {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 6px;
        font-size: .9375rem;
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
        font-size: .9375rem;
        font-weight: 600;
        color: var(--sfk-ink);
    }

    .sfk-profile-info-icon {
        width: 16px;
        height: 16px;
        color: var(--sfk-muted);
    }

    .sfk-profile-info dd {
        margin: 4px 0 0;
        font-size: .9375rem;
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
        font-size: 1.375rem;
        font-weight: 700;
        color: var(--sfk-ink);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sfk-profile-stat-label {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 2px;
        font-size: .9375rem;
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
        padding: 0 8px;
        background: #ffffff;
        border-radius: var(--sfk-radius-lg);
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow);
    }

    .sfk-profile-tab {
        display: inline-flex;
        flex: none;
        align-items: center;
        gap: 8px;
        padding: 14px 16px;
        border-bottom: 2px solid transparent;
        color: var(--sfk-text);
        font-size: .9375rem;
        white-space: nowrap;
    }

    .sfk-profile-tab:hover {
        color: var(--sfk-primary-dark);
    }

    .sfk-profile-tab.is-active {
        border-bottom-color: var(--sfk-primary);
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary-dark);
        font-weight: 600;
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
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow);
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
        font-size: .9375rem;
        color: var(--sfk-muted);
    }

    .sfk-record-tab-stat-value {
        margin-top: 4px;
        font-size: 1.1875rem;
        font-weight: 700;
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
        height: 40px;
        padding: 0 12px 0 36px;
        border: 1px solid #dfe3e8;
        border-radius: var(--sfk-radius);
        font-size: .9375rem;
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
        font-size: .9375rem;
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
        font-size: .9375rem;
    }

    .sfk-record-tab-table th {
        padding: 0;
        background: var(--sfk-head);
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
        font-weight: 600;
    }

    .sfk-record-tab-sort {
        width: 14px;
        height: 14px;
        color: #9ca3af;
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
        background: #f9fafb;
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
        font-size: .8125rem;
        font-weight: 600;
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
        box-shadow: 0 0 0 1px var(--sfk-line), var(--sfk-shadow);
        overflow: hidden;
    }

    .sfk-profile-nav-title {
        margin: 0;
        padding: 16px 20px;
        border-bottom: 1px solid var(--sfk-line);
        font-size: 1.25rem;
        font-weight: 700;
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
        font-size: .9375rem;
        text-align: start;
    }

    .sfk-profile-nav-item:hover {
        background: #f9fafb;
    }

    .sfk-profile-nav-item.is-active {
        background: var(--sfk-primary-soft);
        box-shadow: inset 3px 0 0 var(--sfk-primary);
        color: var(--sfk-primary-dark);
        font-weight: 600;
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
        color: #9ca3af;
    }

    .sfk-profile-title {
        margin: 0 0 16px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--sfk-line);
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--sfk-ink);
    }

    /* each part of a setting: no box, a dark icon tile before its title, a rule between the parts */
    .sfk-profile-main .fi-section:not(.fi-section-not-contained) {
        background: transparent;
        box-shadow: none !important;
        border-radius: 0;
    }

    .sfk-profile-main .fi-section + .fi-section,
    .sfk-profile-main .fi-grid-col + .fi-grid-col .fi-section {
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

    .sfk-profile-main .fi-section-header > .fi-icon {
        width: 32px;
        height: 32px;
        padding: 7px;
        border-radius: var(--sfk-radius);
        background: var(--sfk-ink);
        color: #ffffff;
    }

    .sfk-profile-main .fi-section-header-heading {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--sfk-ink);
    }

    .sfk-profile-main .fi-section-content-ctn > .fi-section-content {
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
</style>
