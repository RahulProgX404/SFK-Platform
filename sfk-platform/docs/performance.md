# Performance (measured 2026-11-18)

What was measured, what was found, what changed and what is left. Every number below was measured; nothing is
estimated unless it says so.

## How it was measured

- **Production-like stack**: the production image (`docker/render/Dockerfile`: nginx, PHP-FPM, OPcache, cached
  config / routes / views) with its own Redis, against a **copy** of the local data (`createdb -T sfk_platform
  sfk_perf`: 6,919 受注, 6,063 売上, 24,248 日報, 3,298 取引先) — never the shared Neon database. Warm caches, one
  warm-up request, then 7 runs per page / interaction, p50 and p95.
- **Server time, DB time, queries**: `SFK_SERVER_TIMING=true` adds a standard `Server-Timing` header (`app`, `db`,
  `q`) to every response, Livewire updates included; `SFK_SERVER_TIMING_LOG=true` also writes one line per request
  to `storage/logs/server-timing.log` (method, path, Livewire calls, time, queries, memory). Off by default — never
  on in production (`Modules\Core\Http\Middleware\ServerTiming`).
- **Livewire interactions**: real `/livewire-…/update` requests replayed against the page component's own snapshot
  (re-render, 絞り込む, next page, sort, search).
- **Where server time goes**: Excimer sampling profiler (1 ms) in the throwaway container only.
- **Browser**: Chrome's Navigation Timing and Long Animation Frames, and same-session A/B loads of the real page HTML
  with and without a change.
- **Infrastructure**: `select 1` round trips to Neon from local Docker; `curl` against the Render site.

## Findings

| # | Finding | Measured |
|---|---|---|
| 1 | **Lists are big**: 100 rows × ~23 columns ≈ 1 MB of HTML (受注 1,076 KB, 過去売上 922 KB, 取引先 897 KB) | list pages 180–360 ms server time |
| 2 | **Every list interaction draws the whole table again** — next page, sort, search, even opening 絞り込む | 315–348 ms and ~1.1 MB per click (受注, 100 rows) |
| 3 | **Hover prefetch on every cell** (`wire:navigate.hover` × 2,300): each link was its own Alpine root, and resting the mouse on a row loaded the record page | 20 rows hovered → 20 full page requests (110–280 ms, 20–57 queries each) |
| 4 | **The browser's work exceeds the server's**: style + layout while parsing ~1.1 s, Livewire / Alpine start-up 738 ms; Filament's `app.css` (471 rules the browser cannot index: `*`, `:where()`, attributes) is ~70 % of the styling cost | page ready at ~2.5 s with TTFB 0.37 s (local) |
| 5 | `売上分析` / dashboard: "first 担当者" by a correlated subquery, run twice per sale; `years()` / `actuals()` asked twice per page | 31 ms per query (6,000 sales), DB 104 ms per page |
| 6 | `受注 新規`: the 13 calculated fields each recomputed all totals; every 用紙 / 印刷 / 加工 row reloaded the 833 suppliers | 246 ms, 14 queries |
| 7 | **Neon is in Singapore**: every query from local development costs ~80 ms | p50 80.1 ms, p95 81.1 ms |
| 8 | **Render free plan**: little CPU, 4 PHP workers; Render and Neon are both in Singapore | `/login` 160–420 ms vs 57 ms local app time |
| 9 | **A clean image build failed** (found while measuring): booting opened a database during `composer` → `package:discover` | build error without a local `database.sqlite` |

What was **not** a problem: query counts (5–21 per page, DB mostly < 15 ms), the 表示件数 control (a native select:
opens without the server), `:has()` selectors (removing the high-level ones changed nothing — 270 vs 269 ms).

## Changes

| Change | Where |
|---|---|
| Table cells are plain links marked `data-sfk-navigate`; one click listener for the page opens them in place (`Alpine.navigate`). No hover prefetch on rows; middle / Ctrl / ⌘ click and new tabs work as before. Swapped into Filament's table view by a Blade precompiler — when Filament changes the line, the stock link comes back and a test fails | `Core\Filament\Tables\RecordLink`, `core::filament.table-links-script`, `CoreServiceProvider` |
| `#[Renderless]` on calls that change nothing on the page: `filterDialog()` (絞り込む), and the drag-and-drop order saves (`reorderViews`, `reorderOutputs`, `saveAppOrder`, `saveMyAppOrder`) | `AppListRecords`, `AppListViewSettings`, `ManagesDocuments`, `PortalPage` |
| First 担当者 of a sale found once for all sales (grouped `min(id)`), not per sale — same results for every 担当者 (checked both ways on the copy) | `Analytics\Application\FirstStaff`, `StaffSalesAnalysis`, `SalesDashboard` |
| `売上分析` reads the years and 実績 once per request | `SalesAnalysis` |
| `受注` form: totals once per form state (per request); suppliers once per request and user; a row's supplier once per supplier | `OrderForm` |
| SQLite search function registered when a connection really opens — the image builds without a database again | `Core\Application\Search\SearchQuery` |
| Measurement: `Server-Timing` header and log (opt-in) | `Core\Http\Middleware\ServerTiming`, `core.server_timing*` |

## Results (same data, same machine, idle)

| Operation | Before | After | Change |
|---|---:|---:|---:|
| Requests sent by hovering over 20 rows | 20 | **0** | −100 % |
| 絞り込む open — server | 325 ms, 1,230 KB | **32 ms, 103 KB** | −90 % |
| 絞り込む open — in the browser (median of 5) | 1,324 ms | **484 ms** | −63 % |
| Livewire / Alpine start-up, 100-row list (A/B, median of 3) | 766 ms | **487 ms** | −36 % |
| DOM ready, 100-row list (A/B, median of 3) | 1,472 ms | **871 ms** | −41 % |
| List re-render / next page / sort — server | 315–348 ms | 278–311 ms | −10 % |
| `売上分析` page — server (DB) | 160 ms (104 ms) | **77 ms (23 ms)** | −52 % |
| `受注 新規` — server | 246 ms, 14 queries | **195 ms, 12 queries** | −21 % |
| 担当者 ranking / 個人 filter query | 15.6 / 10.0 ms | 6.6 / 6.2 ms | −58 % / −38 % |

Measured but **inconclusive** (spread larger than the difference): full page loads of the list in a frame (1.1–1.6 s
both versions) and a next-page click in the browser (1.0–2.2 s both) — the browser's re-styling of a 100-row table
dominates both.

## Remaining, and what to decide

1. **Rows per page — the largest remaining factor; the default is now 60 (user, 2026-11-18; was 100).** 受注 at 20 rows: page 124 ms (vs 306), next page /
   re-render 107–110 ms (vs 322–334), 350 KB (vs 1,130 KB); browser start-up ~130 ms (vs ~490). The default was a
   deliberate choice (表示件数 20–300); users who picked a size keep it.
2. **Filament's own rendering and CSS**: ~3 ms of server time per row and the browser's styling of every cell come
   from Filament; not changed here (copying vendor views / CSS would break on updates).
3. **Hosting**: Render free (little CPU) multiplies every server number above; production on a Tokyo server (AWS
   EC2 / Laravel Cloud) runs close to the local numbers. Local development pays ~80 ms per query to Neon (Singapore) —
   a database in Tokyo, or a local database for development, removes most of that.
4. The first request after a deploy fills the caches (~0.7 s, 52 queries); every later one is fast.
5. `受注 新規` still writes the 833 suppliers into every sub-table row (60 KB each); a searchable select would be
   lighter but changes how the field is used.
