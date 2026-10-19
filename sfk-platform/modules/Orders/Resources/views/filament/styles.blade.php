{{--
    Look of 04. 受注入力 parts on top of core::filament.theme (same colours, square corners, black labels): the wide
    legacy sub-tables scroll sideways, the 製袋 toggle, 集計サマリー above the list, 過去の受注履歴検索 and the
    受注書 preview.
--}}
<style>
    /* legacy label fields: the section labels carry the orange mark of the legacy form, group labels are plain */
    .sfk-order-label { display: flex; align-items: center; gap: 8px; color: var(--kt-label); font-size: 1rem; font-weight: 700; letter-spacing: .05em; }
    .sfk-order-label::before { content: ""; display: inline-block; width: 12px; height: 18px; background: rgb(255, 153, 0); }
    .sfk-order-sublabel { color: var(--kt-label); font-size: .9375rem; font-weight: 700; }
    .sfk-app-page-view .kt-subtable .fi-in-entry-content { padding: 4px 6px; }

    .sfk-order-bag-head { display: flex; align-items: center; gap: 16px; }
    .sfk-order-toggle { padding: 2px 4px; background: none; border: 0; color: var(--kt-blue); font-size: .8125rem; font-weight: 700; cursor: pointer; }
    .sfk-order-toggle:hover { color: var(--kt-blue-dark); text-decoration: underline; }
    .sfk-order-toggle:focus-visible { outline: 2px solid var(--kt-blue); outline-offset: 2px; }
    .sfk-app-page-print .sfk-order-toggle { display: none; }

    /* 集計サマリー */
    .sfk-order-summary { border-bottom: 2px solid var(--kt-blue-dark); background: var(--kt-blue); }
    .sfk-order-summary-bar {
        display: flex; align-items: center; gap: 10px; width: 100%; padding: 6px 16px; background: none; border: 0; color: #ffffff;
        font-size: .8125rem; text-align: start; cursor: pointer;
    }
    .sfk-order-summary-bar:focus-visible { outline: 2px solid #ffffff; outline-offset: -2px; }
    .sfk-order-summary-title { font-weight: 700; }
    .sfk-order-summary-count { padding: 1px 8px; background: rgba(255, 255, 255, .2); font-size: .75rem; }
    .sfk-order-summary-chevron { margin-inline-start: auto; font-size: .75rem; }
    .sfk-order-summary-body { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); margin: 0; background: #ffffff; border-top: 1px solid #ccdcef; }
    @media (max-width: 1024px) { .sfk-order-summary-body { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .sfk-order-summary-cell { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; padding: 8px 14px; border-inline-end: 1px solid #e5edf5; }
    .sfk-order-summary-cell:last-child { border-inline-end: 0; }
    .sfk-order-summary-cell dt { color: var(--kt-label); font-size: .75rem; white-space: nowrap; }
    .sfk-order-summary-cell dd { margin: 0; color: var(--kt-text); font-size: .875rem; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sfk-order-summary-cell dd.is-positive { color: #1a6e33; }
    .sfk-order-summary-cell dd.is-negative { color: var(--kt-red); }

    /* 過去の受注履歴検索 */
    .sfk-order-past { border: 1px solid #d8e2ed; background: #ffffff; }
    .sfk-order-past-head { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; background: var(--kt-blue); }
    .sfk-order-past-title { margin: 0; color: #ffffff; font-size: .875rem; font-weight: 700; }
    .sfk-order-past-toggle { padding: 4px 10px; background: rgba(255, 255, 255, .2); border: 0; color: #ffffff; font-size: .75rem; cursor: pointer; }
    .sfk-order-past-toggle:hover { background: rgba(255, 255, 255, .35); }
    .sfk-order-past-search { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; padding: 14px 16px; background: #f5f8fb; border-bottom: 1px solid #d8e2ed; }
    .sfk-order-past-field { display: flex; flex-direction: column; gap: 4px; color: var(--kt-label); font-size: .75rem; }
    .sfk-order-past-field input {
        min-width: 140px; padding: 6px 10px; border: 1px solid var(--kt-border); background: #ffffff; box-shadow: var(--kt-input-shadow);
        color: var(--kt-text); font-size: .8125rem;
    }
    .sfk-order-past-field input:focus { border-color: var(--kt-blue); outline: none; }
    .sfk-order-past-range { display: flex; align-items: center; gap: 6px; }
    .sfk-order-past-status { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 36px; padding: 6px 16px; font-size: .75rem; }
    .sfk-order-past-pager { display: flex; align-items: center; gap: 6px; }
    .sfk-order-past-page { padding: 2px 10px; border: 1px solid var(--kt-border); background: var(--kt-normal); color: var(--kt-blue); cursor: pointer; }
    .sfk-order-past-page:disabled { color: var(--kt-disabled); cursor: not-allowed; }
    .sfk-order-past-table { overflow-x: auto; }
    .sfk-order-past-table table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
    .sfk-order-past-table th { padding: 8px 12px; background: var(--kt-blue-soft); color: var(--kt-label); text-align: start; white-space: nowrap; }
    .sfk-order-past-table td { padding: 6px 12px; border-top: 1px solid var(--kt-border); color: var(--kt-text); }
    .sfk-order-past-table .is-number { text-align: end; font-variant-numeric: tabular-nums; }
    .sfk-order-past-table a { color: var(--kt-blue); }
    .sfk-order-past-icon { width: 1rem; height: 1rem; }

    /* フォームに反映する内容を確認 */
    .sfk-fax-preview { display: flex; flex-direction: column; gap: 12px; font-size: .8125rem; color: var(--kt-text); }
    .sfk-fax-source { margin: 0; color: var(--kt-label); }
    .sfk-fax-note { padding: 10px 12px; border: 1px solid; line-height: 1.65; }
    .sfk-fax-note ul { margin: 6px 0 0; padding-inline-start: 18px; list-style: disc; }
    .sfk-fax-note.is-error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .sfk-fax-note.is-warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .sfk-fax-note.is-info { background: var(--kt-blue-soft); border-color: #bfdbfe; color: #1e40af; }
    .sfk-fax-table { width: 100%; border-collapse: collapse; }
    .sfk-fax-table th { padding: 8px 10px; background: var(--kt-blue); color: #ffffff; font-weight: 400; text-align: start; }
    .sfk-fax-table td { padding: 8px 10px; border-bottom: 1px solid var(--kt-border); vertical-align: top; }
    .sfk-fax-table tr.is-warning td { background: #fffbeb; }
    .sfk-fax-label { width: 30%; color: var(--kt-label); white-space: nowrap; }
    .sfk-fax-value { display: block; word-break: break-all; }
    .sfk-fax-from { display: block; margin-top: 2px; color: var(--kt-muted); font-size: .75rem; }
    .sfk-fax-check { display: inline-block; margin-top: 4px; padding: 1px 7px; background: #fef3c7; color: #92400e; font-size: .75rem; }
    .sfk-fax-raw summary { cursor: pointer; color: var(--kt-label); }
    .sfk-fax-raw pre { max-height: 260px; margin: 8px 0 0; padding: 12px; overflow: auto; background: #1f2937; color: #e5e7eb; font-size: .6875rem; white-space: pre-wrap; word-break: break-all; }
</style>
