{{-- Look of 帳票出力 (core::filament.documents.*), included by core::filament.theme: same colours, square corners. --}}
<style>
    /* アプリの設定 → 帳票出力 */
    .sfk-docs-meta {
        flex-shrink: 0;
        color: var(--kt-muted);
        font-size: .875rem;
    }

    .sfk-docs-output {
        padding-top: 8px;
        padding-bottom: 8px;
    }

    .sfk-docs-output-body {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }

    .sfk-docs-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }

    .sfk-docs-tag {
        padding: 1px 8px;
        border: 1px solid var(--kt-border);
        background: var(--kt-normal);
        color: var(--kt-text);
        font-size: .8125rem;
    }

    .sfk-docs-tag.is-place {
        border-color: #b9dcf5;
        background: var(--kt-blue-soft);
    }

    .sfk-docs-tag.is-admin {
        border-color: #f5c6c0;
        background: #fdecea;
    }

    /* 上部スペース: 帳票 + 出力 */
    .sfk-docs-bar {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 24px;
        border-top: 1px solid var(--kt-border);
        background: #ffffff;
    }

    .sfk-docs-bar-icon {
        width: 20px;
        height: 20px;
        color: var(--kt-blue);
    }

    .sfk-docs-bar-select {
        display: block;
        width: 280px;
        max-width: 100%;
    }

    /* drop-downs fill their box (bar, designer toolbar and properties) */
    .sfk-docs-bar-select select,
    .sfk-designer .sfk-filter-select,
    .sfk-designer .sfk-filter-select select {
        display: block;
        width: 100%;
    }

    .sfk-designer .sfk-filter-select select {
        min-width: 0;
        height: 36px;
    }

    .sfk-docs-bar-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 96px;
        height: 36px;
        font-size: .9375rem;
    }

    /* the designer */
    .sfk-designer {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .sfk-designer-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px 20px;
        padding: 12px 16px;
        border: 1px solid var(--kt-border);
        background: var(--kt-normal);
    }

    .sfk-designer-name {
        display: flex;
        flex-direction: column;
        gap: 4px;
        width: 320px;
        max-width: 100%;
    }

    .sfk-designer-caption {
        color: var(--kt-label);
        font-size: .9375rem;
    }

    .sfk-designer-tools {
        display: flex;
        flex: 1;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px 16px;
    }

    .sfk-designer-tool {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .sfk-designer-pages {
        display: inline-flex;
    }

    .sfk-designer-page-tab {
        height: 36px;
        padding: 0 12px;
        margin-left: -1px;
        border: 1px solid var(--kt-tab-border);
        background: var(--kt-normal);
        color: var(--kt-text);
        font-size: .9375rem;
    }

    .sfk-designer-page-tab.is-active {
        background: #ffffff;
        color: var(--kt-blue);
        font-weight: 700;
    }

    .sfk-designer-link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: var(--kt-blue);
        font-size: .9375rem;
    }

    .sfk-designer-link:hover {
        text-decoration: underline;
    }

    .sfk-designer-link.is-danger {
        color: var(--kt-red);
    }

    .sfk-designer-link-icon {
        width: 16px;
        height: 16px;
    }

    .sfk-designer-preview {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .sfk-designer-preview .sfk-filter-select {
        width: 220px;
    }

    .sfk-designer-tool .sfk-filter-select {
        width: 110px;
    }

    .sfk-designer-actions {
        display: flex;
        gap: 8px;
        margin-left: auto;
    }

    .sfk-designer-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 120px;
        height: 40px;
        font-size: 1rem;
    }

    .sfk-designer-body {
        display: grid;
        grid-template-columns: 240px minmax(0, 1fr) 340px;
        height: calc(100vh - 280px);
        min-height: 560px;
        border: 1px solid var(--kt-border);
        background: #ffffff;
    }

    .sfk-designer-palette,
    .sfk-designer-props {
        overflow-y: auto;
        padding: 12px;
        background: var(--kt-normal);
    }

    .sfk-designer-palette {
        border-right: 1px solid var(--kt-border);
    }

    .sfk-designer-props {
        border-left: 1px solid var(--kt-border);
    }

    .sfk-designer-group {
        margin: 14px 0 6px;
        padding-left: 8px;
        border-left: 3px solid var(--kt-blue);
        color: var(--kt-label);
        font-size: .9375rem;
        font-weight: 700;
    }

    .sfk-designer-items {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .sfk-designer-item {
        display: flex;
        align-items: center;
        gap: 6px;
        width: 100%;
        min-height: 32px;
        padding: 4px 8px;
        overflow: hidden;
        border: 1px solid var(--kt-border);
        background: #ffffff;
        color: var(--kt-text);
        font-size: .9375rem;
        text-align: left;
        text-overflow: ellipsis;
        white-space: nowrap;
        cursor: grab;
    }

    .sfk-designer-item:hover {
        border-color: var(--kt-blue);
        background: var(--kt-blue-soft);
    }

    .sfk-designer-item.is-table {
        border-left: 3px solid #e67e22;
    }

    .sfk-designer-item.is-text {
        border-left: 3px solid #27ae60;
    }

    .sfk-designer-item-icon {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
        color: #e67e22;
    }

    .sfk-designer-stage {
        overflow: auto;
        padding: 24px;
        background: #dfe5ea;
    }

    .sfk-designer-sheet {
        position: relative;
        margin: 0 auto;
        background: #ffffff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .25);
    }

    .sfk-designer-background {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
    }

    .sfk-designer-element {
        position: absolute;
        outline: 1px dashed var(--kt-blue);
        background: rgba(52, 152, 219, .08);
        cursor: move;
        touch-action: none;
        user-select: none;
    }

    .sfk-designer-element.is-text {
        outline-color: #27ae60;
        background: rgba(39, 174, 96, .08);
    }

    .sfk-designer-element.is-table {
        outline-color: #e67e22;
        background: rgba(230, 126, 34, .06);
    }

    .sfk-designer-element.has-border {
        box-shadow: inset 0 0 0 1px #000000;
    }

    .sfk-designer-element.is-selected {
        z-index: 2;
        outline: 2px solid var(--kt-blue);
    }

    .sfk-designer-text {
        display: flex;
        width: 100%;
        height: 100%;
        overflow: hidden;
        line-height: 1.2;
    }

    .sfk-designer-table {
        display: flex;
        align-items: flex-start;
        height: 100%;
        overflow: hidden;
        background-image: linear-gradient(to bottom, transparent calc(100% - 1px), rgba(230, 126, 34, .45) calc(100% - 1px));
        background-repeat: repeat-y;
    }

    .sfk-designer-column {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        overflow: hidden;
        border-right: 1px dashed rgba(230, 126, 34, .7);
        line-height: 1.2;
        white-space: nowrap;
    }

    .sfk-designer-resize {
        position: absolute;
        right: -5px;
        bottom: -5px;
        width: 10px;
        height: 10px;
        background: var(--kt-blue);
        cursor: nwse-resize;
    }

    .sfk-designer-form {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .sfk-designer-form > .sfk-designer-group:first-child {
        margin-top: 0;
    }

    .sfk-designer-fields {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }

    .sfk-designer-prop {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }

    .sfk-designer-prop.is-wide {
        grid-column: span 2;
    }

    .sfk-designer-prop.is-check {
        flex-direction: row;
        align-items: center;
        align-self: end;
        min-height: 36px;
    }

    .sfk-designer-prop .sfk-filter-input,
    .sfk-designer-column-row .sfk-filter-input {
        width: 100%;
        height: 36px;
    }

    .sfk-designer-textarea {
        height: auto !important;
        min-height: 72px;
        resize: vertical;
    }

    .sfk-designer-color {
        width: 100%;
        height: 36px;
        padding: 2px;
        border: 1px solid var(--kt-border);
        background: #ffffff;
    }

    .sfk-designer-columns {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    /* a column of a table: [field][width] / [alignment][書式] / tools */
    .sfk-designer-column-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 64px;
        gap: 4px;
        padding: 6px;
        border: 1px solid var(--kt-border);
        background: #ffffff;
    }

    .sfk-designer-column-field {
        grid-column: span 2;
    }

    .sfk-designer-column-format {
        grid-column: span 2;
    }

    .sfk-designer-column-tools {
        display: flex;
        grid-column: span 3;
        justify-content: flex-end;
        gap: 4px;
    }

    .sfk-designer-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border: 1px solid var(--kt-border);
        background: var(--kt-normal);
        color: var(--kt-blue);
    }

    .sfk-designer-icon-btn:disabled {
        color: var(--kt-disabled);
        cursor: not-allowed;
    }

    .sfk-designer-icon {
        width: 16px;
        height: 16px;
    }

    .sfk-designer-element-actions {
        display: flex;
        gap: 16px;
        padding-top: 8px;
        border-top: 1px solid var(--kt-border);
    }
</style>
