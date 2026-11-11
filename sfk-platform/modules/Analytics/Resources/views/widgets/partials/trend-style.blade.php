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
    .sfk-growth-change { padding: 1px 8px; border-radius: 9999px; font-size: var(--sfk-fs-badge); font-weight: var(--sfk-fw-semibold); }
    .sfk-growth-change.is-up { background: var(--sfk-success-soft); color: var(--sfk-success); }
    .sfk-growth-change.is-down { background: var(--sfk-danger-soft); color: var(--sfk-danger); }
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
    .sfk-trend-dot { position: absolute; width: 10px; height: 10px; margin: -5px 0 0 -5px; border-radius: 9999px; box-shadow: 0 0 0 2px var(--sfk-surface); pointer-events: none; }
    .sfk-trend-tip { position: absolute; z-index: 2; top: 4px; min-width: 160px; padding: var(--sfk-space-2) var(--sfk-space-3); transform: translateX(12px); border: 1px solid var(--sfk-line); border-radius: var(--sfk-radius); background: var(--sfk-surface); box-shadow: 0 6px 16px rgba(16, 24, 40, .12); font-size: var(--sfk-fs-small); pointer-events: none; }
    .sfk-trend-tip.is-left { transform: translateX(calc(-100% - 12px)); }
    .sfk-trend-tip strong { display: block; margin-bottom: 4px; color: var(--sfk-ink); font-weight: var(--sfk-fw-semibold); }
    .sfk-trend-tip-row { display: flex; align-items: center; gap: 6px; color: var(--sfk-text); white-space: nowrap; }
    .sfk-trend-tip-row i { width: 8px; height: 8px; flex: none; border-radius: 9999px; }
    .sfk-trend-tip-row b { margin-left: auto; padding-left: var(--sfk-space-3); color: var(--sfk-ink); font-weight: var(--sfk-fw-semibold); font-variant-numeric: tabular-nums; }

    @media (max-width: 767px) {
        .sfk-growth-pair { grid-template-columns: minmax(0, 1fr); }
        .sfk-trend-plot, .sfk-trend-axis { height: 180px; }
        .sfk-trend-months span:nth-child(even) { visibility: hidden; }
    }
</style>
