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
        --sfk-ui-chip: #e2f2fe;
        --sfk-ui-head-rule: #1d6fa5;
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

    /* ── Tooltip: near-black box with white text ── */
    .tippy-box {
        --tippy-background-color: var(--sfk-ui-tooltip);
        max-width: 260px !important;
        color: #ffffff;
        font-size: .875rem;
        line-height: 1.5;
        box-shadow: none;
    }

    .tippy-box[data-theme~='light'] {
        --tippy-background-color: var(--sfk-ui-tooltip);
        --tippy-box-shadow: none;
        color: #ffffff;
    }

    .tippy-content {
        padding: 6px 10px;
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
        font-size: 1rem;
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
        font-size: .875rem;
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
        font-size: .875rem;
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
        font-size: 1rem;
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
        font-size: .875rem;
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
        font-size: .9375rem;
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
        font-size: .9375rem;
    }

    .sfk-access>.fi-fo-repeater-add .fi-btn:hover {
        background: var(--kt-blue-soft);
        color: var(--kt-blue-dark);
    }

    .sfk-access :is(.sfk-access-entries, .sfk-access-conditions)>.fi-fo-repeater-add .fi-btn {
        min-height: 36px;
        background: #ffffff;
        border-color: #b9c9d6;
        font-size: .875rem;
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
        font-size: 1rem;
        font-weight: 700;
    }

    .sfk-access-link-count {
        color: var(--kt-muted);
        font-size: .875rem;
        font-variant-numeric: tabular-nums;
    }

    .sfk-access-link-arrow {
        width: 18px;
        height: 18px;
        flex: none;
        color: var(--kt-blue);
    }
</style>
