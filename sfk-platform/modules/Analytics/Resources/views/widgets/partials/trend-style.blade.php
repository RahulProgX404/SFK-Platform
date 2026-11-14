{{-- Styles of the 12-month charts (partials.trend-chart) and of the header of their cards (売上の推移, 勤務時間・収入の推移):
     legend dots and the year choice of personal-growth.png. --}}
<style>
    .sfk-growth-tools { display: flex; flex-wrap: wrap; align-items: center; gap: var(--sfk-space-4); }
    .sfk-growth-legend { display: flex; flex-wrap: wrap; gap: var(--sfk-space-3); margin: 0; padding: 0; list-style: none; color: var(--sfk-text); font-size: var(--sfk-fs-small); }
    .sfk-growth-legend li { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
    .sfk-growth-legend i { width: 16px; height: 3px; border-radius: 9999px; background: var(--sfk-legend); }
    .sfk-growth-legend i.is-dashed { background: repeating-linear-gradient(90deg, var(--sfk-legend) 0 5px, transparent 5px 8px); }
    .sfk-growth-year { display: inline-flex; align-items: center; gap: 6px; height: var(--sfk-control); padding: 0 var(--sfk-space-2) 0 var(--sfk-space-3); border: 1px solid var(--sfk-line); border-radius: var(--sfk-radius); background: var(--sfk-surface); color: var(--sfk-ink); }
    .sfk-growth-year:focus-within { border-color: var(--sfk-primary); box-shadow: 0 0 0 3px var(--sfk-primary-soft); }
    .sfk-growth-year .fi-icon { width: 16px; height: 16px; flex: none; color: var(--sfk-muted); }
    .sfk-growth-year select { height: 100%; padding: 0 22px 0 0; border: 0; background-color: transparent; background-position: right 0 center; color: inherit; font: inherit; font-size: var(--sfk-fs-label); font-variant-numeric: tabular-nums; cursor: pointer; }
    .sfk-growth-year select:focus { outline: none; box-shadow: none; }
    .sfk-growth-figures { display: flex; flex-wrap: wrap; gap: var(--sfk-space-6); margin: 0 0 var(--sfk-space-4); }
    .sfk-growth-figures dt { color: var(--sfk-muted); font-size: var(--sfk-fs-small); }
    .sfk-growth-figures dd { display: flex; align-items: baseline; gap: var(--sfk-space-2); margin: 2px 0 0; color: var(--sfk-ink); font-size: var(--sfk-fs-figure); font-weight: var(--sfk-fw-bold); font-variant-numeric: tabular-nums; }
    .sfk-growth-figures dd small { color: var(--sfk-muted); font-size: var(--sfk-fs-small); font-weight: var(--sfk-fw-medium); }
    .sfk-growth-figures dd .sfk-dash-trend { align-self: center; }
    .sfk-growth-figures dd.sfk-growth-note { display: flex; flex-wrap: wrap; align-items: center; gap: 4px var(--sfk-space-3); margin-top: 6px; font-variant-numeric: tabular-nums; color: var(--sfk-muted); font-size: var(--sfk-fs-badge); font-weight: var(--sfk-fw-regular); }
    .sfk-growth-days { color: var(--sfk-muted); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; }
    .sfk-growth-pair { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--sfk-space-6); }
    .sfk-growth-pair h3 { margin: 0 0 var(--sfk-space-2); color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); }

    .sfk-trend { position: relative; padding: var(--sfk-space-2) var(--sfk-space-3) 0 52px; }
    .sfk-trend-axis { position: absolute; top: var(--sfk-space-2); left: 0; width: 44px; height: 240px; }
    .sfk-trend-axis span { position: absolute; right: 0; transform: translateY(-50%); color: var(--sfk-muted); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sfk-trend-plot { position: relative; height: 240px; border-radius: 2px; cursor: crosshair; }
    .sfk-trend-plot:focus { outline: none; }
    .sfk-trend-plot:focus-visible { box-shadow: 0 0 0 2px var(--sfk-primary-soft); }
    .sfk-trend-grid { position: absolute; left: 0; right: 0; height: 0; border-top: 1px dashed var(--sfk-line); }
    .sfk-trend-grid.is-base { border-top-style: solid; border-color: var(--sfk-line-strong); }
    .sfk-trend-svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
    .sfk-trend-months { position: relative; height: 20px; margin-top: var(--sfk-space-2); }
    .sfk-trend-months span { position: absolute; transform: translateX(-50%); color: var(--sfk-muted); font-size: var(--sfk-fs-badge); white-space: nowrap; }
    .sfk-trend-cross { position: absolute; top: 0; bottom: 0; width: 0; border-left: 1px solid var(--sfk-line-strong); pointer-events: none; }
    .sfk-trend-ring { position: absolute; width: 10px; height: 10px; margin: -5px 0 0 -5px; border: 2px solid var(--sfk-ring-color); border-radius: 9999px; background: var(--sfk-surface); pointer-events: none; }
    .sfk-trend-dot { position: absolute; width: 10px; height: 10px; margin: -5px 0 0 -5px; border-radius: 9999px; box-shadow: 0 0 0 2px var(--sfk-surface); pointer-events: none; }
    /* as wide as its rows (an absolute box near the right edge would shrink to the space left), never wider than 300px */
    .sfk-trend-tip { position: absolute; z-index: 5; top: 4px; width: max-content; min-width: 180px; max-width: 300px; padding: 10px 12px; transform: translateX(12px); pointer-events: none; }
    .sfk-trend-tip .sfk-tip-rows dt { white-space: nowrap; }
    .sfk-trend-tip.is-left { transform: translateX(calc(-100% - 12px)); }
    .sfk-trend-tip .is-none dd { color: var(--sfk-muted); font-weight: var(--sfk-fw-regular); }
    @media (prefers-reduced-motion: no-preference) { .sfk-trend-tip { animation: sfk-trend-tip-in .12s ease-out; } }
    @keyframes sfk-trend-tip-in { from { opacity: 0; } to { opacity: 1; } }

    @media (max-width: 767px) {
        .sfk-growth-pair { grid-template-columns: minmax(0, 1fr); }
        .sfk-trend-plot, .sfk-trend-axis { height: 180px; }
        .sfk-trend-months span:nth-child(even) { visibility: hidden; }
        .sfk-dash-card-head:has(> .sfk-growth-tools) { flex-wrap: wrap; }
        .sfk-trend { padding-left: 44px; }
        .sfk-trend-axis { width: 38px; }
        .sfk-trend-tip { min-width: 0; max-width: calc(50vw - 24px); }
        .sfk-trend-tip .sfk-tip-rows > div { flex-wrap: wrap; column-gap: 8px; }
        .sfk-trend-tip .sfk-tip-rows dd { margin-left: auto; }
    }
</style>
