{{--
    SFK UI: the component layer of the system (included at the end of core::filament.theme). Our own implementation,
    written for Filament's fields so Livewire binding, validation and forms keep working as they are; its design
    language (palette, sizes, states) is derived from the MIT-licensed UI component library of Cybozu, Inc. (KUC) —
    see THIRD_PARTY_NOTICES.md. No code of the library is included. Toasts keep their own design (theme, 「Toasts」).

    One section per component; colours come from the theme tokens (--kt-*) plus the few below. Square corners
    everywhere (theme rule); radio buttons and toasts stay round. Our own native inputs take the same look with the
    classes sfk-ui-check / sfk-ui-radio (the login page includes this layer too).
--}}
<style>
    :root {
        --sfk-ui-frame: #d4d7d7;
        /* unchecked frame of a check box */
        --sfk-ui-frame-hover: #a8b0b3;
        --sfk-ui-switch-off: #b5b5b5;
        --sfk-ui-tooltip: #1f2326;
        --sfk-ui-zone: #f7f9fa;
        --sfk-ui-file: #f2f4f6;
        --sfk-ui-chip: #e6eef5;
        --sfk-ui-head-rule: #033456;
        --sfk-ui-check: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath d='M5 10.2l3.2 3.1L15 6.4' fill='none' stroke='%233498db' stroke-width='2.4'/%3E%3C/svg%3E");
        --sfk-ui-check-off: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath d='M5 10.2l3.2 3.1L15 6.4' fill='none' stroke='%23b8bcbd' stroke-width='2.4'/%3E%3C/svg%3E");
        --sfk-ui-dash: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath d='M5 10h10' fill='none' stroke='%233498db' stroke-width='2.4'/%3E%3C/svg%3E");
    }

    /* ── Check box: white box with a grey frame; checked = blue frame and a blue tick (not a filled box) ── */
    :is(input[type='checkbox'].fi-checkbox-input, input[type='checkbox'].sfk-ui-check) {
        width: 20px;
        height: 20px;
        flex: none;
        appearance: none;
        background-color: #ffffff;
        background-position: center;
        background-repeat: no-repeat;
        background-size: 20px 20px;
        border: 2px solid var(--sfk-ui-frame);
        box-shadow: 1px 1px 3px #f5f5f5 inset, -1px -1px 3px #f5f5f5 inset;
        --tw-ring-shadow: 0 0 #0000;
        --tw-ring-offset-shadow: 0 0 #0000;
        cursor: pointer;
        transition: border-color .12s;
    }

    :is(input[type='checkbox'].fi-checkbox-input, input[type='checkbox'].sfk-ui-check):hover:not(:disabled) {
        border-color: var(--sfk-ui-frame-hover);
    }

    :is(input[type='checkbox'].fi-checkbox-input, input[type='checkbox'].sfk-ui-check):checked {
        background-color: #ffffff;
        background-image: var(--sfk-ui-check);
        border-color: var(--kt-blue);
    }

    :is(input[type='checkbox'].fi-checkbox-input, input[type='checkbox'].sfk-ui-check):indeterminate {
        background-color: #ffffff;
        background-image: var(--sfk-ui-dash);
        border-color: var(--kt-blue);
    }

    :is(input[type='checkbox'].fi-checkbox-input, input[type='checkbox'].sfk-ui-check):focus-visible {
        outline: 1px solid var(--kt-blue);
        outline-offset: 2px;
    }

    :is(input[type='checkbox'].fi-checkbox-input, input[type='checkbox'].sfk-ui-check):disabled {
        background-color: #f5f5f5;
        border-color: var(--sfk-ui-frame);
    }

    :is(input[type='checkbox'].fi-checkbox-input, input[type='checkbox'].sfk-ui-check):disabled:checked {
        background-color: #f5f5f5;
        background-image: var(--sfk-ui-check-off);
    }

    :is(input[type='checkbox'].fi-checkbox-input, input[type='checkbox'].sfk-ui-check).fi-invalid {
        border-color: var(--kt-red);
    }

    /* ── Radio button: white circle with a hairline frame; chosen = blue frame and a blue dot ── */
    :is(input[type='radio'].fi-radio-input, input[type='radio'].sfk-ui-radio) {
        width: 20px;
        height: 20px;
        flex: none;
        appearance: none;
        background-color: #ffffff;
        background-image: none;
        border: 1px solid var(--kt-tab-border, #c7cbcb);
        box-shadow: 1px 1px 3px #f5f5f5 inset, -1px -1px 3px #f5f5f5 inset;
        --tw-ring-shadow: 0 0 #0000;
        --tw-ring-offset-shadow: 0 0 #0000;
        cursor: pointer;
        transition: border-color .12s;
    }

    :is(input[type='radio'].fi-radio-input, input[type='radio'].sfk-ui-radio):hover:not(:disabled) {
        border-color: var(--sfk-ui-frame-hover);
    }

    :is(input[type='radio'].fi-radio-input, input[type='radio'].sfk-ui-radio):checked {
        background-color: #ffffff;
        background-image: radial-gradient(circle, var(--kt-blue) 0 5.5px, transparent 6px);
        border-color: var(--kt-blue);
    }

    :is(input[type='radio'].fi-radio-input, input[type='radio'].sfk-ui-radio):focus-visible {
        outline: 1px solid var(--kt-blue);
        outline-offset: 2px;
    }

    :is(input[type='radio'].fi-radio-input, input[type='radio'].sfk-ui-radio):disabled {
        background-color: #f5f5f5;
    }

    :is(input[type='radio'].fi-radio-input, input[type='radio'].sfk-ui-radio):disabled:checked {
        background-image: radial-gradient(circle, var(--kt-border) 0 5.5px, transparent 6px);
        border-color: var(--sfk-ui-frame);
    }

    /* ── Option lists (CheckboxList, Radio): roomy rows, grey text on hover, a frame around the focused option ── */
    /* only the box and its text are clickable: the label is as wide as its content, not the whole grid column */
    .fi-fo-checkbox-list-option,
    .fi-fo-radio-label {
        display: inline-flex;
        width: fit-content;
        max-width: 100%;
        justify-self: start;
        align-self: start;
        gap: 10px;
        padding: 4px 6px;
        border: 1px solid transparent;
        cursor: pointer;
    }

    /* our own check boxes / radio buttons (設定, 絞り込む, login): their labels too */
    label:has(> input.sfk-ui-check),
    label:has(> input.sfk-ui-radio) {
        width: fit-content;
        max-width: 100%;
        align-self: flex-start;
        justify-self: start;
        cursor: pointer;
    }

    /* a single check box (Checkbox field): its label too */
    label.fi-fo-field-label:has(> input[type='checkbox']),
    label.fi-fo-field-label:has(> .fi-checkbox-input) {
        display: inline-flex;
        width: fit-content;
        max-width: 100%;
        cursor: pointer;
    }

    /* ── 文字色 palette of the rich text editors (core::filament.text-color-palette) ── */
    .sfk-text-palette {
        position: fixed;
        z-index: 1000;
        width: 254px;
        padding: 10px;
        background: #ffffff;
        border: 1px solid var(--kt-border);
        box-shadow: 0 4px 14px rgba(0, 0, 0, .18);
    }

    .sfk-text-palette-grid {
        display: grid;
        grid-template-columns: repeat(7, 28px);
        gap: 6px;
    }

    .sfk-text-palette-swatch {
        width: 28px;
        height: 28px;
        border: 1px solid rgba(0, 0, 0, .15);
        cursor: pointer;
    }

    .sfk-text-palette-swatch:hover,
    .sfk-text-palette-swatch:focus-visible {
        outline: 2px solid var(--kt-blue);
        outline-offset: 1px;
    }

    .sfk-text-palette-clear {
        margin-top: 8px;
    }

    /* the labelled tools of the editors (表を削除) */
    .fi-fo-rich-editor-tool-with-label {
        gap: 4px;
        width: auto;
        padding-inline: 6px;
        font-size: .8125rem;
    }

    /* a radio group without inline(): one option per line (the labels above flow side by side in its block) */
    .fi-fo-radio.fi-grid-direction-col:not(.fi-inline) > .fi-fo-radio-label {
        display: flex;
    }

    .fi-fo-checkbox-list-option:has(input:focus-visible),
    .fi-fo-radio-label:has(input:focus-visible) {
        border-color: var(--kt-blue);
    }

    .fi-fo-checkbox-list-option:has(input:focus-visible) input,
    .fi-fo-radio-label:has(input:focus-visible) input {
        outline: 0;
    }

    .fi-fo-checkbox-list-option:hover .fi-fo-checkbox-list-option-label,
    .fi-fo-radio-label:hover .fi-fo-radio-label-text {
        color: #666666;
    }

    .fi-fo-checkbox-list-option:has(input:disabled),
    .fi-fo-radio-label:has(input:disabled) {
        cursor: not-allowed;
    }

    .fi-fo-checkbox-list-option:has(input:disabled) .fi-fo-checkbox-list-option-label,
    .fi-fo-radio-label:has(input:disabled) .fi-fo-radio-label-text {
        color: var(--kt-muted);
    }

    /* ── Switch (Toggle): grey track, blue when on, a white knob; square like the rest of the system ── */
    .fi-toggle {
        width: 52px;
        height: 28px;
        padding: 4px;
        border: 0;
        background-color: var(--sfk-ui-switch-off) !important;
        --tw-ring-shadow: 0 0 #0000;
        transition: background-color .2s ease-in-out;
    }

    .fi-toggle.fi-toggle-on {
        background-color: var(--kt-blue) !important;
    }

    .fi-toggle>:first-child {
        width: 20px;
        height: 20px;
        background: #ffffff;
        box-shadow: 0 1px 2px rgba(0, 0, 0, .2);
    }

    .fi-toggle.fi-toggle-on>:first-child {
        translate: 24px 0;
        transform: none;
    }

    .fi-toggle .fi-icon {
        display: none;
    }

    .fi-toggle:focus-visible {
        outline: 1px solid var(--kt-blue);
        outline-offset: 1px;
    }

    .fi-toggle:disabled,
    .fi-toggle[disabled] {
        opacity: .5;
        cursor: not-allowed;
    }

    /* ── Tooltip — one look on every screen (user 2026-11-14): Filament's tooltips (`->tooltip()`, its light / dark
       themes), the information tooltips of Modules\Core\Filament\Support\Tip (window.sfkTip) and the browser `title`s
       (shown through window.sfkTipTitle, below) are all a light card with a hairline border, a soft shadow and an
       arrow, fading in with a 3px rise. The chart tooltips draw the same card (sfk-tip-panel). `[data-state]` is on
       every tippy box: it outranks Filament's theme rules. ── */
    .tippy-box[data-state],
    .sfk-tip-panel {
        --tippy-background-color: var(--sfk-surface, #fff);
        --tippy-box-shadow: 0 8px 24px rgba(16, 24, 40, .12), 0 2px 6px rgba(16, 24, 40, .06);
        border: 1px solid var(--sfk-line, #e3e6ea);
        border-radius: var(--sfk-radius, 6px);
        background: var(--sfk-surface, #fff);
        box-shadow: 0 8px 24px rgba(16, 24, 40, .12), 0 2px 6px rgba(16, 24, 40, .06);
        color: var(--sfk-ink, #1f2326);
        font-size: var(--sfk-fs-small, 13px);
        line-height: 1.55;
        text-align: left;
        white-space: normal;
    }

    .tippy-box[data-state] {
        max-width: min(320px, calc(100vw - 16px)) !important;
    }

    .tippy-box[data-theme~='sfk-tip'] {
        pointer-events: none;
    }

    .tippy-box[data-state]>.tippy-content {
        padding: 7px 10px;
    }

    .tippy-box[data-state]>.tippy-content:has(> .sfk-tip-title, > .sfk-tip > .sfk-tip-title) {
        padding: 9px 12px;
    }

    .tippy-box[data-animation='sfk-tip'] {
        transition-property: opacity, transform;
        transition-timing-function: ease-out;
    }

    .tippy-box[data-animation='sfk-tip'][data-state='hidden'] {
        opacity: 0;
    }

    .tippy-box[data-animation='sfk-tip'][data-placement^='top'][data-state='hidden'] {
        transform: translateY(3px);
    }

    .tippy-box[data-animation='sfk-tip'][data-placement^='bottom'][data-state='hidden'] {
        transform: translateY(-3px);
    }

    /* the arrow: a small square turned on its corner, the border on its two outer sides */
    .tippy-box[data-state]>.tippy-arrow {
        width: 12px;
        height: 12px;
        color: var(--sfk-surface, #fff);
    }

    .tippy-box[data-state]>.tippy-arrow::before {
        width: 9px;
        height: 9px;
        border: 0 !important;
        background: var(--sfk-surface, #fff);
        transform: rotate(45deg) !important;
        transform-origin: center !important;
    }

    .tippy-box[data-state][data-placement^='top']>.tippy-arrow::before {
        top: auto !important;
        bottom: -4px !important;
        left: 1.5px !important;
        box-shadow: 1px 1px 0 var(--sfk-line, #e3e6ea);
    }

    .tippy-box[data-state][data-placement^='bottom']>.tippy-arrow::before {
        top: -4px !important;
        bottom: auto !important;
        left: 1.5px !important;
        box-shadow: -1px -1px 0 var(--sfk-line, #e3e6ea);
    }

    .tippy-box[data-state][data-placement^='left']>.tippy-arrow::before {
        top: 1.5px !important;
        right: -4px !important;
        left: auto !important;
        box-shadow: 1px -1px 0 var(--sfk-line, #e3e6ea);
    }

    .tippy-box[data-state][data-placement^='right']>.tippy-arrow::before {
        top: 1.5px !important;
        left: -4px !important;
        right: auto !important;
        box-shadow: -1px 1px 0 var(--sfk-line, #e3e6ea);
    }

    .sfk-tip-title {
        margin: 0 0 4px;
        color: var(--sfk-ink, #1f2326);
        font-size: var(--sfk-fs-small, 13px);
        font-weight: var(--sfk-fw-bold, 700);
    }

    .sfk-tip-text {
        margin: 0;
        color: var(--sfk-text, #3c4249);
    }

    .sfk-tip-text+.sfk-tip-text {
        margin-top: 2px;
    }

    .sfk-tip-rows {
        display: grid;
        gap: 3px;
        margin: 6px 0 0;
        font-variant-numeric: tabular-nums;
    }

    .sfk-tip-title+.sfk-tip-rows {
        margin-top: 2px;
    }

    .sfk-tip-rows>div {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 16px;
    }

    .sfk-tip-rows dt {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
        color: var(--sfk-muted, #6b7280);
    }

    .sfk-tip-rows dd {
        margin: 0;
        color: var(--sfk-ink, #1f2326);
        font-weight: var(--sfk-fw-semibold, 600);
        white-space: nowrap;
    }

    .sfk-tip-rows>div.is-strong dt {
        color: var(--sfk-ink, #1f2326);
    }

    .sfk-tip-rows dd.is-up {
        color: var(--sfk-success, #12805c);
    }

    .sfk-tip-rows dd.is-down {
        color: var(--sfk-danger-text, #c0322a);
    }

    .sfk-tip-rows dd.is-flat {
        color: var(--sfk-text, #3c4249);
    }

    .sfk-tip-dot {
        width: 8px;
        height: 8px;
        flex: none;
        border-radius: 9999px;
    }

    .sfk-tip-divider {
        height: 0;
        margin: 3px 0;
        border-top: 1px solid var(--sfk-line-soft, #eef0f2);
    }

    .sfk-tip-formula {
        margin: 6px 0 0;
        padding: 6px 8px;
        border-radius: 4px;
        background: var(--sfk-hover, #f5f6f8);
        color: var(--sfk-text, #3c4249);
        font-size: var(--sfk-fs-badge, 12px);
    }

    .sfk-tip-formula span {
        display: block;
        color: var(--sfk-muted, #6b7280);
        font-weight: var(--sfk-fw-semibold, 600);
    }

    .sfk-tip-note {
        margin: 6px 0 0;
        color: var(--sfk-muted, #6b7280);
        font-size: var(--sfk-fs-badge, 12px);
    }

    /* a term that explains itself on hover / focus / tap: a dotted underline, no icon */
    .sfk-tip-term {
        border-radius: 2px;
        text-decoration: underline dotted;
        text-decoration-color: color-mix(in srgb, currentColor 45%, transparent);
        text-underline-offset: 3px;
        cursor: help;
    }

    .sfk-tip-term:focus-visible,
    [data-sfk-tip]:focus-visible {
        outline: 2px solid var(--sfk-primary, #05467e);
        outline-offset: 2px;
    }

    [data-sfk-tip] {
        cursor: help;
    }

    @media (prefers-reduced-motion: reduce) {
        .tippy-box[data-state] {
            transition-duration: 0s !important;
            transform: none !important;
        }
    }

    /* ── Loading: the spinner in the system blue ── */
    .fi-loading-indicator {
        color: var(--kt-blue);
    }

    /* ── Collapsible group (開発者用フィールド, 各種原価 …): a grey header bar with the chevron, like a field group ── */
    .fi-section.fi-collapsible>.fi-section-header {
        min-height: 48px;
        padding: 10px 16px;
        background: #f5f5f5 !important;
        border-bottom: 1px solid var(--kt-border);
        cursor: pointer;
    }

    .fi-section.fi-collapsible.fi-collapsed>.fi-section-header {
        border-bottom: 0;
    }

    .fi-section.fi-collapsible>.fi-section-header:hover {
        background: #eceeef !important;
    }

    .fi-section.fi-collapsible>.fi-section-header .fi-section-header-heading {
        color: var(--kt-text);
        font-size: .875rem;
    }

    .fi-section.fi-collapsible .fi-section-collapse-btn {
        color: var(--kt-muted);
    }

    /* ── Attachment (FileUpload): a light drop zone with a dashed frame, blue while a file is dragged over it ── */
    /* the zone is drawn on the root (FilePond splits its panel into top / centre / bottom pieces); an outline keeps
       the layout as it is */
    .fi-fo-file-upload .filepond--root {
        background-color: var(--sfk-ui-zone) !important;
        box-shadow: none !important;
        outline: 1px dashed var(--kt-tab-border, #c7cbcb);
        outline-offset: -1px;
        transition: background-color .12s;
    }

    .fi-fo-file-upload .filepond--panel-root {
        background-color: transparent;
        border: 0;
    }

    .fi-fo-file-upload .filepond--root[data-hopper-state='drag-over'] {
        background-color: var(--kt-blue-soft) !important;
        outline: 2px dashed var(--kt-blue);
        outline-offset: -2px;
    }

    .fi-fo-file-upload .filepond--drop-label {
        color: var(--kt-text);
    }

    .fi-fo-file-upload .filepond--label-action {
        color: var(--kt-blue);
        text-decoration: none;
    }

    .fi-fo-file-upload .filepond--label-action:hover {
        color: var(--kt-blue-dark);
        text-decoration: underline;
    }

    .fi-fo-file-upload .filepond--item-panel {
        background-color: var(--sfk-ui-file);
        box-shadow: 0 0 0 1px var(--kt-border);
    }

    .fi-fo-file-upload .filepond--file {
        color: var(--kt-text);
    }

    .fi-fo-file-upload [data-filepond-item-state*='error'] .filepond--item-panel,
    .fi-fo-file-upload [data-filepond-item-state*='invalid'] .filepond--item-panel {
        background-color: var(--kt-red);
    }

    .fi-fo-file-upload [data-filepond-item-state*='error'] .filepond--file,
    .fi-fo-file-upload [data-filepond-item-state*='invalid'] .filepond--file {
        color: #ffffff;
    }

    /* ── Multi-select chips: light-blue chips with a blue × ── */
    .fi-select-input-value-badges-ctn .fi-badge {
        min-height: 26px;
        padding: 2px 6px 2px 10px;
        background-color: var(--sfk-ui-chip) !important;
        box-shadow: 0 0 0 1px #c5e1f7 inset !important;
        color: var(--kt-text) !important;
        font-size: .8125rem;
        font-weight: 400;
    }

    .fi-select-input-value-badges-ctn .fi-badge .fi-icon,
    .fi-select-input-value-remove-btn {
        color: var(--kt-blue) !important;
    }

    /* ── Drop-down options: the chosen one in blue with a tick ── */
    .fi-select-input-option[aria-selected='true'] {
        position: relative;
        padding-inline-start: 28px;
        color: var(--kt-blue);
    }

    .fi-select-input-option[aria-selected='true']::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 6px;
        width: 16px;
        height: 16px;
        translate: 0 -50%;
        background: var(--sfk-ui-check) center / 16px 16px no-repeat;
    }

    /* ── Read-only tables (related lists, preview tables): blue header with white labels, like the sub-tables ── */
    .sfk-related-table th,
    .sfk-data-table th {
        height: 40px;
        background: var(--kt-blue) !important;
        border-bottom: 0;
        border-inline-end: 1px solid rgba(255, 255, 255, .25);
        color: #ffffff !important;
        font-size: .8125rem;
        font-weight: 400 !important;
    }

    .sfk-related-table th:first-child,
    .sfk-data-table th:first-child {
        border-inline-start: 1px solid var(--sfk-ui-head-rule);
    }

    .sfk-related-table th:last-child,
    .sfk-data-table th:last-child {
        border-inline-end: 1px solid var(--sfk-ui-head-rule);
    }

    .sfk-related-table tbody tr:hover>td {
        background: var(--kt-blue-soft);
    }

    /*
     * ── Access settings (アクセス権 → アプリ / レコード / フィールド, class sfk-access on their repeaters) ──
     * every setting a white card with a blue left edge and a header naming it (numbered where the order counts),
     * the rights as chips that turn blue when on, 「…を追加」 as a full-width dashed button, × red on hover,
     * new items fading in. Inner lists (対象, 条件) are lighter cards inside.
     */
    .sfk-access>.fi-fo-repeater-items {
        gap: 14px;
        counter-reset: sfk-access;
    }

    .sfk-access>.fi-fo-repeater-items>.fi-fo-repeater-item {
        background: #ffffff;
        border: 1px solid #dbe4ec;
        border-inline-start: 4px solid var(--kt-blue);
        box-shadow: 0 1px 3px rgba(20, 70, 110, .06);
        animation: sfk-access-in .18s ease-out;
        --tw-ring-shadow: 0 0 #0000;
    }

    @keyframes sfk-access-in {
        from {
            opacity: 0;
            transform: translateY(-4px);
        }
    }

    .sfk-access>.fi-fo-repeater-items>.fi-fo-repeater-item>.fi-fo-repeater-item-header {
        min-height: 44px;
        padding: 8px 12px;
        background: #f6f9fc;
        border-bottom: 1px solid #e3ebf2;
    }

    .sfk-access>.fi-fo-repeater-items>.fi-fo-repeater-item>.fi-fo-repeater-item-header .fi-fo-repeater-item-header-label {
        color: var(--kt-label);
        font-size: .875rem;
        font-weight: 700;
    }

    /* numbered settings (レコード, フィールド): the order decides, so the number leads the header */
    .sfk-access-numbered>.fi-fo-repeater-items>.fi-fo-repeater-item {
        counter-increment: sfk-access;
    }

    .sfk-access-numbered>.fi-fo-repeater-items>.fi-fo-repeater-item>.fi-fo-repeater-item-header .fi-fo-repeater-item-header-label::before {
        content: counter(sfk-access);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
        margin-inline-end: 10px;
        padding: 0 6px;
        background: var(--kt-blue);
        color: #ffffff;
        font-size: .8125rem;
        font-variant-numeric: tabular-nums;
    }

    .sfk-access>.fi-fo-repeater-items>.fi-fo-repeater-item>.fi-fo-repeater-item-content {
        padding: 16px 18px 18px;
    }

    /* inner lists: lighter cards, grey left edge */
    .sfk-access :is(.sfk-access-entries, .sfk-access-conditions)>.fi-fo-repeater-items>.fi-fo-repeater-item {
        background: #fbfcfd;
        border-color: #e3e7e8;
        border-inline-start-color: #b9c9d6;
        box-shadow: none;
    }

    .sfk-access :is(.sfk-access-entries, .sfk-access-conditions)>.fi-fo-repeater-items>.fi-fo-repeater-item>.fi-fo-repeater-item-header {
        min-height: 36px;
        padding: 4px 10px;
        background: transparent;
    }

    .sfk-access :is(.sfk-access-entries, .sfk-access-conditions)>.fi-fo-repeater-items>.fi-fo-repeater-item>.fi-fo-repeater-item-header .fi-fo-repeater-item-header-label {
        font-size: .875rem;
    }

    .sfk-access :is(.sfk-access-entries, .sfk-access-conditions)>.fi-fo-repeater-items>.fi-fo-repeater-item>.fi-fo-repeater-item-content {
        padding: 10px 12px 12px;
    }

    /* remove (×) and drag handle: quiet until hovered */
    .sfk-access .fi-fo-repeater-item-header .fi-icon-btn {
        /* color: var(--kt-muted); */
    }

    .sfk-access .fi-fo-repeater-item-header-end-actions .fi-icon-btn:hover,
    .sfk-access .fi-fo-repeater-item-header-end-actions .fi-icon-btn:focus-visible {
        background: #fdecea;
        color: var(--kt-red);
    }

    .sfk-access .fi-fo-repeater-item-header-start-actions .fi-icon-btn:hover {
        color: var(--kt-blue);
    }

    /* 「…を追加」: a full-width dashed button under the list */
    .sfk-access>.fi-fo-repeater-add {
        justify-content: stretch;
    }

    .sfk-access>.fi-fo-repeater-add .fi-btn {
        width: 100%;
        min-height: 44px;
        background: #f7fbfe;
        border: 1px dashed var(--kt-blue);
        box-shadow: none;
        color: var(--kt-blue);
        font-size: .875rem;
    }

    .sfk-access>.fi-fo-repeater-add .fi-btn:hover {
        background: var(--kt-blue-soft);
        color: var(--kt-blue-dark);
    }

    .sfk-access :is(.sfk-access-entries, .sfk-access-conditions)>.fi-fo-repeater-add .fi-btn {
        min-height: 36px;
        background: #ffffff;
        border-color: #b9c9d6;
        font-size: .8125rem;
    }

    /* the rights of an entry in one wrapping row (Filament puts a group's parts in an inner grid) */
    .sfk-access-rights>.fi-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .sfk-access-rights>.fi-grid>.fi-grid-col {
        width: auto;
    }

    /* rights as chips: 閲覧・編集・削除 … (check boxes) and 編集 / 閲覧 / なし (radio) turn blue when on */
    .sfk-access label.fi-fo-field-label:has(> .fi-checkbox-input),
    .sfk-access .fi-fo-radio-label {
        min-height: 38px;
        padding: 6px 12px;
        background: #ffffff;
        border: 1px solid var(--kt-disabled);
        transition: background-color .12s, border-color .12s, color .12s;
    }

    .sfk-access label.fi-fo-field-label:has(> .fi-checkbox-input:checked),
    .sfk-access .fi-fo-radio-label:has(> .fi-radio-input:checked) {
        background: var(--kt-blue-soft);
        border-color: var(--kt-blue);
    }

    .sfk-access label.fi-fo-field-label:has(> .fi-checkbox-input:checked) .fi-fo-field-label-content,
    .sfk-access .fi-fo-radio-label:has(> .fi-radio-input:checked) .fi-fo-radio-label-text {
        color: var(--kt-blue-dark);
        font-weight: 700;
    }

    .sfk-access label.fi-fo-field-label:has(> .fi-checkbox-input):hover,
    .sfk-access .fi-fo-radio-label:hover {
        border-color: var(--kt-blue);
    }

    /* なし chosen: red, it hides the field */
    .sfk-access .fi-fo-radio-label:has(> .fi-radio-input[value='none']:checked) {
        background: #fdecea;
        border-color: var(--kt-red);
    }

    .sfk-access .fi-fo-radio-label:has(> .fi-radio-input[value='none']:checked) .fi-fo-radio-label-text {
        color: var(--kt-red-dark);
    }

    .sfk-access .fi-fo-radio.fi-inline {
        gap: 8px;
    }

    /* アプリの設定 → 設定 → アクセス権: アプリ / レコード / フィールド as cards with how many settings each holds */
    .sfk-access-links {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 12px;
    }

    .sfk-access-link {
        display: flex;
        align-items: center;
        gap: 14px;
        min-height: 72px;
        padding: 12px 16px;
        background: #ffffff;
        border: 1px solid #dbe4ec;
        border-inline-start: 4px solid var(--kt-blue);
        box-shadow: 0 1px 3px rgba(20, 70, 110, .06);
        color: var(--kt-text);
        transition: background-color .12s, box-shadow .12s, transform .12s;
    }

    .sfk-access-link:hover,
    .sfk-access-link:focus-visible {
        background: #f7fbfe;
        box-shadow: 0 4px 12px rgba(20, 70, 110, .12);
        transform: translateY(-1px);
    }

    .sfk-access-link:focus-visible {
        outline: 1px solid var(--kt-blue);
        outline-offset: 1px;
    }

    .sfk-access-link-tile {
        display: inline-flex;
        flex: none;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: var(--kt-blue-soft);
        color: var(--kt-blue);
    }

    .sfk-access-link-icon {
        width: 22px;
        height: 22px;
    }

    .sfk-access-link-text {
        display: grid;
        flex: 1;
        gap: 2px;
        min-width: 0;
    }

    .sfk-access-link-title {
        color: var(--kt-label);
        font-size: .875rem;
        font-weight: 700;
    }

    .sfk-access-link-count {
        color: var(--kt-muted);
        font-size: .8125rem;
        font-variant-numeric: tabular-nums;
    }

    .sfk-access-link-arrow {
        width: 18px;
        height: 18px;
        flex: none;
        color: var(--kt-blue);
    }
</style>

<script>
    // Information tooltip (Modules\Core\Filament\Support\Tip): the shared settings of `x-tooltip="sfkTip(html)"` —
    // light theme, arrow, shown after a short delay on hover, at once on keyboard focus or a tap (touch), kept inside
    // the screen (it flips and shifts near the edges), described to screen readers (aria-describedby), closed by Esc,
    // a tap elsewhere or leaving. Defined once (served in the cached theme script; safe to run again).
    window.sfkTip = window.sfkTip || function (content, extra) {
        const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        return Object.assign({
            content: content,
            allowHTML: true,
            theme: 'sfk-tip',
            animation: 'sfk-tip',
            placement: 'top',
            arrow: true,
            offset: [0, 8],
            maxWidth: 320,
            delay: [250, 0],
            duration: reduced ? [0, 0] : [160, 120],
            trigger: 'mouseenter focus',
            touch: true,
            hideOnClick: true,
            interactive: false,
            aria: { content: 'describedby', expanded: false },
            appendTo: () => document.body,
            popperOptions: { modifiers: [{ name: 'preventOverflow', options: { padding: 8 } }, { name: 'flip', options: { fallbackPlacements: ['bottom'] } }] },
            onShow(instance) {
                instance.sfkEsc = (event) => { if (event.key === 'Escape') { instance.hide(); } };
                document.addEventListener('keydown', instance.sfkEsc);
            },
            onHide(instance) {
                document.removeEventListener('keydown', instance.sfkEsc);
            },
        }, extra || {});
    };

    // Browser `title`s become the same tooltip (user 2026-11-14): on the first hover or focus of an element with a
    // title, the title moves to data-sfk-title and the element gets `x-tooltip="sfkTipTitle($el)"` (Alpine sets it up
    // from the attribute). Touch screens never showed titles: nothing changes there. A redraw by Livewire puts the
    // title back; the next hover takes it again. Form fields, frames and [data-native-title] keep the browser's.
    window.sfkTipTitle = window.sfkTipTitle || function (el) {
        const text = (el.dataset.sfkTitle || '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

        return window.sfkTip('<p class="sfk-tip-text">' + text + '</p>', { touch: false, delay: [400, 0] });
    };

    if (!window.sfkTitleTips) {
        window.sfkTitleTips = true;
        const adopt = (event) => {
            if (event.pointerType === 'touch' || !(event.target instanceof Element)) {
                return;
            }
            const el = event.target.closest('[title]');
            if (!el || el.hasAttribute('x-tooltip') || el.closest('[data-native-title]') || el.matches('iframe, input, textarea, select, option, link, style, svg *')) {
                return;
            }
            const text = (el.getAttribute('title') || '').trim();
            el.removeAttribute('title');
            if (text === '') {
                return;
            }
            el.dataset.sfkTitle = text;
            el.setAttribute('x-tooltip', 'sfkTipTitle($el)');
            setTimeout(() => {
                if (!el.__x_tippy) {
                    // not set up (outside Alpine): the browser's title again
                    el.removeAttribute('x-tooltip');
                    el.setAttribute('title', text);
                } else if (el.matches(':hover') || el === document.activeElement) {
                    el.__x_tippy.show();
                }
            }, 30);
        };
        document.addEventListener('pointerover', adopt, true);
        document.addEventListener('focusin', adopt, true);
    }
</script>
