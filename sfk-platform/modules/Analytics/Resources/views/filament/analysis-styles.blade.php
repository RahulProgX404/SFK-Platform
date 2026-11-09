{{-- Styles of the 分析 pages (売上分析, 日報分析): the --sfk-* tokens of core::filament.design; the tables have the dark
     header of the app lists. --}}
<style>
    .sfk-an {
        display: flex;
        flex-direction: column;
        gap: var(--sfk-space-5);
        padding: var(--sfk-space-5) var(--sfk-gutter) var(--sfk-space-8);
    }

    .sfk-an-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--sfk-space-2);
    }

    .sfk-an-toolbar .sfk-list-tool {
        min-height: var(--sfk-control);
    }

    .sfk-an-seg {
        display: inline-flex;
        padding: 3px;
        border-radius: var(--sfk-radius);
        background: var(--sfk-hover-strong);
    }

    .sfk-an-seg button {
        min-width: 64px;
        height: calc(var(--sfk-control) - 6px);
        padding: 0 var(--sfk-space-3);
        border-radius: calc(var(--sfk-radius) - 2px);
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-label);
    }

    .sfk-an-seg button.is-active {
        background: var(--sfk-surface);
        box-shadow: var(--sfk-shadow);
        color: var(--sfk-ink);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-an-checks {
        display: flex;
        flex-direction: column;
        gap: 2px;
        max-height: 22rem;
        overflow-y: auto;
        padding: var(--sfk-space-2);
    }

    .sfk-an-checks label {
        display: flex;
        align-items: center;
        gap: var(--sfk-space-2);
        padding: 6px var(--sfk-space-2);
        border-radius: var(--sfk-radius);
        color: var(--sfk-text);
        font-size: var(--sfk-fs-body);
        white-space: nowrap;
        cursor: pointer;
    }

    .sfk-an-checks label:hover {
        background: var(--sfk-hover);
    }

    .sfk-an-checks-actions {
        display: flex;
        gap: var(--sfk-space-3);
        padding: var(--sfk-space-2) var(--sfk-space-4);
        border-top: 1px solid var(--sfk-line-soft);
    }

    .sfk-an-year {
        width: 160px;
    }

    .sfk-an-section {
        display: flex;
        flex-direction: column;
        gap: var(--sfk-space-3);
    }

    .sfk-an-heading {
        display: flex;
        align-items: baseline;
        gap: var(--sfk-space-3);
        margin: 0;
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-heading);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-an-heading small {
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-small);
        font-weight: var(--sfk-fw-regular);
        font-variant-numeric: tabular-nums;
    }

    .sfk-an-card {
        overflow-x: auto;
        border-radius: var(--sfk-radius-lg);
        background: var(--sfk-surface);
        box-shadow: var(--sfk-ring), var(--sfk-shadow);
    }

    .sfk-an-empty {
        padding: var(--sfk-space-8) var(--sfk-space-4);
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-body);
        text-align: center;
    }

    .sfk-an-table {
        width: 100%;
        min-width: 1180px;
        border-collapse: collapse;
        table-layout: fixed;
        font-variant-numeric: tabular-nums;
    }

    .sfk-an-table th,
    .sfk-an-table td {
        height: 40px;
        padding: 0 var(--sfk-space-3);
        text-align: right;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: var(--sfk-fs-body);
    }

    .sfk-an-table thead th {
        background: var(--sfk-list-head);
        color: #ffffff;
        font-size: var(--sfk-fs-label);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-an-table :is(th, td):first-child {
        position: sticky;
        left: 0;
        z-index: 1;
        width: 120px;
        text-align: left;
    }

    .sfk-an-table tbody td:first-child {
        background: inherit;
        color: var(--sfk-ink);
        font-weight: var(--sfk-fw-medium);
    }

    .sfk-an-table .is-total {
        width: 128px;
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-an-table .is-rank {
        width: 64px;
        text-align: center;
    }

    .sfk-an-table .is-name {
        width: 200px;
        text-align: left;
    }

    .sfk-an-table tbody .is-name {
        color: var(--sfk-ink);
    }

    .sfk-an-table tbody tr {
        background: var(--sfk-surface);
    }

    .sfk-an-table tbody tr+tr td {
        border-top: 1px solid var(--sfk-line-soft);
    }

    .sfk-an-table tbody tr:hover {
        background: var(--sfk-hover);
    }

    .sfk-an-table tbody tr.is-current td:first-child {
        box-shadow: inset 3px 0 0 var(--sfk-primary);
    }

    .sfk-an-table tbody tr.is-plan {
        background: var(--sfk-head);
    }

    .sfk-an-table tbody tr.is-plan td:first-child {
        color: var(--sfk-muted);
    }

    .sfk-an-table tbody tr.is-sum td {
        border-top: 1px solid var(--sfk-line-strong);
        background: var(--sfk-head);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-an-table td.is-future {
        color: var(--sfk-disabled-text);
    }

    /* 日報分析: the amount with the hours under it */
    .sfk-an-table.is-dual tbody td {
        height: 52px;
        line-height: 1.3;
    }

    .sfk-an-table.is-dual td small {
        display: block;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-badge);
    }

    .sfk-an-table td.is-total {
        background: var(--sfk-head);
    }

    .sfk-an-link {
        width: 100%;
        padding: 4px 0;
        border-radius: 4px;
        color: inherit;
        text-align: right;
    }

    .sfk-an-link:hover {
        color: var(--sfk-primary);
        text-decoration: underline;
    }

    .sfk-an-link.is-open {
        color: var(--sfk-primary);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-an-edit {
        width: 100%;
        padding: 4px 6px;
        /* border-radius: 4px; */
        color: var(--sfk-text);
        text-align: right;
        box-shadow: inset 0 -1px 0 var(--sfk-line-strong);
    }

    .sfk-an-edit:hover {
        /* background: var(--sfk-primary-soft); */
        color: var(--sfk-primary-dark);
    }

    .sfk-an-input {
        width: 100%;
        height: 30px;
        padding: 0 6px;
        border: 0;
        border-radius: 4px;
        background: var(--sfk-surface);
        box-shadow: 0 0 0 1px var(--sfk-primary), var(--sfk-focus);
        color: var(--sfk-ink);
        font: inherit;
        text-align: right;
    }

    .sfk-an-medal {
        display: inline-grid;
        place-items: center;
        width: 26px;
        height: 26px;
        border-radius: 9999px;
        background: var(--sfk-hover-strong);
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-badge);
        font-weight: var(--sfk-fw-bold);
    }

    .sfk-an-medal.is-1 {
        background: #fdf3e1;
        color: #b26a00;
    }

    .sfk-an-medal.is-2 {
        background: #eef1f5;
        color: #4b5563;
    }

    .sfk-an-medal.is-3 {
        background: #fbeee6;
        color: #a0522d;
    }

    .sfk-an-person {
        display: flex;
        flex-direction: column;
        gap: var(--sfk-space-2);
    }

    .sfk-an-person>summary {
        display: inline-flex;
        align-items: center;
        gap: var(--sfk-space-2);
        width: fit-content;
        list-style: none;
        cursor: pointer;
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-subheading);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-an-person>summary::-webkit-details-marker {
        display: none;
    }

    .sfk-an-person>summary .fi-icon {
        width: 16px;
        height: 16px;
        color: var(--sfk-muted);
        transition: transform .15s;
    }

    .sfk-an-person:not([open])>summary .fi-icon {
        transform: rotate(-90deg);
    }

    .sfk-an-person>summary small {
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-small);
        font-weight: var(--sfk-fw-regular);
        font-variant-numeric: tabular-nums;
    }

    /* 売上先別 明細: a panel on the right */
    .sfk-an-drawer-bg {
        position: fixed;
        inset: 0;
        z-index: 40;
        background: rgba(16, 24, 40, .18);
    }

    .sfk-an-drawer {
        position: fixed;
        inset: 0 0 0 auto;
        z-index: 41;
        display: flex;
        flex-direction: column;
        width: min(560px, 100vw);
        background: var(--sfk-surface);
        box-shadow: var(--sfk-shadow-lg);
    }

    .sfk-an-drawer-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: var(--sfk-space-3);
        padding: var(--sfk-space-5) var(--sfk-space-6) var(--sfk-space-4);
        border-bottom: 1px solid var(--sfk-line);
    }

    .sfk-an-drawer-head h2 {
        margin: 0;
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-heading);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-an-drawer-head p {
        margin: 2px 0 0;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-small);
    }

    .sfk-an-drawer-total {
        margin-top: var(--sfk-space-2) !important;
        color: var(--sfk-ink) !important;
        font-size: var(--sfk-fs-figure) !important;
        font-weight: var(--sfk-fw-bold);
        font-variant-numeric: tabular-nums;
    }

    .sfk-an-close {
        display: grid;
        place-items: center;
        width: 32px;
        height: 32px;
        border-radius: var(--sfk-radius);
        color: var(--sfk-muted);
    }

    .sfk-an-close:hover {
        background: var(--sfk-hover-strong);
        color: var(--sfk-ink);
    }

    .sfk-an-drawer-body {
        flex: 1;
        overflow-y: auto;
        padding: var(--sfk-space-3) var(--sfk-space-6) var(--sfk-space-6);
    }

    .sfk-an-customer {
        border-bottom: 1px solid var(--sfk-line-soft);
    }

    .sfk-an-customer>button {
        display: flex;
        align-items: center;
        gap: var(--sfk-space-3);
        width: 100%;
        padding: var(--sfk-space-3) 0;
        text-align: left;
    }

    .sfk-an-customer>button .fi-icon {
        width: 16px;
        height: 16px;
        flex: none;
        color: var(--sfk-subtle);
        transition: transform .15s;
    }

    .sfk-an-customer.is-open>button .fi-icon {
        transform: rotate(90deg);
        color: var(--sfk-primary);
    }

    .sfk-an-customer-name {
        flex: 1;
        min-width: 0;
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-body);
        overflow-wrap: anywhere;
    }

    .sfk-an-customer-name small {
        display: block;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-badge);
    }

    .sfk-an-customer-amount {
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-body);
        font-weight: var(--sfk-fw-semibold);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sfk-an-lines {
        width: 100%;
        margin: 0 0 var(--sfk-space-3);
        border-collapse: collapse;
        font-variant-numeric: tabular-nums;
    }

    .sfk-an-lines th,
    .sfk-an-lines td {
        padding: 6px var(--sfk-space-2);
        font-size: var(--sfk-fs-small);
        text-align: left;
    }

    .sfk-an-lines th {
        background: var(--sfk-table-head);
        color: var(--sfk-ink);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-an-lines td {
        border-bottom: 1px solid var(--sfk-line-soft);
        color: var(--sfk-text);
        background: var(--sfk-table-body)
    }


    .sfk-an-lines .is-amount {
        text-align: right;
        white-space: nowrap;
    }

    .sfk-an-lines .is-date {
        width: 96px;
        white-space: nowrap;
    }

    @media (max-width: 767px) {
        .sfk-an {
            padding-top: var(--sfk-space-4);
        }

        .sfk-an-drawer-head,
        .sfk-an-drawer-body {
            padding-inline: var(--sfk-space-4);
        }
    }
</style>
