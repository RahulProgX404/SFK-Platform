{{--
    The dashboard (Modules\Core\Filament\Portal\PortalPage; design_ref/Dashboard.png): the cards the business modules give
    the panel (widgets, each drawn in `sfk-dash-span-<n>` of a 12-column grid; 2 columns on tablets, 1 on phones), and
    アプリ — the panel over the whole screen opened from the top bar (event `sfk-open-apps`): search, スペース,
    よく使うアプリ and the apps in the user's own order. The card frame (sfk-dash-card …) is shared by the widgets.
--}}
<x-filament-panels::page>
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

        .sfk-dash-date-presets button:hover {
            background: var(--sfk-primary-soft);
            color: var(--sfk-primary-dark);
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

        @media (min-width: 768px) {
            .sfk-dash {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .sfk-dash-span-6,
            .sfk-dash-span-12 {
                grid-column: 1 / -1;
            }
        }

        @media (min-width: 1280px) {
            .sfk-dash {
                grid-template-columns: repeat(12, minmax(0, 1fr));
            }

            .sfk-dash-span-2 {
                grid-column: span 2;
            }

            .sfk-dash-span-3 {
                grid-column: span 3;
            }

            .sfk-dash-span-4 {
                grid-column: span 4;
            }

            .sfk-dash-span-5 {
                grid-column: span 5;
            }

            .sfk-dash-span-6 {
                grid-column: span 6;
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

        .sfk-empty {
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

            .sfk-dash-header-tools>.sfk-dash-date-menu {
                flex: 1;
                min-width: 0;
            }

            .sfk-dash-scope {
                width: 100%;
            }

            .sfk-dash-scope-option {
                flex: 1;
                justify-content: center;
            }

            .sfk-dash-date {
                width: 100%;
                justify-content: space-between;
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

    {{-- the cards --}}
    <div class="sfk-dash">
        @foreach ($this->dashboardWidgets() as $widget)
            @livewire($widget, ['pageFilters' => $this->filters], key($widget))
        @endforeach
    </div>

    {{-- アプリ: opened by the button of the top bar (core::filament.app-launcher-button) --}}
    <div class="sfk-apps" role="dialog" aria-modal="true" aria-labelledby="sfk-apps-title" x-data="{ open: false, q: '' }"
        x-show="open" x-cloak x-on:sfk-open-apps.window="open = true; setTimeout(() => $refs.q.focus(), 50)"
        x-on:keydown.escape.window="open = false"
        x-effect="document.documentElement.style.overflow = open ? 'hidden' : ''" x-transition.opacity.duration.150ms>
        <div class="sfk-apps-head">
            <h2 class="sfk-apps-title" id="sfk-apps-title">アプリ</h2>
            <label class="sfk-apps-search">
                <x-filament::icon icon="heroicon-o-magnifying-glass" />
                <span class="fi-sr-only">アプリを検索</span>
                <input type="search" x-ref="q" x-model="q" placeholder="アプリ名で検索" autocomplete="off"
                    x-on:keydown.enter.prevent="const first = $root.querySelector('.sfk-apps-grid li:not([hidden]) a'); if (first) window.location.href = first.href">
            </label>
            <div class="sfk-apps-tools">
                @if ($appsSettingsUrl !== null)
                    <a href="{{ $appsSettingsUrl }}" class="sfk-add" title="アプリ設定" aria-label="アプリ設定">
                        <x-filament::icon icon="heroicon-m-plus" />
                    </a>
                @endif
                @if ($this->reorderAppsAction->isVisible())
                    {{ $this->reorderAppsAction }}
                @endif
                <button type="button" class="sfk-apps-close" x-on:click="open = false" title="閉じる">
                    <x-filament::icon icon="heroicon-o-x-mark" />
                    <span class="fi-sr-only">閉じる</span>
                </button>
            </div>
        </div>

        <div class="sfk-apps-body">
            {{-- スペース: filter the apps --}}
            <nav class="sfk-apps-side" aria-label="スペース">
                <p class="sfk-apps-side-head">
                    <span>スペース</span>
                    @if ($spacesUrl !== null)
                        <a href="{{ $spacesUrl }}" class="sfk-add" title="スペースを作成・管理" aria-label="スペースを作成・管理">
                            <x-filament::icon icon="heroicon-m-plus" />
                        </a>
                    @endif
                </p>
                @if ($isAdministrator)
                    <div class="sfk-apps-scope-wrap">
                        <select class="sfk-apps-scope" wire:model.live="spaceScope" aria-label="表示するスペース">
                            <option value="joined">参加中のスペース</option>
                            <option value="all">すべてのスペース</option>
                        </select>
                    </div>
                @endif
                <ul class="sfk-list">
                    <li>
                        <button type="button" wire:click="$set('appSpace', null)" @class(['sfk-row', 'is-selected' => $appSpace === null])>
                            <span class="sfk-space-icon is-all" aria-hidden="true"></span>
                            <span class="sfk-row-name">すべてのアプリ</span>
                        </button>
                    </li>
                    @foreach ($spaces as $space)
                        <li wire:key="space-{{ $space->id }}" class="sfk-space-item">
                            <button type="button" wire:click="showSpace({{ $space->id }})"
                                @class(['sfk-row', 'is-selected' => $appSpace === $space->id])>
                                <span class="sfk-space-icon" aria-hidden="true"></span>
                                <x-filament::icon icon="heroicon-s-lock-closed" class="sfk-lock" />
                                <span class="sfk-row-name">{{ $space->name }}</span>
                            </button>
                            @if (isset($spaceSettingsUrls[$space->id]))
                                <a href="{{ $spaceSettingsUrls[$space->id] }}" class="sfk-space-settings"
                                    title="スペースの設定（{{ $space->name }}）">
                                    <x-filament::icon icon="heroicon-o-cog-6-tooth" />
                                    <span class="fi-sr-only">スペースの設定（{{ $space->name }}）</span>
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($spaces->isEmpty())
                    <p class="sfk-empty">{{ $spaceScope === 'all' ? 'スペースはありません。' : '参加中のスペースはありません。' }}</p>
                @endif
            </nav>

            <div class="sfk-apps-main">
                {{-- よく使うアプリ: the five apps this user opens most --}}
                @if ($mostUsed->isNotEmpty())
                    <section class="sfk-apps-section" aria-labelledby="sfk-most-used-title" x-show="! q">
                        <h3 id="sfk-most-used-title">よく使うアプリ</h3>
                        <div class="sfk-shortcuts">
                            @foreach ($mostUsed as $app)
                                <a href="{{ $appUrls[$app->id] }}" @class([
                                    'sfk-shortcut',
                                    'sfk-unpublished' =>
                                        $app->status !== \Modules\Core\Domain\Apps\AppStatus::Published,
                                ])>
                                    <span class="sfk-shortcut-tile" aria-hidden="true">{{ $app->iconText() }}</span>
                                    <span>{{ $app->displayName() }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- アプリ: all, or those of the chosen space, in the user's own order (drag ⠿ to change it, theirs only;
                     administrators' drag changes the default order of everyone); the search narrows them as you type --}}
                <section class="sfk-apps-section" aria-labelledby="sfk-app-title">
                    <h3 id="sfk-app-title">
                        アプリ <small>{{ $apps->count() }}件</small>
                        @if ($hasOwnOrder)
                            <span class="sfk-apps-order-tools" x-show="q === ''">
                                <button type="button" class="sfk-apps-order-btn" wire:click="resetMyAppOrder"
                                    wire:loading.attr="disabled">
                                    <x-filament::icon icon="heroicon-m-arrow-uturn-left" />既定の並びに戻す
                                </button>
                            </span>
                        @endif
                    </h3>
                    @if ($apps->isEmpty())
                        <p class="sfk-apps-none">利用できるアプリはありません。</p>
                    @else
                        <ul class="sfk-apps-grid" x-sortable data-sortable-animation-duration="150"
                            x-on:end.stop="$wire.saveMyAppOrder($event.target.sortable.toArray())">
                            @foreach ($apps as $app)
                                @php($url = $appUrls[$app->id] ?? null)
                                @php($published = $app->status === \Modules\Core\Domain\Apps\AppStatus::Published)
                                <li wire:key="app-{{ $app->id }}" x-sortable-item="{{ $app->id }}"
                                    class="sfk-apps-tile"
                                    data-name="{{ mb_strtolower($app->name . ' ' . $app->iconText()) }}"
                                    x-bind:hidden="q !== '' && !$el.dataset.name.includes(q.trim().toLowerCase())">
                                    @php($handleTitle = $isAdministrator ? 'ドラッグして既定の並びを変更' : 'ドラッグして並び替え')
                                    <button type="button" class="sfk-apps-handle" x-sortable-handle x-show="q === ''"
                                        title="{{ $handleTitle }}">
                                        <x-filament::icon icon="heroicon-m-bars-3" />
                                        <span class="fi-sr-only">{{ $handleTitle }}</span>
                                    </button>
                                    @if ($url !== null)
                                        <a href="{{ $url }}" @class(['sfk-row', 'sfk-unpublished' => !$published])>
                                            <span class="sfk-app-icon"
                                                aria-hidden="true">{{ $app->iconText() }}</span>
                                            <span class="sfk-row-name">{{ $app->name }}</span>
                                            @unless ($published)
                                                <span class="sfk-row-muted">（{{ $app->status->label() }}）</span>
                                            @endunless
                                        </a>
                                    @else
                                        <div @class(['sfk-row', 'sfk-unpublished' => !$published])>
                                            <span class="sfk-app-icon"
                                                aria-hidden="true">{{ $app->iconText() }}</span>
                                            <span class="sfk-row-name">{{ $app->name }}</span>
                                            <span
                                                class="sfk-row-muted">（{{ $published ? '画面準備中' : $app->status->label() }}）</span>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <p class="sfk-apps-none"
                            x-show="q !== '' && ! [...$root.querySelectorAll('.sfk-apps-grid li')].some((li) => ! li.hidden)"
                            x-cloak>「<span x-text="q"></span>」に一致するアプリはありません。</p>
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
