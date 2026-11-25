{{--
    The look of the dashboard (core::filament.portal, PortalPage) and of its アプリ panel (core::filament.portal-app-order):
    part of the cached theme stylesheet (ThemeAssets), not of the page — a Livewire update of the dashboard (its filters,
    the live redraw) would otherwise send these ~40 KB again each time. Every rule is scoped to the dashboard's own classes.
--}}
<style>
    /* the page uses the whole content area (like the app screens), on the grey ground */
    .sfk-dashboard-page {
        background: var(--sfk-page);
    }

    /* ---- header (core::filament.portal-header) -------------------------------------------------------------- */
    .sfk-dash-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--sfk-space-3);
        padding: var(--sfk-space-5) var(--sfk-gutter) 0;
    }

    .sfk-dash-heading {
        margin: 0;
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-title);
        font-weight: var(--sfk-fw-bold);
    }

    .sfk-dash-header-tools {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--sfk-space-2);
    }

    .sfk-dash-scope {
        display: inline-flex;
        gap: 2px;
        height: var(--sfk-control);
        padding: 3px;
        border-radius: var(--sfk-radius);
        background: var(--sfk-surface);
        box-shadow: 0 0 0 1px var(--sfk-line-strong), var(--sfk-shadow);
    }

    .sfk-dash-scope-option {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0 var(--sfk-space-3);
        border-radius: 4px;
        color: var(--sfk-text);
        font-size: var(--sfk-fs-label);
        white-space: nowrap;
    }

    .sfk-dash-scope-option:hover {
        background: var(--sfk-hover-strong);
        color: var(--sfk-ink);
    }

    .sfk-dash-scope-option.is-active {
        background: var(--sfk-muted);
        color: #fff;
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-dash-scope-icon {
        width: 16px;
        height: 16px;
    }

    .sfk-dash-date {
        display: inline-flex;
        align-items: center;
        gap: var(--sfk-space-2);
        height: var(--sfk-control);
        padding: 0 var(--sfk-space-3);
        border-radius: var(--sfk-radius);
        background: var(--sfk-surface);
        box-shadow: 0 0 0 1px var(--sfk-line-strong), var(--sfk-shadow);
        color: var(--sfk-text);
        font-size: var(--sfk-fs-label);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sfk-dash-date:hover {
        box-shadow: 0 0 0 1px var(--sfk-primary), var(--sfk-shadow);
    }

    .sfk-dash-date-icon,
    .sfk-dash-date-chevron {
        width: 16px;
        height: 16px;
        color: var(--sfk-muted);
    }

    .sfk-dash-date-panel {
        display: flex;
        flex-direction: column;
        gap: var(--sfk-space-3);
        padding: var(--sfk-space-3);
    }

    .sfk-dash-date-presets {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 6px;
    }

    .sfk-dash-date-presets button {
        height: 32px;
        border-radius: var(--sfk-radius);
        background: var(--sfk-hover-strong);
        color: var(--sfk-text);
        font-size: var(--sfk-fs-small);
    }

    .sfk-dash-date-presets button:hover,
    .sfk-dash-date-presets button.is-active {
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary-dark);
    }

    .sfk-dash-date-presets button.is-active {
        box-shadow: inset 0 0 0 1px var(--sfk-primary);
        font-weight: var(--sfk-fw-semibold);
    }

    /* the date button names what it is: 選択期間 (and the preset it matches), so the cards' own periods stand apart */
    .sfk-dash-date-kind {
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-small);
    }

    .sfk-dash-date-short {
        display: none;
    }

    .sfk-dash-date-range {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--sfk-space-2);
    }

    .sfk-dash-date-range label {
        display: flex;
        flex-direction: column;
        gap: 4px;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-badge);
    }

    .sfk-dash-date-input {
        height: 34px;
        padding: 0 8px;
        border: 0;
        border-radius: var(--sfk-radius);
        box-shadow: 0 0 0 1px var(--sfk-line-strong);
        color: var(--sfk-ink);
        font: inherit;
        font-size: var(--sfk-fs-small);
    }

    .sfk-dash-date-input:focus {
        outline: none;
        box-shadow: 0 0 0 1px var(--sfk-primary), var(--sfk-focus);
    }

    .sfk-dash-date-actions {
        display: flex;
        justify-content: flex-end;
    }

    .sfk-dash-create {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: var(--sfk-control);
        padding: 0 var(--sfk-space-4);
        border-radius: var(--sfk-radius);
        background: var(--sfk-primary);
        color: #fff;
        font-size: var(--sfk-fs-label);
        font-weight: var(--sfk-fw-semibold);
        text-decoration: none;
        white-space: nowrap;
    }

    .sfk-dash-create:hover {
        background: var(--sfk-primary-dark);
        text-decoration: none;
    }

    .sfk-dash-create:disabled {
        background: var(--sfk-field-disabled);
        color: var(--sfk-disabled-text);
        cursor: not-allowed;
    }

    .sfk-dash-create-icon {
        width: 16px;
        height: 16px;
    }

    /* ---- the cards -------------------------------------------------------------------------------------------- */
    .sfk-dash {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: var(--sfk-space-5);
        padding: var(--sfk-space-5) var(--sfk-gutter) var(--sfk-space-8);
    }

    /* tablets: two columns; wide cards take the row, the small ones fill the gaps beside each other */
    @media (min-width: 768px) {
        .sfk-dash {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            grid-auto-flow: row dense;
        }

        .sfk-dash-span-5,
        .sfk-dash-span-6,
        .sfk-dash-span-8,
        .sfk-dash-span-12,
        .sfk-dash-t-full {
            grid-column: 1 / -1;
        }
    }

    @media (min-width: 768px) and (max-width: 1279px) {
        .sfk-dash-t-half {
            grid-column: auto;
        }
    }

    /* 12 columns of 5 tracks each, so a card may also take 30% / 20% of a row (sfk-dash-span-30p / -20p); the space
       between the cards is their own padding (a gap between 60 tracks would eat the row) */
    @media (min-width: 1280px) {
        .sfk-dash {
            grid-template-columns: repeat(60, minmax(0, 1fr));
            grid-auto-flow: row;
            column-gap: 0;
            padding-inline: calc(var(--sfk-gutter) - var(--sfk-space-5) / 2);
        }

        .sfk-dash>* {
            padding-inline: calc(var(--sfk-space-5) / 2);
        }

        .sfk-dash-span-2 {
            grid-column: span 10;
        }

        .sfk-dash-span-3 {
            grid-column: span 15;
        }

        .sfk-dash-span-4 {
            grid-column: span 20;
        }

        .sfk-dash-span-5 {
            grid-column: span 25;
        }

        .sfk-dash-span-6 {
            grid-column: span 30;
        }

        .sfk-dash-span-8 {
            grid-column: span 40;
        }

        .sfk-dash-span-30p {
            grid-column: span 18;
        }

        .sfk-dash-span-20p {
            grid-column: span 12;
        }

        .sfk-dash-span-12 {
            grid-column: 1 / -1;
        }
    }

    .sfk-dash-cell {
        display: flex;
        min-width: 0;
    }

    .sfk-dash-cell>* {
        flex: 1;
        min-width: 0;
    }

    /* lists (rankings, people, activity): not stretched to the tallest card of the row */
    .sfk-dash-cell.is-fit {
        align-self: start;
    }

    /* …but always as high as their full list (user 2026-11-17: the same height with all rows, fewer or none): an
       invisible copy of the full list (analytics::widgets.partials.ghost) and the real content share one grid cell, so
       the cell keeps the full height; the real rows fill it from the top, 「…はありません」 sits in its middle */
    .sfk-dash-stack {
        display: grid;
    }

    .sfk-dash-stack>* {
        grid-area: 1 / 1;
        min-width: 0;
    }

    .sfk-dash-ghost {
        visibility: hidden;
        pointer-events: none;
        user-select: none;
    }

    .sfk-dash-stack>.sfk-dash-empty {
        align-self: center;
        margin: 0;
    }

    .sfk-dash-card {
        display: flex;
        flex-direction: column;
        padding: var(--sfk-space-5);
        border-radius: var(--sfk-radius-xl);
        background: var(--sfk-surface);
        box-shadow: var(--sfk-ring), var(--sfk-shadow-md);
    }

    .sfk-dash-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--sfk-space-3);
        margin-bottom: var(--sfk-space-4);
    }

    .sfk-dash-card-title {
        margin: 0;
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-heading);
        font-weight: var(--sfk-fw-bold);
    }

    .sfk-dash-more {
        display: grid;
        place-items: center;
        width: 32px;
        height: 32px;
        flex: none;
        border-radius: 9999px;
        box-shadow: 0 0 0 1px var(--sfk-line-strong);
        color: var(--sfk-ink);
    }

    .sfk-dash-more:hover {
        background: var(--sfk-primary);
        box-shadow: none;
        color: #fff;
    }

    .sfk-dash-more .fi-icon {
        width: 16px;
        height: 16px;
    }

    .sfk-dash-period {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: var(--sfk-radius);
        box-shadow: 0 0 0 1px var(--sfk-line);
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-badge);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sfk-dash-period .fi-icon {
        width: 14px;
        height: 14px;
    }

    .sfk-dash-trend {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        padding: 1px 8px;
        border-radius: 9999px;
        background: var(--sfk-success-soft);
        color: var(--sfk-success);
        font-size: var(--sfk-fs-badge);
        font-weight: var(--sfk-fw-semibold);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sfk-dash-trend.is-down {
        background: var(--sfk-danger-soft);
        color: var(--sfk-danger-text);
    }

    .sfk-dash-empty {
        margin: auto 0;
        padding: var(--sfk-space-6) 0;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-small);
        text-align: center;
    }

    /* avatars: initials in a circle */
    .sfk-dash-avatar {
        display: grid;
        place-items: center;
        width: 40px;
        height: 40px;
        flex: none;
        border-radius: 9999px;
        background: var(--sfk-surface);
        box-shadow: 0 0 0 2px var(--sfk-surface), 0 2px 6px rgba(16, 24, 40, .12);
        color: var(--sfk-primary-dark);
        font-size: var(--sfk-fs-small);
        font-weight: var(--sfk-fw-bold);
    }

    .sfk-dash-avatar.is-solid {
        color: #fff;
        box-shadow: none;
    }

    .sfk-dash-avatar.is-solid.is-1 {
        background: var(--sfk-primary);
    }

    .sfk-dash-avatar.is-solid.is-2 {
        background: #16956b;
    }

    .sfk-dash-avatar.is-solid.is-3 {
        background: #d48806;
    }

    .sfk-dash-avatar.is-solid.is-4 {
        background: #6e56cf;
    }

    .sfk-dash-avatar.is-solid.is-5 {
        background: #0b8aa3;
    }

    /* a ranking: rows on a soft gradient, the figure in a white circle on the right */
    .sfk-dash-rank {
        display: flex;
        flex-direction: column;
        gap: var(--sfk-space-3);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .sfk-dash-rank-row {
        --sfk-rank: var(--sfk-primary-soft);
        display: flex;
        align-items: center;
        gap: var(--sfk-space-3);
        min-height: 56px;
        padding: 6px var(--sfk-space-3) 6px 6px;
        border-radius: 9999px;
        background: linear-gradient(90deg, var(--sfk-rank) 0%, var(--sfk-rank) 45%, rgba(255, 255, 255, 0) 100%);
    }

    .sfk-dash-rank-row.is-2 {
        --sfk-rank: #e3f1fb;
    }

    .sfk-dash-rank-row.is-3 {
        --sfk-rank: #e2f5ec;
    }

    .sfk-dash-rank-row.is-4 {
        --sfk-rank: #fbf2df;
    }

    .sfk-dash-rank-row.is-5 {
        --sfk-rank: #fde7e7;
    }

    .sfk-dash-rank-text {
        flex: 1;
        min-width: 0;
    }

    .sfk-dash-rank-text strong,
    .sfk-dash-people-text strong {
        display: block;
        overflow: hidden;
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-label);
        font-weight: var(--sfk-fw-semibold);
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sfk-dash-rank-text small,
    .sfk-dash-people-text small {
        display: block;
        overflow: hidden;
        color: var(--sfk-text);
        font-size: var(--sfk-fs-badge);
        font-variant-numeric: tabular-nums;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sfk-dash-rank-count {
        flex: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--sfk-text);
        font-size: var(--sfk-fs-badge);
        white-space: nowrap;
    }

    .sfk-dash-rank-count b {
        display: grid;
        place-items: center;
        min-width: 36px;
        height: 36px;
        padding: 0 6px;
        border-radius: 9999px;
        background: var(--sfk-surface);
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-label);
        font-weight: var(--sfk-fw-bold);
        font-variant-numeric: tabular-nums;
    }

    /* people: avatar, name and a line under it, the figure on the right */
    .sfk-dash-people {
        display: flex;
        flex-direction: column;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .sfk-dash-people li {
        display: flex;
        align-items: center;
        gap: var(--sfk-space-3);
        min-height: 58px;
    }

    .sfk-dash-people li+li {
        border-top: 1px solid var(--sfk-line-soft);
    }

    .sfk-dash-people.is-compact li {
        min-height: 50px;
    }

    .sfk-dash-people.is-compact .sfk-dash-avatar {
        width: 34px;
        height: 34px;
        font-size: var(--sfk-fs-badge);
    }

    .sfk-dash-people-text {
        flex: 1;
        min-width: 0;
        color: inherit;
        text-decoration: none;
    }

    a.sfk-dash-people-text:hover strong {
        color: var(--sfk-primary);
    }

    .sfk-dash-people-figure {
        flex: none;
        text-align: right;
    }

    .sfk-dash-people-figure small {
        display: block;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-badge);
    }

    .sfk-dash-people-figure strong {
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-label);
        font-weight: var(--sfk-fw-bold);
        font-variant-numeric: tabular-nums;
    }

    /* the title of a card with its period (the dashboard's date button) under it */
    .sfk-dash-card-titles {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }

    .sfk-dash-caption {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-badge);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    /* what a card's period is: 選択期間 (the date button, blue) or the card's own (暦年・月別, 今日・明日 — grey) */
    .sfk-dash-tag {
        display: inline-flex;
        align-items: center;
        padding: 0 6px;
        border-radius: 4px;
        background: var(--sfk-hover-strong);
        color: var(--sfk-text);
        font-size: var(--sfk-fs-badge);
        font-weight: var(--sfk-fw-medium);
        line-height: 18px;
    }

    .sfk-dash-tag.is-selected {
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary-dark);
    }

    /* how far the sales data goes, when a figure reaches past it (no percentage then) */
    .sfk-dash-datanote {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: var(--sfk-warning);
        font-size: var(--sfk-fs-badge);
        font-weight: var(--sfk-fw-medium);
        white-space: nowrap;
    }

    .sfk-dash-datanote .fi-icon {
        width: 14px;
        height: 14px;
        flex: none;
    }

    /* a comparison chip (analytics::widgets.partials.change): green ▲ up, red ▼ down, grey ± — on every card; what it is
       compared with is part of its text (前期間比, 前年同期比 …) */
    .sfk-dash-trend.is-flat {
        background: var(--sfk-hover);
        color: var(--sfk-text);
    }

    /* a ranking: the place, the name and a line under it, the amount on the right and a bar against the first */
    .sfk-dash-ranking {
        display: flex;
        flex-direction: column;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .sfk-dash-ranking>li {
        display: grid;
        grid-template-columns: 24px minmax(0, 1fr) auto;
        align-items: center;
        gap: 6px var(--sfk-space-3);
        padding: 10px 0;
    }

    .sfk-dash-ranking>li+li {
        border-top: 1px solid var(--sfk-line-soft);
    }

    .sfk-dash-ranking>li:first-child {
        padding-top: 2px;
    }

    .sfk-dash-rankno {
        display: grid;
        grid-row: span 2;
        place-items: center;
        width: 24px;
        height: 24px;
        border-radius: var(--sfk-radius);
        background: var(--sfk-hover-strong);
        color: var(--sfk-text);
        font-size: var(--sfk-fs-badge);
        font-weight: var(--sfk-fw-bold);
        font-variant-numeric: tabular-nums;
    }

    .sfk-dash-rankno.is-1 {
        background: var(--sfk-primary);
        color: #fff;
    }

    .sfk-dash-rankno.is-2,
    .sfk-dash-rankno.is-3 {
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary);
    }

    .sfk-dash-ranking-name {
        min-width: 0;
        color: inherit;
        text-decoration: none;
    }

    .sfk-dash-ranking-name strong {
        display: block;
        overflow: hidden;
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-body);
        font-weight: var(--sfk-fw-semibold);
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    a.sfk-dash-ranking-name:hover strong {
        color: var(--sfk-primary);
    }

    .sfk-dash-ranking-name small,
    .sfk-dash-ranking-figure small {
        display: block;
        overflow: hidden;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-badge);
        font-variant-numeric: tabular-nums;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sfk-dash-ranking-figure {
        text-align: right;
    }

    .sfk-dash-ranking-figure strong {
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-label);
        font-weight: var(--sfk-fw-bold);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sfk-dash-meter {
        grid-column: 2 / -1;
        height: 4px;
        overflow: hidden;
        border-radius: 9999px;
        background: var(--sfk-line-soft);
    }

    .sfk-dash-meter i {
        display: block;
        height: 100%;
        border-radius: 9999px;
        background: var(--sfk-primary);
    }

    .sfk-dash-ranking>li:not(:first-child) .sfk-dash-meter i {
        background: color-mix(in srgb, var(--sfk-primary) 55%, white);
    }

    /* the signed-in user's own row (the ranking of workers) */
    .sfk-dash-ranking>li.is-me {
        margin: 0 calc(-1 * var(--sfk-space-2));
        padding-right: var(--sfk-space-2);
        padding-left: var(--sfk-space-2);
        border-radius: var(--sfk-radius-lg);
        background: var(--sfk-primary-soft);
    }

    /* ---- アプリ: the panel over the whole screen ------------------------------------------------------------- */
    .sfk-apps {
        position: fixed;
        inset: 0;
        z-index: 35;
        display: flex;
        flex-direction: column;
        background: var(--sfk-page);
    }

    .sfk-apps-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--sfk-space-3);
        padding: var(--sfk-space-4) var(--sfk-gutter);
        background: var(--sfk-surface);
        box-shadow: 0 1px 0 var(--sfk-line);
    }

    .sfk-apps-title {
        margin: 0;
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-title);
        font-weight: var(--sfk-fw-bold);
    }

    .sfk-apps-search {
        position: relative;
        flex: 1 1 320px;
        max-width: 560px;
    }

    .sfk-apps-search .fi-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        width: 18px;
        height: 18px;
        color: var(--sfk-subtle);
        transform: translateY(-50%);
    }

    .sfk-apps-search input {
        width: 100%;
        height: 42px;
        padding: 0 12px 0 40px;
        border: 0;
        border-radius: 9999px;
        background: var(--sfk-page);
        box-shadow: inset 0 0 0 1px var(--sfk-line);
        color: var(--sfk-ink);
        font: inherit;
        font-size: var(--sfk-fs-body);
    }

    .sfk-apps-search input:focus {
        outline: none;
        background: var(--sfk-surface);
        box-shadow: inset 0 0 0 1px var(--sfk-primary), var(--sfk-focus);
    }

    .sfk-apps-tools {
        display: flex;
        align-items: center;
        gap: var(--sfk-space-1);
        margin-left: auto;
    }

    .sfk-apps-close {
        display: grid;
        place-items: center;
        width: 40px;
        height: 40px;
        border-radius: 9999px;
        color: var(--sfk-muted);
    }

    .sfk-apps-close:hover {
        background: var(--sfk-hover-strong);
        color: var(--sfk-ink);
    }

    .sfk-apps-close .fi-icon {
        width: 22px;
        height: 22px;
    }

    .sfk-apps-body {
        flex: 1;
        display: grid;
        grid-template-columns: 260px minmax(0, 1fr);
        min-height: 0;
    }

    .sfk-apps-side {
        overflow-y: auto;
        padding: var(--sfk-space-4);
        border-right: 1px solid var(--sfk-line);
        background: var(--sfk-surface);
    }

    .sfk-apps-side-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--sfk-space-2);
        margin: 0 0 var(--sfk-space-2);
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-small);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-apps-scope {
        width: 100%;
        margin-bottom: var(--sfk-space-2);
        padding: 6px 8px;
        border: 0;
        border-radius: var(--sfk-radius);
        background: var(--sfk-page);
        color: var(--sfk-primary);
        font: inherit;
        font-size: var(--sfk-fs-small);
    }

    .sfk-apps-main {
        overflow-y: auto;
        padding: var(--sfk-space-5) var(--sfk-gutter) var(--sfk-space-8);
    }

    .sfk-apps-section+.sfk-apps-section {
        margin-top: var(--sfk-space-6);
    }

    .sfk-apps-section h3 {
        display: flex;
        align-items: center;
        gap: var(--sfk-space-2);
        margin: 0 0 var(--sfk-space-3);
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-subheading);
        font-weight: var(--sfk-fw-semibold);
    }

    .sfk-apps-section h3 small {
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-badge);
        font-weight: var(--sfk-fw-regular);
    }

    .sfk-apps-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: var(--sfk-space-2);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    /* a tile: the app, and ⠿ on its right to drag it (the user's own order) */
    .sfk-apps-tile {
        position: relative;
    }

    .sfk-apps-tile .sfk-row {
        padding-right: 2.5rem;
    }

    .sfk-apps-handle {
        position: absolute;
        right: 6px;
        top: 50%;
        z-index: 1;
        display: grid;
        place-items: center;
        width: 28px;
        height: 28px;
        border-radius: var(--sfk-radius);
        color: var(--sfk-disabled-text);
        cursor: grab;
        transform: translateY(-50%);
    }

    .sfk-apps-handle .fi-icon {
        width: 16px;
        height: 16px;
    }

    .sfk-apps-tile:hover .sfk-apps-handle,
    .sfk-apps-handle:focus-visible {
        color: var(--sfk-muted);
    }

    .sfk-apps-handle:hover {
        background: var(--sfk-hover-strong);
        color: var(--sfk-primary) !important;
    }

    .sfk-apps-tile.fi-sortable-ghost {
        opacity: .4;
    }

    .sfk-apps-order-tools {
        display: inline-flex;
        flex-wrap: wrap;
        gap: var(--sfk-space-1);
        margin-left: auto;
    }

    .sfk-apps-order-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        height: 30px;
        padding: 0 10px;
        border-radius: var(--sfk-radius);
        color: var(--sfk-primary);
        font-size: var(--sfk-fs-small);
        font-weight: var(--sfk-fw-medium);
    }

    .sfk-apps-order-btn:hover {
        background: var(--sfk-primary-soft);
    }

    .sfk-apps-order-btn .fi-icon {
        width: 16px;
        height: 16px;
    }

    .sfk-apps-none {
        margin: 0;
        padding: var(--sfk-space-6) 0;
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-small);
        text-align: center;
    }

    /* the rows and tiles of the panel (spaces, apps, shortcuts) */
    .sfk-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .sfk-space-item {
        display: flex;
        align-items: center;
        border-radius: var(--sfk-radius);
    }

    .sfk-space-item .sfk-row {
        flex: 1;
        min-width: 0;
    }

    .sfk-space-settings {
        display: grid;
        place-items: center;
        width: 2rem;
        height: 2rem;
        flex-shrink: 0;
        border-radius: var(--sfk-radius);
        color: var(--sfk-subtle);
    }

    .sfk-space-settings:hover {
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary);
    }

    .sfk-space-settings svg {
        width: 1.125rem;
        height: 1.125rem;
    }

    .sfk-row {
        display: flex;
        align-items: center;
        gap: var(--sfk-space-3);
        width: 100%;
        min-height: 2.75rem;
        padding: var(--sfk-space-2) var(--sfk-space-3);
        border-radius: var(--sfk-radius);
        color: var(--sfk-text);
        font-size: var(--sfk-fs-label);
        text-align: left;
        text-decoration: none;
    }

    a.sfk-row:hover,
    button.sfk-row:hover {
        background: var(--sfk-hover) !important;
        color: var(--sfk-primary);
        text-decoration: none;
    }

    .sfk-row.is-selected {
        background: var(--sfk-primary-soft);
        box-shadow: inset 3px 0 0 var(--sfk-primary);
        color: var(--sfk-primary-dark);
    }

    .sfk-apps-grid .sfk-row {
        min-height: 3.25rem;
        background: var(--sfk-surface);
        box-shadow: var(--sfk-ring);
    }

    .sfk-apps-grid a.sfk-row:hover {
        box-shadow: 0 0 0 1px var(--sfk-primary-soft-strong), var(--sfk-shadow-md);
    }

    .sfk-row-name {
        flex: 1;
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .sfk-row-muted {
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-small);
    }

    .sfk-space-icon {
        width: 1.5rem;
        height: 1.5rem;
        flex-shrink: 0;
        border-radius: var(--sfk-radius);
        background: linear-gradient(135deg, #3fbf8c, #16956b);
    }

    .sfk-space-icon.is-all {
        background: linear-gradient(135deg, #2b6aa0, var(--sfk-primary));
    }

    .sfk-app-icon {
        display: grid;
        place-items: center;
        width: 2rem;
        height: 2rem;
        flex-shrink: 0;
        overflow: hidden;
        border-radius: var(--sfk-radius);
        background: #3c78b4;
        color: #fff;
        font-size: .625rem;
        font-weight: var(--sfk-fw-bold);
        line-height: 1;
        letter-spacing: -.02em;
    }

    .sfk-lock {
        width: .75rem;
        height: .75rem;
        color: var(--sfk-subtle);
        flex-shrink: 0;
    }

    .sfk-apps .sfk-empty {
        padding: var(--sfk-space-3);
        color: var(--sfk-muted);
        font-size: var(--sfk-fs-small);
    }

    .sfk-add {
        display: grid;
        place-items: center;
        width: 2rem;
        height: 2rem;
        border-radius: var(--sfk-radius);
        color: var(--sfk-muted);
    }

    .sfk-add:hover {
        background: var(--sfk-primary-soft);
        color: var(--sfk-primary);
    }

    .sfk-add svg {
        width: 1.25rem;
        height: 1.25rem;
    }

    /* apps that are not 公開中 (shown to super admins): dimmed */
    .sfk-unpublished {
        opacity: .45;
    }

    .sfk-unpublished:hover {
        opacity: .8;
    }

    .sfk-shortcuts {
        display: flex;
        flex-wrap: wrap;
        gap: var(--sfk-space-3);
    }

    .sfk-shortcut {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--sfk-space-2);
        width: 7.5rem;
        padding: var(--sfk-space-3) var(--sfk-space-2);
        border-radius: var(--sfk-radius-lg);
        background: var(--sfk-surface);
        box-shadow: var(--sfk-ring);
        color: var(--sfk-ink);
        font-size: var(--sfk-fs-small);
        font-weight: var(--sfk-fw-semibold);
        text-align: center;
        text-decoration: none;
    }

    a.sfk-shortcut:hover {
        box-shadow: 0 0 0 1px var(--sfk-primary-soft-strong), var(--sfk-shadow-md);
        color: var(--sfk-primary);
        text-decoration: none;
    }

    .sfk-shortcut-tile {
        display: grid;
        place-items: center;
        width: 3.25rem;
        height: 3.25rem;
        border-radius: var(--sfk-radius-xl);
        background: linear-gradient(135deg, #2b6aa0, var(--sfk-primary));
        color: #fff;
        font-size: var(--sfk-fs-subheading);
        font-weight: var(--sfk-fw-bold);
    }

    .sfk-shortcut.is-disabled {
        color: var(--sfk-muted);
    }

    .sfk-shortcut.is-disabled .sfk-shortcut-tile {
        background: #b8c4cc;
    }

    /* アプリの並び替え (⚙, administrators) */
    .sfk-app-order {
        margin: 0;
        padding: 0;
        list-style: none;
        overflow: hidden;
        border-radius: var(--sfk-radius-lg);
        box-shadow: var(--sfk-ring);
    }

    .sfk-app-order-row {
        display: flex;
        align-items: center;
        gap: .625rem;
        min-height: 2.75rem;
        padding: 0 .75rem 0 .25rem;
        background: var(--sfk-surface);
        border-top: 1px solid var(--sfk-line-soft);
        font-size: var(--sfk-fs-label);
    }

    .sfk-app-order-row:first-child {
        border-top: 0;
    }

    .sfk-app-order-row:hover {
        background: var(--sfk-hover);
    }

    .sfk-app-order-row.fi-sortable-ghost {
        opacity: .4;
    }

    .sfk-app-order-handle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 2.25rem;
        color: var(--sfk-subtle);
        cursor: grab;
    }

    .sfk-app-order-handle:hover {
        color: var(--sfk-primary);
    }

    .sfk-app-order-handle-icon {
        width: 1.125rem;
        height: 1.125rem;
    }

    .sfk-app-order-name {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* phones: the spaces become a row of chips above the apps, one app per row */
    @media (max-width: 767px) {
        .sfk-dash-header {
            padding-top: var(--sfk-space-4);
        }

        .sfk-dash-header-tools {
            width: 100%;
        }

        /* 全体 | 個人 and 新規作成 on one row, the date button on a row of its own under them */
        .sfk-dash-header-tools>.sfk-dash-date-menu {
            order: 3;
            flex: 1 1 100%;
            min-width: 0;
        }

        .sfk-dash-scope {
            flex: 1;
            width: auto;
        }

        .sfk-dash-date-long {
            display: none;
        }

        .sfk-dash-date-short {
            display: inline;
        }

        .sfk-dash-scope-option {
            flex: 1;
            justify-content: center;
        }

        .sfk-dash-date {
            width: 100%;
            justify-content: flex-start;
        }

        .sfk-dash-date-chevron {
            margin-left: auto;
        }

        .sfk-dash-date-presets button {
            height: var(--sfk-control);
        }

        .sfk-dash {
            gap: var(--sfk-space-4);
            padding-top: var(--sfk-space-4);
        }

        .sfk-dash-card {
            padding: var(--sfk-space-4);
        }

        .sfk-apps-head {
            padding: var(--sfk-space-3) var(--sfk-space-4);
        }

        .sfk-apps-search {
            order: 3;
            flex-basis: 100%;
            max-width: none;
        }

        .sfk-apps-body {
            grid-template-columns: minmax(0, 1fr);
            grid-template-rows: auto minmax(0, 1fr);
        }

        .sfk-apps-side {
            display: flex;
            gap: var(--sfk-space-2);
            overflow-x: auto;
            overflow-y: hidden;
            padding: var(--sfk-space-2) var(--sfk-space-4);
            border-right: 0;
            border-bottom: 1px solid var(--sfk-line);
            scrollbar-width: none;
        }

        .sfk-apps-side::-webkit-scrollbar {
            display: none;
        }

        .sfk-apps-side-head,
        .sfk-apps-scope-wrap {
            display: none;
        }

        .sfk-apps-side .sfk-list {
            display: flex;
            gap: var(--sfk-space-2);
        }

        .sfk-apps-side .sfk-row {
            min-height: 2.25rem;
            padding: 0 var(--sfk-space-3);
            border-radius: 9999px;
            box-shadow: var(--sfk-ring);
            white-space: nowrap;
        }

        .sfk-apps-side .sfk-space-icon,
        .sfk-apps-side .sfk-lock,
        .sfk-apps-side .sfk-space-settings {
            display: none;
        }

        .sfk-apps-main {
            padding: var(--sfk-space-4);
        }

        .sfk-apps-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .sfk-shortcut {
            width: calc(33.333% - var(--sfk-space-2));
        }
    }
</style>
