{{--
    The look of the quick-entry panels (08. 日報入力's QuickDailyReport and Core QuickEntry: 14. 事故報告管理, 16. 申請管理,
    17. 安全衛生チェック, 18. アルコールチェック): phones first — big touch targets, 16px+ inputs (no zoom) — with the --sfk-*
    tokens of core::filament.design.
--}}
<style>
    .sfk-qe {
        position: fixed;
        inset: 0;
        z-index: 45;
        display: flex;
        flex-direction: column;
        height: 100vh;
        height: 100dvh;
        background: var(--sfk-page);
        color: var(--sfk-text);
    }

    .sfk-qe-head {
        display: grid;
        grid-template-columns: 48px 1fr 48px;
        align-items: center;
        gap: var(--sfk-space-2);
        padding: var(--sfk-space-2) var(--sfk-space-2);
        padding-top: max(var(--sfk-space-2), env(safe-area-inset-top));
        background: var(--sfk-surface);
        box-shadow: 0 1px 0 var(--sfk-line);
    }

    .sfk-qe-title {
        margin: 0;
        overflow: hidden;
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-title);
        font-weight: var(--sfk-fw-bold);
        text-align: center;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sfk-qe-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border: 0;
        border-radius: var(--sfk-radius);
        background: transparent;
        color: var(--sfk-muted);
        cursor: pointer;
    }

    .sfk-qe-icon-btn:hover {
        background: var(--sfk-hover);
        color: var(--sfk-ink);
    }

    .sfk-qe-icon-btn .fi-icon {
        width: 24px;
        height: 24px;
    }

    .sfk-qe-progress {
        display: flex;
        align-items: center;
        gap: var(--sfk-space-3);
        padding: var(--sfk-space-2) var(--sfk-gutter);
        background: var(--sfk-surface);
        box-shadow: 0 1px 0 var(--sfk-line-soft);
    }

    .sfk-qe-bar {
        flex: 1;
        height: 6px;
        overflow: hidden;
        border-radius: 999px;
        background: var(--sfk-line-soft);
    }

    .sfk-qe-bar>span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: var(--sfk-primary);
        transition: width .25s ease;
    }

    .sfk-qe-count {
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-small);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sfk-qe-form {
        display: flex;
        flex: 1;
        flex-direction: column;
        min-height: 0;
    }

    .sfk-qe-body {
        flex: 1;
        overflow-x: hidden;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }

    .sfk-qe-step {
        box-sizing: border-box;
        width: 100%;
        max-width: 560px;
        margin: 0 auto;
        padding: var(--sfk-space-5) var(--sfk-gutter) var(--sfk-space-8);
        animation: sfk-qe-forward .2s ease-out;
    }

    .sfk-qe-step.is-back {
        animation-name: sfk-qe-back;
    }

    @keyframes sfk-qe-forward {
        from {
            opacity: 0;
            transform: translateX(24px);
        }
    }

    @keyframes sfk-qe-back {
        from {
            opacity: 0;
            transform: translateX(-24px);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .sfk-qe-step {
            animation: none;
        }
    }

    .sfk-qe-label {
        display: block;
        margin: 0 0 var(--sfk-space-3);
        color: var(--kt-label, var(--sfk-ink));
        font-size: var(--sfk-fs-heading);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-qe-field+.sfk-qe-field {
        margin-top: var(--sfk-space-5);
    }

    .sfk-qe-input,
    .sfk-qe-select,
    .sfk-qe-textarea {
        box-sizing: border-box;
        width: 100%;
        min-height: 56px;
        padding: 0 var(--sfk-space-4);
        border: 1px solid var(--sfk-line-strong);
        border-radius: var(--sfk-radius-lg);
        background: var(--sfk-surface);
        color: var(--sfk-ink);
        /* 16px or more: phones do not zoom into the field */
        font-size: var(--sfk-fs-figure);
        font-variant-numeric: tabular-nums;
    }

    .sfk-qe-textarea {
        min-height: 120px;
        padding: var(--sfk-space-3) var(--sfk-space-4);
        resize: vertical;
    }

    .sfk-qe-input:focus,
    .sfk-qe-select:focus,
    .sfk-qe-textarea:focus {
        border-color: var(--sfk-primary);
        outline: 2px solid var(--sfk-primary-soft-strong);
        outline-offset: 0;
    }

    .sfk-qe-input.is-invalid,
    .sfk-qe-select.is-invalid {
        border-color: var(--sfk-danger);
    }

    .sfk-qe-unit {
        display: flex;
        align-items: center;
        gap: var(--sfk-space-2);
    }

    .sfk-qe-unit>span {
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-heading);
        white-space: nowrap;
    }

    .sfk-qe-error {
        margin: var(--sfk-space-2) 0 0;
        color: var(--sfk-danger-text);
        font-size: var(--sfk-fs-body);
    }

    .sfk-qe-chips {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: var(--sfk-space-2);
        margin-top: var(--sfk-space-3);
    }

    .sfk-qe-chip,
    .sfk-qe-option,
    .sfk-qe-toggle>span,
    .sfk-qe-segment span {
        display: flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        min-height: 52px;
        padding: var(--sfk-space-2) var(--sfk-space-3);
        border: 1px solid var(--sfk-line-strong);
        border-radius: var(--sfk-radius-lg);
        background: var(--sfk-surface);
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-heading);
        font-variant-numeric: tabular-nums;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
        transition: background-color .15s ease, border-color .15s ease;
    }

    .sfk-qe-chip:hover,
    .sfk-qe-option:hover,
    .sfk-qe-toggle:hover>span,
    .sfk-qe-segment label:hover span {
        border-color: var(--sfk-primary);
    }

    .sfk-qe-chip.is-selected,
    .sfk-qe-option.is-selected,
    .sfk-qe-toggle input:checked+span,
    .sfk-qe-segment input:checked+span {
        border-color: var(--sfk-primary);
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-qe-toggle input,
    .sfk-qe-segment input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .sfk-qe-toggle input:focus-visible+span,
    .sfk-qe-segment input:focus-visible+span {
        outline: 2px solid var(--sfk-primary);
        outline-offset: 2px;
    }

    .sfk-qe-toggle,
    .sfk-qe-segment label {
        position: relative;
        display: block;
    }

    .sfk-qe-options {
        display: grid;
        gap: var(--sfk-space-2);
        margin: var(--sfk-space-3) 0 0;
        padding: 0;
        list-style: none;
    }

    .sfk-qe-options.is-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sfk-qe-option {
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
        text-align: left;
    }

    .sfk-qe-options.is-grid .sfk-qe-option {
        align-items: center;
        text-align: center;
    }

    .sfk-qe-option-main {
        overflow-wrap: anywhere;
    }

    .sfk-qe-option-sub {
        width: 100%;
        overflow: hidden;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-small);
        font-weight: var(--sfk-fw-regular);
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sfk-qe-job {
        margin: 0 0 var(--sfk-space-4);
        padding: var(--sfk-space-3) var(--sfk-space-4);
        border-radius: var(--sfk-radius-lg);
        background: var(--sfk-surface);
        box-shadow: inset 0 0 0 1px var(--sfk-line);
        font-size: var(--sfk-fs-body);
    }

    .sfk-qe-job strong {
        display: block;
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-heading);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-qe-job dl {
        display: flex;
        flex-wrap: wrap;
        gap: var(--sfk-space-1) var(--sfk-space-4);
        margin: var(--sfk-space-1) 0 0;
        color: var(--sfk-muted);
    }

    .sfk-qe-job dd {
        margin: 0 0 0 var(--sfk-space-1);
        color: var(--sfk-ink);
        font-variant-numeric: tabular-nums;
    }

    .sfk-qe-job dl>div {
        display: flex;
    }

    .sfk-qe-segment {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--sfk-space-2);
    }

    .sfk-qe-group {
        margin: 0;
        padding: 0;
        border: 0;
    }

    .sfk-qe-group+.sfk-qe-group {
        margin-top: var(--sfk-space-5);
    }

    .sfk-qe-pair {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--sfk-space-3);
    }

    .sfk-qe-toggles {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: var(--sfk-space-2);
    }

    .sfk-qe-link {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        margin-top: var(--sfk-space-2);
        padding: 0 var(--sfk-space-2);
        border: 0;
        background: transparent;
        color: var(--sfk-primary);
        font-size: var(--sfk-fs-body);
        cursor: pointer;
    }

    .sfk-qe-summary {
        margin: 0;
        padding: 0;
        overflow: hidden;
        border-radius: var(--sfk-radius-lg);
        background: var(--sfk-surface);
        box-shadow: inset 0 0 0 1px var(--sfk-line);
        list-style: none;
    }

    .sfk-qe-summary li+li {
        border-top: 1px solid var(--sfk-line-soft);
    }

    .sfk-qe-summary-row {
        display: grid;
        grid-template-columns: minmax(96px, 38%) 1fr 20px;
        align-items: center;
        gap: var(--sfk-space-3);
        box-sizing: border-box;
        width: 100%;
        min-height: 52px;
        padding: var(--sfk-space-2) var(--sfk-space-4);
        border: 0;
        background: transparent;
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-body);
        text-align: left;
    }

    button.sfk-qe-summary-row {
        cursor: pointer;
    }

    button.sfk-qe-summary-row:hover {
        background: var(--sfk-hover);
    }

    .sfk-qe-summary-label {
        color: var(--kt-label, var(--sfk-ink));
        font-weight: var(--sfk-fw-medium);
    }

    .sfk-qe-summary-value {
        overflow-wrap: anywhere;
        font-variant-numeric: tabular-nums;
    }

    .sfk-qe-summary-row.is-invalid .sfk-qe-summary-value {
        color: var(--sfk-danger-text);
    }

    .sfk-qe-summary-row .fi-icon {
        width: 18px;
        height: 18px;
        color: var(--sfk-subtle);
    }

    .sfk-qe-check {
        display: flex;
        align-items: center;
        gap: var(--sfk-space-3);
        min-height: 52px;
        font-size: var(--sfk-fs-heading);
    }

    .sfk-qe-check input {
        width: 22px;
        height: 22px;
    }

    .sfk-qe-foot {
        display: flex;
        gap: var(--sfk-space-3);
        justify-content: center;
        padding: var(--sfk-space-3) var(--sfk-gutter);
        padding-bottom: max(var(--sfk-space-3), env(safe-area-inset-bottom));
        background: var(--sfk-surface);
        box-shadow: 0 -1px 0 var(--sfk-line);
    }

    .sfk-qe-foot-inner {
        display: flex;
        flex-wrap: wrap;
        gap: var(--sfk-space-3);
        width: 100%;
        max-width: 560px;
    }

    .sfk-qe-btn {
        display: inline-flex;
        flex: 1 1 0;
        align-items: center;
        justify-content: center;
        gap: var(--sfk-space-2);
        min-width: 0;
        min-height: 52px;
        padding: 0 var(--sfk-space-4);
        border: 1px solid var(--sfk-line-strong);
        border-radius: var(--sfk-radius-lg);
        background: var(--sfk-surface);
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-heading);
        font-weight: var(--sfk-fw-semibold);
        white-space: nowrap;
        cursor: pointer;
    }

    .sfk-qe-btn.is-primary {
        flex-grow: 2;
        border-color: var(--sfk-primary);
        background: var(--sfk-primary);
        color: #fff;
    }

    .sfk-qe-btn:disabled {
        opacity: .6;
        cursor: wait;
    }

    .sfk-qe-foot-inner.is-stacked {
        flex-direction: column-reverse;
    }

    .sfk-qe-foot-inner.is-stacked>.sfk-qe-btn {
        flex: none;
        width: 100%;
    }

    /* ---- the shared steps of Core QuickEntry (14 / 16 / 17 / 18) ------------------------------------------ */
    .sfk-qe-section {
        display: inline-flex;
        align-items: center;
        margin: 0 0 var(--sfk-space-2);
        padding: 2px 10px;
        border-radius: 999px;
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary);
        font-size: var(--sfk-fs-small);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-qe-options.is-cols-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sfk-qe-options.is-cols-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .sfk-qe-options:is(.is-cols-2, .is-cols-3) .sfk-qe-option {
        align-items: center;
        text-align: center;
    }

    .sfk-qe-option.is-danger.is-selected {
        border-color: var(--sfk-danger);
        background: var(--sfk-danger-soft);
        color: var(--sfk-danger-text);
    }

    .sfk-qe-person {
        display: flex;
        align-items: center;
        gap: var(--sfk-space-3);
        box-sizing: border-box;
        width: 100%;
        min-height: 56px;
        padding: var(--sfk-space-2) var(--sfk-space-3);
        border: 1px solid var(--sfk-line-strong);
        border-radius: var(--sfk-radius-lg);
        background: var(--sfk-surface);
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-heading);
        text-align: left;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
    }

    .sfk-qe-person:hover {
        border-color: var(--sfk-primary);
    }

    .sfk-qe-person.is-selected {
        border-color: var(--sfk-primary);
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-qe-person>.fi-icon {
        width: 22px;
        height: 22px;
        margin-left: auto;
        color: var(--sfk-primary);
    }

    .sfk-qe-avatar {
        display: grid;
        flex: none;
        place-items: center;
        width: 36px;
        height: 36px;
        border-radius: 999px;
        background: var(--sfk-hover-strong);
        color: var(--sfk-text);
        font-size: var(--sfk-fs-small);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-qe-person.is-selected .sfk-qe-avatar {
        background: var(--sfk-primary);
        color: #fff;
    }

    .sfk-qe-person-name {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sfk-qe-empty {
        margin: var(--sfk-space-2) 0 0;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-body);
    }

    .sfk-qe-summary-value {
        display: -webkit-box;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
        white-space: pre-line;
    }

    .sfk-qe-summary-row.is-figure .sfk-qe-summary-value {
        font-weight: var(--sfk-fw-semibold);
    }

    /* cards of a table entered on a phone (損害 of 14, チェックリスト of 17) */
    .sfk-qe-cards {
        display: flex;
        flex-direction: column;
        gap: var(--sfk-space-3);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .sfk-qe-card {
        padding: var(--sfk-space-4);
        border-radius: var(--sfk-radius-lg);
        background: var(--sfk-surface);
        box-shadow: inset 0 0 0 1px var(--sfk-line);
        transition: box-shadow .15s ease;
    }

    .sfk-qe-card.is-done {
        box-shadow: inset 0 0 0 1px var(--sfk-success), inset 4px 0 0 var(--sfk-success);
    }

    .sfk-qe-card.is-invalid {
        box-shadow: inset 0 0 0 1px var(--sfk-danger), inset 4px 0 0 var(--sfk-danger);
    }

    .sfk-qe-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: var(--sfk-space-3);
        margin-bottom: var(--sfk-space-3);
    }

    .sfk-qe-card-title {
        margin: 0;
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-heading);
        font-weight: var(--sfk-fw-semibold);
        overflow-wrap: anywhere;
    }

    .sfk-qe-card-remove {
        display: inline-flex;
        flex: none;
        align-items: center;
        gap: 4px;
        min-height: 36px;
        padding: 0 var(--sfk-space-2);
        border: 0;
        border-radius: var(--sfk-radius);
        background: transparent;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-small);
        cursor: pointer;
    }

    .sfk-qe-card-remove:hover {
        background: var(--sfk-danger-soft);
        color: var(--sfk-danger-text);
    }

    .sfk-qe-card-remove .fi-icon {
        width: 18px;
        height: 18px;
    }

    .sfk-qe-mini-label {
        display: block;
        margin: 0 0 var(--sfk-space-1);
        color: var(--kt-label, var(--sfk-ink));
        font-size: var(--sfk-fs-body);
        font-weight: var(--sfk-fw-medium);
    }

    .sfk-qe-card .sfk-qe-input {
        min-height: 48px;
    }

    .sfk-qe-card .sfk-qe-textarea {
        min-height: 80px;
    }

    .sfk-qe-card .sfk-qe-segment span {
        min-height: 44px;
    }

    .sfk-qe-card-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--sfk-space-3);
        margin-top: var(--sfk-space-3);
    }

    .sfk-qe-card-foot {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--sfk-space-3);
        margin-top: var(--sfk-space-3);
        padding-top: var(--sfk-space-3);
        border-top: 1px solid var(--sfk-line-soft);
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-body);
    }

    .sfk-qe-card-foot strong {
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-heading);
        font-weight: var(--sfk-fw-semibold);
        font-variant-numeric: tabular-nums;
    }

    .sfk-qe-add {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: var(--sfk-space-2);
        box-sizing: border-box;
        width: 100%;
        min-height: 52px;
        margin-top: var(--sfk-space-3);
        border: 1px dashed var(--sfk-line-strong);
        border-radius: var(--sfk-radius-lg);
        background: transparent;
        color: var(--sfk-primary);
        font-size: var(--sfk-fs-heading);
        font-weight: var(--sfk-fw-semibold);
        cursor: pointer;
    }

    .sfk-qe-add:hover {
        border-color: var(--sfk-primary);
        background: var(--sfk-primary-soft);
    }

    .sfk-qe-add .fi-icon {
        width: 20px;
        height: 20px;
    }

    .sfk-qe-total {
        position: sticky;
        top: 0;
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--sfk-space-3);
        margin: 0 0 var(--sfk-space-3);
        padding: var(--sfk-space-3) var(--sfk-space-4);
        border-radius: var(--sfk-radius-lg);
        background: var(--sfk-surface);
        box-shadow: inset 0 0 0 1px var(--sfk-line), 0 4px 10px rgba(16, 24, 40, .06);
        color: var(--sfk-text);
        font-size: var(--sfk-fs-body);
    }

    .sfk-qe-total strong {
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-figure);
        font-weight: var(--sfk-fw-bold);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sfk-qe-total .sfk-qe-link {
        min-height: 36px;
        margin: 0;
        white-space: nowrap;
    }

    .sfk-qe-meter {
        flex: 1;
        height: 6px;
        overflow: hidden;
        border-radius: 999px;
        background: var(--sfk-line-soft);
    }

    .sfk-qe-meter>span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: var(--sfk-success);
        transition: width .2s ease;
    }

    .sfk-qe-switch {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--sfk-space-3);
        min-height: 56px;
        margin: 0 0 var(--sfk-space-3);
        padding: var(--sfk-space-2) var(--sfk-space-4);
        border-radius: var(--sfk-radius-lg);
        background: var(--sfk-surface);
        box-shadow: inset 0 0 0 1px var(--sfk-line);
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-heading);
        cursor: pointer;
    }

    button.sfk-qe-switch {
        box-sizing: border-box;
        width: 100%;
        border: 0;
        text-align: left;
    }

    .sfk-qe-switch-track {
        position: relative;
        flex: none;
        width: 52px;
        height: 28px;
        border-radius: 999px;
        background: var(--sfk-ui-switch-off, #b5b5b5);
        transition: background-color .2s ease;
    }

    .sfk-qe-switch-track>span {
        position: absolute;
        top: 4px;
        left: 4px;
        width: 20px;
        height: 20px;
        border-radius: 999px;
        background: #fff;
        box-shadow: 0 1px 2px rgba(0, 0, 0, .2);
        transition: translate .2s ease;
    }

    .sfk-qe-switch-track.is-on {
        background: var(--sfk-primary);
    }

    .sfk-qe-switch-track.is-on>span {
        translate: 24px 0;
    }

    .sfk-qe-check-toggle {
        display: inline-flex;
        flex: none;
        align-items: center;
        justify-content: center;
        gap: var(--sfk-space-1);
        min-width: 96px;
        min-height: 44px;
        padding: 0 var(--sfk-space-3);
        border: 1px solid var(--sfk-line-strong);
        border-radius: var(--sfk-radius-lg);
        background: var(--sfk-surface);
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-heading);
        cursor: pointer;
    }

    .sfk-qe-check-toggle .fi-icon {
        width: 20px;
        height: 20px;
    }

    .sfk-qe-check-toggle.is-on {
        border-color: var(--sfk-success);
        background: var(--sfk-success-soft);
        color: var(--sfk-success);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-qe-card-row {
        display: flex;
        align-items: center;
        gap: var(--sfk-space-2);
    }

    .sfk-qe-card-row .sfk-qe-segment {
        flex: 1;
    }

    .sfk-qe-no {
        display: inline-grid;
        flex: none;
        place-items: center;
        min-width: 28px;
        height: 28px;
        margin-right: var(--sfk-space-2);
        padding: 0 6px;
        border-radius: 999px;
        background: var(--sfk-hover-strong);
        color: var(--sfk-text);
        font-size: var(--sfk-fs-small);
        font-weight: var(--sfk-fw-semibold);
        font-variant-numeric: tabular-nums;
        vertical-align: 1px;
    }

    .sfk-qe-note-link {
        min-height: 36px;
        margin-top: var(--sfk-space-1);
        padding: 0;
        font-size: var(--sfk-fs-small);
    }

    @media (max-width: 380px) {
        .sfk-qe-card-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
