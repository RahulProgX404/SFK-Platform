{{--
    The look of the dashboard cards (Analytics Filament\Widgets, analytics::widgets.*): part of the cached theme
    stylesheet (ThemeAssets::extend, AnalyticsServiceProvider), not of the cards — each card is a Livewire component,
    redrawn by the dashboard's filters and the live pulse, which would otherwise send these styles again. Scoped to the
    cards' own classes; the shared card frame (sfk-dash-*) is core::filament.portal-styles.
--}}
<style>
    /* ---- 売上合計 (analytics::widgets.revenue) ---- */
    .sfk-revenue { display: flex; flex-direction: column; gap: var(--sfk-space-4); height: 100%; padding: var(--sfk-space-5); border-radius: var(--sfk-radius-xl); background: linear-gradient(160deg, var(--sfk-primary) 0%, #0b5aa3 100%); box-shadow: 0 8px 20px rgba(5, 70, 126, .2); color: #fff; }
    .sfk-revenue-head { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--sfk-space-3); }
    .sfk-revenue-title { margin: 0; font-size: var(--sfk-fs-heading); font-weight: var(--sfk-fw-bold); }
    .sfk-revenue-caption { display: flex; align-items: center; gap: 6px; margin-top: 4px; color: rgba(255, 255, 255, .85); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; }
    .sfk-revenue-caption .sfk-dash-tag { background: rgba(255, 255, 255, .2); color: #fff; }
    .sfk-revenue-amount { margin: 0; font-size: 22px; font-weight: var(--sfk-fw-bold); line-height: 1.2; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .sfk-revenue-sub { margin: 4px 0 0; color: rgba(255, 255, 255, .78); font-size: var(--sfk-fs-small); font-variant-numeric: tabular-nums; }
    .sfk-revenue-compare { display: flex; flex-direction: column; gap: 6px; padding-top: var(--sfk-space-3); border-top: 1px solid rgba(255, 255, 255, .2); font-size: var(--sfk-fs-small); font-variant-numeric: tabular-nums; }
    .sfk-revenue-compare-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 4px var(--sfk-space-2); }
    .sfk-revenue .sfk-dash-trend { background: #fff; }
    .sfk-revenue-diff { color: #fff; font-weight: var(--sfk-fw-semibold); white-space: nowrap; }
    .sfk-revenue-diff small { margin-right: 4px; color: rgba(255, 255, 255, .78); font-size: var(--sfk-fs-badge); font-weight: var(--sfk-fw-regular); }
    .sfk-revenue-baseline { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 2px var(--sfk-space-3); margin: 0; color: rgba(255, 255, 255, .82); }
    .sfk-revenue-baseline dt small { margin-left: 4px; font-size: var(--sfk-fs-badge); }
    .sfk-revenue-baseline dd { margin: 0; color: #fff; font-weight: var(--sfk-fw-semibold); white-space: nowrap; }
    .sfk-revenue-note { display: flex; align-items: center; gap: 6px; margin: 0; padding: 4px 8px; border-radius: var(--sfk-radius); background: rgba(255, 255, 255, .14); color: #fff; font-size: var(--sfk-fs-badge); }
    .sfk-revenue-note .fi-icon { width: 14px; height: 14px; flex: none; }
    .sfk-revenue-target { padding: var(--sfk-space-3) var(--sfk-space-4); border-radius: var(--sfk-radius-lg); background: rgba(255, 255, 255, .12); }
    .sfk-revenue-target-row { display: flex; align-items: baseline; justify-content: space-between; gap: var(--sfk-space-3); font-size: var(--sfk-fs-small); }
    .sfk-revenue-target-row strong { font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-bold); font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sfk-revenue-bar { height: 8px; margin: var(--sfk-space-2) 0 6px; overflow: hidden; border-radius: 9999px; background: rgba(255, 255, 255, .22); }
    .sfk-revenue-bar i { display: block; height: 100%; border-radius: 9999px; background: #fff; }
    .sfk-revenue-target-note { display: flex; justify-content: space-between; gap: var(--sfk-space-3); color: rgba(255, 255, 255, .82); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; }
    .sfk-revenue-target-note b { color: #fff; font-weight: var(--sfk-fw-bold); }
    .sfk-revenue-more { display: inline-flex; align-items: center; gap: 6px; align-self: flex-start; margin-top: auto; color: #fff; font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-semibold); text-decoration: none; opacity: .92; }
    .sfk-revenue-more:hover { opacity: 1; text-decoration: underline; }
    .sfk-revenue .sfk-tip-term:focus-visible, .sfk-revenue [data-sfk-tip]:focus-visible { outline-color: #fff; }
    .sfk-revenue-more .fi-icon { width: 16px; height: 16px; }


    /* ---- 売上概要 (analytics::widgets.sales-overview) ---- */
    .sfk-overview-kpis { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: var(--sfk-space-4); }
    .sfk-overview-kpi { min-width: 0; }
    .sfk-overview-kpi + .sfk-overview-kpi { padding-left: var(--sfk-space-4); border-left: 1px solid var(--sfk-line-soft); }
    .sfk-overview-label { margin: 0; color: var(--sfk-muted); font-size: var(--sfk-fs-small); }
    .sfk-overview-value { margin: 2px 0 4px; color: var(--sfk-ink); font-size: 20px; font-weight: var(--sfk-fw-bold); line-height: 1.25; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sfk-overview-value small { margin-left: 2px; color: var(--sfk-muted); font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-medium); }
    .sfk-overview-count-row { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--sfk-space-3); }
    .sfk-overview-spark { display: flex; align-items: flex-end; gap: 3px; width: 84px; height: 30px; flex: none; }
    .sfk-overview-spark i { flex: 1; min-height: 3px; border-radius: 2px 2px 0 0; background: var(--sfk-primary); opacity: .3; }
    .sfk-overview-spark i:last-of-type { opacity: .85; }
    .sfk-overview-spark i.is-nodata { height: 100% !important; border: 1px dashed var(--sfk-line-strong); border-bottom: 0; background: transparent; opacity: 1; }
    .sfk-overview-compare { display: flex; flex-wrap: wrap; align-items: center; gap: 4px var(--sfk-space-2); }
    .sfk-overview-baseline { display: block; margin-top: 4px; color: var(--sfk-muted); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; }
    .sfk-overview-baseline b { color: var(--sfk-text); font-weight: var(--sfk-fw-semibold); }
    .sfk-overview-bar { display: flex; gap: 2px; height: 10px; margin: var(--sfk-space-5) 0 var(--sfk-space-4); overflow: hidden; border-radius: 9999px; background: var(--sfk-line-soft); }
    .sfk-overview-bar span { height: 100%; background: var(--sfk-tone); }
    .sfk-overview-statuses { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--sfk-space-3); margin: 0; }
    .sfk-overview-status { min-width: 0; padding-left: var(--sfk-space-3); border-left: 3px solid var(--sfk-tone); }
    .sfk-overview-status dt { color: var(--sfk-text); font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-medium); white-space: nowrap; }
    .sfk-overview-status dd { margin: 2px 0 0; }
    .sfk-overview-status-amount { display: block; overflow: hidden; color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-bold); font-variant-numeric: tabular-nums; text-overflow: ellipsis; white-space: nowrap; }
    .sfk-overview-status-note { display: block; color: var(--sfk-muted); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sfk-overview-status.is-alert .sfk-overview-status-amount { color: var(--sfk-danger-text, var(--sfk-danger)); }
    .sfk-tone-slate { --sfk-tone: #64748b; --sfk-tone-soft: #f1f5f9; }
    @media (max-width: 640px) {
        .sfk-overview-kpis { grid-template-columns: minmax(0, 1fr); }
        .sfk-overview-kpi + .sfk-overview-kpi { padding: var(--sfk-space-3) 0 0; border-top: 1px solid var(--sfk-line-soft); border-left: 0; }
        .sfk-overview-statuses { grid-template-columns: repeat(2, minmax(0, 1fr)); row-gap: var(--sfk-space-4); }
    }


    /* ---- 入出金 (analytics::widgets.cash-flow) ---- */
    .sfk-cash-rows { display: flex; flex-direction: column; margin: 0; }
    .sfk-cash-row { display: flex; align-items: center; justify-content: space-between; gap: var(--sfk-space-3); padding: var(--sfk-space-3) 0; }
    .sfk-cash-row + .sfk-cash-row { border-top: 1px solid var(--sfk-line-soft); }
    .sfk-cash-row dt { display: flex; align-items: center; gap: var(--sfk-space-2); color: var(--sfk-text); font-size: var(--sfk-fs-body); }
    .sfk-cash-icon { display: grid; place-items: center; width: 28px; height: 28px; flex: none; border-radius: var(--sfk-radius); background: var(--sfk-tone-soft); color: var(--sfk-tone); }
    .sfk-cash-icon .fi-icon { width: 16px; height: 16px; }
    .sfk-cash-row dd { margin: 0; color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sfk-cash-net { display: flex; align-items: baseline; justify-content: space-between; gap: var(--sfk-space-3); margin-top: auto; padding: var(--sfk-space-3) var(--sfk-space-4); border-radius: var(--sfk-radius-lg); background: var(--sfk-success-soft); }
    .sfk-cash-net.is-minus { background: var(--sfk-danger-soft); }
    .sfk-cash-net span { color: var(--sfk-text); font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-semibold); }
    .sfk-cash-net strong { color: var(--sfk-success); font-size: var(--sfk-fs-figure); font-weight: var(--sfk-fw-bold); font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sfk-cash-net.is-minus strong { color: var(--sfk-danger-text, var(--sfk-danger)); }


    /* ---- 個人の概要 (analytics::widgets.personal-overview) ---- */
    .sfk-personal-wrap { display: flex; }
    .sfk-personal-wrap > * { flex: 1; min-width: 0; }
    .sfk-personal { position: relative; display: flex; flex-direction: column; gap: var(--sfk-space-4); padding: var(--sfk-space-5) var(--sfk-space-6); overflow: hidden; border-radius: var(--sfk-radius-xl); background: radial-gradient(circle at 78% 120%, rgba(91, 150, 230, .55), transparent 55%), linear-gradient(115deg, #071a33 0%, #0b2c57 38%, var(--sfk-primary) 72%, #2c6cc0 100%); box-shadow: 0 10px 24px rgba(5, 70, 126, .22); color: #fff; }
    /* the stars and the wire hill of overview.png */
    .sfk-personal::before { content: ""; position: absolute; inset: 0; background-image: radial-gradient(rgba(255, 255, 255, .55) 1px, transparent 1.5px), radial-gradient(rgba(255, 255, 255, .3) 1px, transparent 1.5px); background-position: 0 0, 37px 23px; background-size: 140px 90px, 90px 70px; opacity: .45; pointer-events: none; }
    .sfk-personal-mesh { position: absolute; right: 0; bottom: 0; width: min(42%, 380px); height: 100%; color: rgba(255, 255, 255, .28); pointer-events: none; }
    .sfk-personal-head { position: relative; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--sfk-space-3); }
    .sfk-personal-title { margin: 0; font-size: var(--sfk-fs-heading); font-weight: var(--sfk-fw-bold); }
    .sfk-personal-tools { display: inline-flex; flex-wrap: wrap; align-items: center; gap: var(--sfk-space-2); }
    .sfk-personal-period { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: var(--sfk-radius); background: rgba(255, 255, 255, .14); color: rgba(255, 255, 255, .92); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sfk-personal-period .fi-icon { width: 14px; height: 14px; }
    .sfk-personal-range { height: 30px; padding: 0 28px 0 10px; border: 0; border-radius: var(--sfk-radius); background-color: rgba(255, 255, 255, .16); color: #fff; font: inherit; font-size: var(--sfk-fs-small); }
    .sfk-personal-range option { color: var(--sfk-ink); }
    .sfk-personal-range:focus { outline: none; box-shadow: 0 0 0 2px rgba(255, 255, 255, .5); }
    .sfk-personal-figures { position: relative; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: var(--sfk-space-4); margin: 0; }
    .sfk-personal-figure dt { display: flex; align-items: center; gap: var(--sfk-space-2); color: rgba(255, 255, 255, .88); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-medium); }
    .sfk-personal-figure dt .fi-icon { width: 18px; height: 18px; flex: none; }
    .sfk-personal-figure dd { margin: 6px 0 0; font-size: 1.25rem; font-weight: var(--sfk-fw-bold); font-variant-numeric: tabular-nums; line-height: 1.3; overflow-wrap: anywhere; }
    .sfk-personal-figure dd small { margin-left: 3px; color: rgba(255, 255, 255, .8); font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-medium); }
    @media (max-width: 767px) {
        .sfk-personal { padding: var(--sfk-space-4); }
        .sfk-personal-figures { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }


    /* ---- 売れ筋 TOP3 (analytics::widgets.top-products) ---- */
    .sfk-products { display: flex; flex-direction: column; gap: var(--sfk-space-4); margin: 0; padding: 0; list-style: none; }
    .sfk-products-item { display: grid; grid-template-columns: 24px minmax(0, 1fr); gap: 6px var(--sfk-space-2); }
    .sfk-products-item .sfk-dash-rankno { grid-row: span 3; align-self: start; }
    .sfk-products-name { display: -webkit-box; overflow: hidden; color: var(--sfk-ink); font-size: var(--sfk-fs-body); font-weight: var(--sfk-fw-semibold); line-height: 1.35; overflow-wrap: anywhere; -webkit-box-orient: vertical; -webkit-line-clamp: 2; min-height: calc(2em * 1.35); }
    .sfk-products-bar { height: 10px; overflow: hidden; border-radius: 9999px; background: var(--sfk-line-soft); }
    .sfk-products-bar i { display: block; height: 100%; border-radius: 9999px; }
    .sfk-products-figure { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 0 var(--sfk-space-2); }
    .sfk-products-amount { color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-bold); font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sfk-products-count { color: var(--sfk-muted); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; white-space: nowrap; }


    /* ---- 最近表示した項目 / クイックアクション (analytics::widgets.quick-actions) ---- */
    .sfk-quick { display: flex; flex-direction: column; gap: var(--sfk-space-3); margin: 0; padding: 0; list-style: none; }
    .sfk-quick-row { display: flex; align-items: center; gap: var(--sfk-space-3); min-height: 56px; padding: var(--sfk-space-2) var(--sfk-space-3); border-radius: var(--sfk-radius-lg); background: var(--sfk-quick); color: #fff; }
    .sfk-quick-row.is-blue { --sfk-quick: var(--sfk-primary); }
    .sfk-quick-row.is-teal { --sfk-quick: #0b8aa3; }
    .sfk-quick-row.is-green { --sfk-quick: #16956b; }
    .sfk-quick-row.is-orange { --sfk-quick: #d4610f; }
    .sfk-quick-row.is-dark { --sfk-quick: #1f2937; }
    .sfk-quick-row.is-purple { --sfk-quick: #6e56cf; }
    .sfk-quick-icon { display: grid; place-items: center; width: 30px; height: 30px; flex: none; border-radius: 9999px; background: rgba(255, 255, 255, .18); }
    .sfk-quick-icon .fi-icon { width: 16px; height: 16px; }
    .sfk-quick-text { flex: 1; min-width: 0; color: #fff; text-decoration: none; }
    .sfk-quick-text:hover { text-decoration: underline; }
    .sfk-quick-text strong { display: block; overflow: hidden; font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); text-overflow: ellipsis; white-space: nowrap; }
    .sfk-quick-text small { display: block; overflow: hidden; color: rgba(255, 255, 255, .85); font-size: var(--sfk-fs-badge); text-overflow: ellipsis; white-space: nowrap; }
    .sfk-quick-run { flex: none; display: inline-flex; align-items: center; gap: 4px; height: 30px; padding: 0 12px; border-radius: 9999px; background: rgba(255, 255, 255, .2); color: #fff; font-size: var(--sfk-fs-badge); font-weight: var(--sfk-fw-semibold); white-space: nowrap; }
    .sfk-quick-run:hover { background: rgba(255, 255, 255, .32); }
    .sfk-quick-run .fi-icon { width: 14px; height: 14px; }
    .sfk-quick-runs { flex: none; display: inline-flex; gap: var(--sfk-space-2); }
    a.sfk-quick-run { text-decoration: none; }
    .sfk-quick-done { display: flex; flex-direction: column; align-items: center; align-self: center; gap: var(--sfk-space-2); padding: var(--sfk-space-6) var(--sfk-space-4); color: var(--sfk-muted); font-size: var(--sfk-fs-small); text-align: center; }
    .sfk-quick-done .fi-icon { width: 36px; height: 36px; color: #16956b; }
    .sfk-recent { display: flex; flex-direction: column; margin: 0; padding: 0; list-style: none; }
    .sfk-recent li + li { border-top: 1px solid var(--sfk-line-soft); }
    .sfk-recent-link { display: flex; align-items: center; gap: var(--sfk-space-3); min-height: 54px; padding: var(--sfk-space-2); border-radius: var(--sfk-radius); color: inherit; text-decoration: none; }
    .sfk-recent-link:hover { background: var(--sfk-hover); text-decoration: none; }
    .sfk-recent-link:hover strong { color: var(--sfk-primary); }
    .sfk-recent-icon { display: grid; place-items: center; width: 36px; height: 36px; flex: none; overflow: hidden; border-radius: var(--sfk-radius-lg); background: var(--sfk-primary); color: #fff; font-size: 11px; font-weight: var(--sfk-fw-bold); line-height: 1; }
    .sfk-recent-text { flex: 1; min-width: 0; }
    .sfk-recent-text strong { display: block; overflow: hidden; color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); text-overflow: ellipsis; white-space: nowrap; }
    .sfk-recent-text small { display: block; overflow: hidden; color: var(--sfk-muted); font-size: var(--sfk-fs-badge); text-overflow: ellipsis; white-space: nowrap; }
    .sfk-recent-id { flex: none; padding: 2px 8px; border-radius: 9999px; background: var(--sfk-primary-soft); color: var(--sfk-primary-dark); font-size: var(--sfk-fs-badge); font-weight: var(--sfk-fw-semibold); font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sfk-recent-arrow { width: 16px; height: 16px; flex: none; color: var(--sfk-subtle); }
    .sfk-quick-more { margin: var(--sfk-space-3) 0 0; color: var(--sfk-muted); font-size: var(--sfk-fs-small); text-align: right; }


    /* ---- 休暇・勤怠予定 (analytics::widgets.absences) ---- */
    .sfk-absence-days { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--sfk-space-3); }
    .sfk-absence-day { min-width: 0; padding: var(--sfk-space-3); border-radius: var(--sfk-radius-lg); box-shadow: inset 0 0 0 1px var(--sfk-line-soft); }
    .sfk-absence-day h3 { display: flex; align-items: baseline; justify-content: space-between; gap: var(--sfk-space-2); margin: 0 0 var(--sfk-space-1); color: var(--sfk-ink); font-size: var(--sfk-fs-body); font-weight: var(--sfk-fw-bold); }
    .sfk-absence-day h3 small { color: var(--sfk-muted); font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-regular); font-variant-numeric: tabular-nums; }
    .sfk-absence-day.is-today { background: var(--sfk-primary-soft); box-shadow: none; }
    .sfk-absence-chip { flex: none; padding: 1px 8px; border-radius: 9999px; background: var(--sfk-tone-soft); color: var(--sfk-tone); font-size: var(--sfk-fs-badge); font-weight: var(--sfk-fw-semibold); white-space: nowrap; }
    /* four people of room a day (the card's height); more scroll inside the day */
    .sfk-dash-stack > .sfk-absence-days .sfk-dash-people { max-height: calc(var(--sfk-absence-rows) * 50px); overflow-y: auto; scrollbar-width: thin; }
    .sfk-absence-none { margin: 0; padding: var(--sfk-space-1) 0; color: var(--sfk-muted); font-size: var(--sfk-fs-small); }


    /* ---- 最近のアクティビティ (analytics::widgets.recent-activity) ---- */
    .sfk-activity { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 var(--sfk-space-8); }
    .sfk-activity ol { margin: 0; padding: 0; list-style: none; }
    .sfk-activity-item { position: relative; display: flex; gap: var(--sfk-space-3); padding-bottom: var(--sfk-space-3); }
    .sfk-activity-item::before { content: ""; position: absolute; left: 15px; top: 34px; bottom: 2px; width: 2px; border-radius: 2px; background: linear-gradient(180deg, var(--sfk-tone-soft), transparent); }
    .sfk-activity-item:last-child::before { display: none; }
    .sfk-activity-icon { position: relative; z-index: 1; display: grid; place-items: center; width: 32px; height: 32px; flex: none; border-radius: 9999px; background: var(--sfk-tone); color: #fff; }
    .sfk-activity-icon .fi-icon { width: 16px; height: 16px; }
    .sfk-activity-text { flex: 1; min-width: 0; padding-top: 4px; }
    .sfk-activity-head { display: flex; align-items: baseline; justify-content: space-between; gap: 2px var(--sfk-space-3); }
    .sfk-activity-head strong { flex: 1; min-width: 0; overflow: hidden; color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); text-overflow: ellipsis; white-space: nowrap; }
    .sfk-activity-head time { display: flex; flex: none; align-items: baseline; margin-left: auto; gap: 6px; color: var(--sfk-text); font-size: var(--sfk-fs-badge); line-height: 1.35; white-space: nowrap; }
    .sfk-activity-head time small { color: var(--sfk-muted); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; }
    .sfk-activity-body { display: block; margin-top: 2px; overflow: hidden; color: var(--sfk-text); font-size: var(--sfk-fs-small); text-overflow: ellipsis; white-space: nowrap; }
    a.sfk-activity-text { color: inherit; text-decoration: none; }
    a.sfk-activity-text:hover strong { color: var(--sfk-primary); }
    .sfk-activity.is-single { grid-template-columns: minmax(0, 1fr); }
    @media (max-width: 1023px) { .sfk-activity { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 480px) { .sfk-activity-head time { flex-direction: column; align-items: flex-end; gap: 0; } }

</style>
