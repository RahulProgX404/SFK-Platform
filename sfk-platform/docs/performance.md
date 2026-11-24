# Performance

Three passes so far: 2026-11-24 (the whole system, every click), 2026-11-20 (clicks that take seconds — browser, round
trips, workers) and 2026-11-18 (big lists, below). Every number was measured; nothing is estimated unless it says so.

## Pass 2026-11-24: the whole system

### How it was measured

- **Where**: the development stack as used (Docker on Windows, PHP-FPM `pm = static` 8, Redis, the shared Neon database
  in Singapore), the super admin's own Chrome session; warm workers unless said otherwise; 3–16 runs each.
- **Server**: `SFK_SERVER_TIMING` + `_LOG` — the log now also lists **each query's time and SQL** (no bound values)
  under its request, so the round trips of any click can be read directly (`Core\Http\Middleware\ServerTiming`).
- **Browser**: a Livewire `request` hook per click — network + server, response size, and the main-thread work of
  applying the response (until the next task); opening drop-downs: handler + forced style / layout, and whether any
  server request was sent.
- **Database**: `EXPLAIN (ANALYZE, BUFFERS)` and timed reads in a container (read-only), after idle pauses.
- **Render**: `curl` of the public `/up` and `/login` (nobody signed in there).

### Baseline (before)

| What | Measured |
|---|---|
| Open 並び替え, a row's 「…」, 出力する書類, the sidebar (受注 list, 60 rows, 6,145 elements) | **0–26 ms** of main-thread work, **no server request** — already instant |
| 受注 list click: 一覧 tab, next / previous page, sort | 1.78–2.16 s in the browser = 1.40–1.55 s network + server (**11–12 queries**, DB 1.05–1.16 s) + 0.29–0.63 s applying ~800 KB |
| 絞り込む open (受注) | 0.52 s (server 0.36 s, 3 queries) |
| Dashboard `/erp` | warm 0.54–1.08 s (3–4 queries); first visit after a reload **5.1 s** (cold workers and caches) |
| 受注 record page / edit page | 1.92–2.44 s (18–19 queries) / 2.32 s (20 queries) |
| Page pulse (every 4 s per tab in view) | 65–93 ms app time, 0 queries (booting the app) |
| Render (free plan) | first request after a pause **50.7 s** (the instance sleeps); then `/up` 0.15–0.22 s, `/login` 0.21–0.25 s |

### Findings

| # | Finding | Measured |
|---|---|---|
| 1 | **Every query is a round trip to Singapore**; the SQL itself is fast | ~80 ms each; the 受注 list query runs in 0.18 ms in PostgreSQL |
| 2 | **A big result needs several round trips** after a few idle seconds (TCP slow start on Neon's side): 60 rows of 受注 ≈ 42 KB | 60 rows 238 ms (3 trips), 20 rows 160 ms, ids only 80 ms; leaving out the text / JSON columns changed nothing (110 of ~708 bytes a row) |
| 3 | The cache versions were read from the database on almost every click: the Redis copy lived 10 s, clicks are further apart | 1 query per click |
| 4 | The signed-in user was read from the database on every request and click | 1 query per request |
| 5 | List rows loaded each belongs-to relation separately, also several into the same table (受注: 得意先, 製版先, 製造先 … all 取引先; 申請管理 / アルコールチェック: four users relations) | 受注: 3 `partners` queries per click |
| 6 | 受注 list eager-loaded the 担当者' 従業員カテゴリ and 役職, which the ユーザー情報 cards already take from the cache | 2 queries per click |
| 7 | 受注 record page: 得意先 was read alone before the relations were batched (the page's mount() used it first) | 1 query per page |
| 8 | 受注 form: 製版先 / 製造先 / データ / フィルム each read the same 50 suppliers, and each its chosen 取引先 again | 4 lists + 4 lookups |
| 9 | A worker's first query opens a new TLS connection to Neon — after every PHP-FPM reload (the dev watcher reloads on each code change) and when Neon wakes from suspend | 580–616 ms instead of 80 ms |
| 10 | **Render free**: the instance sleeps after 15 minutes; little CPU | 50.7 s for the first request |
| 11 | Applying a list click in the browser: Filament redraws the page and its 60 × 26 cells | 0.29–0.63 s, ~800 KB per click |

### Changes

| Change | Where |
|---|---|
| Cache versions: stale-while-revalidate — older than 10 s but younger than 5 min they serve the request and are read again after the response (`defer()`); this environment's bumps are in the store at once anyway, another environment's (Render ⇄ local) are seen one request later | `Core\Application\Cache\CacheVersions` (`STALE_SECONDS`) |
| The signed-in user read through the reference cache (column values, rebuilt as a model); every save / delete of a user bumps it, so passwords, 在籍状況, rights are read afresh on the next request; not where the cache store is the database (Render) | `Core\Auth\CachedUserProvider`, `config/auth.php` (`sfk-eloquent`) |
| Models rebuilt from the reference cache carry the connection name, so `is()` compares equal with the same record from the database (was a latent mismatch) | `ReferenceCache::rememberModels()` |
| Lists batch belongs-to relations: a list page names them (`batchedTableRelations()`), their eager loads are taken off the query and they load with one query per related table for the page of rows; what cannot be batched is eager-loaded as usual — never per row | `AppListRecords::paginateTableQuery()`, `RelationBatch::loadMany()`; 受注, 売上, 申請管理, アルコールチェック, 安全衛生チェック, 事故報告 |
| 受注 list: no 従業員カテゴリ / 役職 eager loads (the cards come from the cache) | `OrdersTable` |
| Record pages batch their relations right after mount, before the page's own mount() reads one | `AppViewRecord::mount()` |
| 受注 form: the partner selects share one list per kind and request (the newest 50 — Filament's own preload; the suppliers are the first 50 of the rows' list), labels from it; searching still asks the relationship | `OrderForm::partner()` |
| Measurement: the timing log lists each query's time and SQL | `ServerTiming` |

### Results (same machine, same data, warm)

| Operation | Before | After | Change |
|---|---:|---:|---:|
| 受注 list click (page / sort) — server | 1,387–1,533 ms, 11–12 queries | **968–1,034 ms, 6 queries** | −32 % |
| 受注 list click — in the browser | 1,778–2,161 ms | 1,364–1,874 ms | −15 to −20 % |
| Dashboard `/erp` — server | median 562 ms (528–1,079), 3–4 queries | **median 498 ms (465–507), 2 queries** | −11 % (median) |
| 受注 record page — server | 1,916–2,443 ms, 18–19 queries | **1,722–1,802 ms, 16 queries** | −10 to −26 % |
| 受注 edit page — server | 2,321 ms, 20 queries | **1,960–2,020 ms, 16 queries** | −14 % |
| Open a drop-down / 「…」 / sidebar | 0–26 ms, no request | unchanged | — |

Tests: `RequestRoundTripsTest` (stale-while-revalidate; the cached user sees every change, nothing where the store is
the database), `OrderRecordQueriesTest` (one `partners` query per list click, no category / 役職 queries, the form's
shared lists).

### Remaining, and what to decide

1. **The database is far away** (unchanged, the largest factor in development): ~80 ms per round trip and 3 trips for
   a 60-row page. Production on the same network as its database (Tokyo) pays ~1 ms. For development: a local
   PostgreSQL copy, or a database in Tokyo.
2. **Render free sleeps**: 50.7 s for the first request after 15 idle minutes, little CPU after that. A paid instance
   (no sleep) or the planned Tokyo server removes it; nothing in the code can.
3. **PHP time per request** ~65 ms to boot (the pulse, `/up`) and 340–460 ms for a list click, record or form in
   development (no config / event cache, debug on) — mostly Filament drawing the page; production caches config,
   routes, views and events (the 2026-11-18 numbers on the production image were about half).
4. **Applying a list click in the browser** (0.3–0.6 s for 60 rows): Filament redraws the whole page and every cell;
   fewer rows per page is the lever (表示件数, the user's choice).
5. Still one query each: the pager's `count(*)` (the summary cards count the same rows on 4 lists), the bell's unread
   count on every page, comments / 変更履歴 of a record while its panel is closed, one lookup per distinct row supplier
   on the 受注 form.
6. `/messaging/api/unread` takes 8 queries (~0.7 s here), but only when the inbox version changes.

## Pass 2026-11-20: every click felt slow

### How it was measured

- **Where**: the development stack as it is used (Docker on Windows, PHP-FPM, Redis, the shared Neon database in
  Singapore), signed in as the super admin; p50 / p95 of 3–7 runs after a warm-up, nothing else running. Not Render:
  its address is not in the repository and nobody signed in there.
- **Server**: `SFK_SERVER_TIMING` (+ `_LOG`) for time, database time and queries of every page and Livewire request;
  real `/livewire-…/update` requests replayed from each page's own snapshot (re-render, next page, 表示件数, 絞り込む); the
  SQL of a request listed in-process; `EXPLAIN (ANALYZE, BUFFERS)` of the list query (read-only transaction).
- **Browser** (Chrome, the user's session, nothing saved): the main-thread work of a click — handler + style + layout,
  forced synchronously — and Long Animation Frames for attribution. The automated window draws at 1 frame/s, so
  time-to-paint is not usable there; the numbers are the work the browser must do before it can draw.

### Findings

| # | Finding | Measured |
|---|---|---|
| 1 | **A few CSS `:has()` rules restyled the whole page on every small change** — Filament's (`:has(…[aria-expanded=true])` of the builder / rich editor, `.fi-input-wrp:not(:has(.fi-input-wrp-content-ctn))`) and two of ours (`:has(.fi-ta-record-checkbox:checked)`, `:not(:has(textarea))`). Every drop-down sets `aria-expanded`, so opening one restyled the whole document | 日報 list (100 rows): drop-down 60–220 ms, row tick 107–122 ms, row 「…」 116–198 ms, sidebar 58–108 ms of main-thread work per click; 受注 form: drop-down 210 ms |
| 2 | **Every query is a round trip to Singapore** (~80 ms); the SQL itself is fast | list query: 0.6 ms in PostgreSQL, 235 ms seen by the app (latency + transfer) |
| 3 | **FPM workers came and went** (`pm = dynamic`, 2 started, 5 at most): a click on a new worker paid a new TLS login to Neon and cold file lookups; requests fired together waited for a free worker | 5 requests at once after 25 s idle: slowest 1.5–2.0 s while the others took 0.7 s; a new connection's first query ~600 ms |
| 4 | `cache_versions` read from the database on every request and every Livewire click | 1 query each |
| 5 | Record pages: ‹ › looked up the previous and next record (2 queries) on every request of the page; 取引先 / users read one per relation and per row; the 宛先 users list read every time | 受注 record 21 queries / 2.1 s, other records 11–20 |
| 6 | 絞り込む asked the same choices several times (users ×3, 段ボール ×2) | 日報 絞り込む 8 queries / 763 ms |
| 7 | The running app container still had `BROADCAST_CONNECTION=reverb` from an older `.env` while no Reverb was running | a push waited 1 s for the name lookup (laravel.log) |
| 8 | The theme stylesheet / script went through the session middleware | a first visit's asset requests read the signed-in user |

Not a problem: the 表示件数 control (a native select: its options open without the server), JavaScript / CSS
downloads (cached; Livewire 595 KB, Filament 630 KB decoded), query plans (all indexed, < 1 ms).

### Changes

| Change | Where |
|---|---|
| Filament's stylesheet served without the rules of finding 1, replaced by equivalents without `:has()` (`:focus-within`; `> :not(prefix, suffix)`, same elements and specificity) — a Filament update that changes one fails the test; a year in the browser cache, versioned by content | `Core\Filament\Support\FilamentStylesheet`, route `core.filament.css`, `BasePanelProvider::theme()` |
| Our two rules: `.fi-ta-row.fi-selected` (Filament marks selected rows), `.fi-input-wrp:not(.fi-fo-textarea)` | `core::filament.design` |
| Cache versions read from the cache store (Redis) by HTTP requests; a bump writes them there after its commit; another environment's bumps (Render ⇄ local) are seen within 10 s; not where the store is the database | `Core\Application\Cache\CacheVersions` |
| ‹ › worked out once when a record page opens (one UNION query) and kept with the page | `AppViewRecord::recordNeighbours()` |
| Belongs-to relations of a record page — and of its rows — loaded with one query per table on every request; 作成者 / 更新者 by default, more per app (受注, 売上, 日報, 入金, 請求書, 納品書, 見積, 支払) | `Core\Application\Records\RelationBatch`, `AppViewRecord::batchedRelations()` |
| User choices of 絞り込む and コメント宛先 through the ReferenceCache (a saved user bumps it); a choice list shared by fields asked once per request | `Core\Application\Users\UserChoices`, `FilterField::getOptions()` |
| Theme / Filament stylesheets and the theme script without the web middleware (no session, no database) | `modules/Core/routes/web.php` |
| Development PHP-FPM: `pm = static`, 8 workers (each keeps its database connection and file lookups) | `docker/php/php-fpm.conf` (image rebuilt) |
| Development containers recreated (the stale `BROADCAST_CONNECTION=reverb` is gone) | `docker compose up -d --build app queue scheduler` |

### Results (same machine, same data, idle)

Browser — main-thread work of a click, median of 7 (100-row 日報 list, 2,369 elements; 受注 form, 6,188 elements):

| Click | Before | After |
|---|---:|---:|
| Open a drop-down (並び替え, ⚙, user menu) | 60–220 ms | **16–17 ms** |
| Open a drop-down on the 受注 form | 210 ms | **16 ms** |
| Tick a row | 107–122 ms | **21 ms** |
| Open a row's 「…」 | 116–198 ms | **16 ms** |
| Open / close the sidebar | 58–108 ms | **25 ms** |
| Type in / focus a field | 15–16 ms | 15–16 ms |

Server — p50 total (queries), local stack against Neon:

| Request | Before | After | Change |
|---|---:|---:|---:|
| 絞り込む open, 日報 | 763 ms (8) | **354 ms (3)** | −54 % |
| 絞り込む open, 取引先 | 517 ms (5) | **180 ms (1)** | −65 % |
| 受注 record page | 2,082 ms (21) | **1,642 ms (15)** | −21 % |
| Click on the 受注 record page (re-render) | 1,559 ms (16)¹ | **1,179 ms (12)** | −24 % |
| Other record pages (11 apps) | 1,166–1,916 ms (11–20) | **887–1,471 ms (8–15)** | −21 to −31 % |
| List next page / re-render, 取引先 (100 rows) | 957 / 905 ms (5) | **817 / 803 ms (4)** | −12 % |
| List next page, 日報 (100 rows) | 1,235 ms (9) | **1,136 ms (8)** | −8 % |
| 表示件数 → 20, 取引先 | 684 ms (5) | **599 ms (4)** | −12 % |
| Dashboard `/erp`, `/erp/orders` | 750 / 721 ms (5) | **650 / 614 ms (4)** | −14 % |
| 5 requests at once after 25 s idle — slowest | 1.5–2.0 s | **0.67–0.70 s** | −60 % |

¹ measured after the ‹ › and user-choice changes, before the relation batching (no earlier measurement).

### Remaining, and what to decide

1. **The database is in Singapore** — the largest factor left in development: ~80 ms × 4–15 queries per click. A
   database in Tokyo (Neon has no Tokyo region; the planned AWS RDS / Laravel Cloud in Tokyo) or a local PostgreSQL
   copy for development removes most of it. Production on the same network as its database pays ~1 ms per query.
2. **Render free plan** (not measured this time, no address): 0.1 CPU and sleeping after 15 minutes — the first visit
   after a pause waits for the container to start. A paid instance or the AWS / Laravel Cloud server is needed for
   production speed.
3. Filament draws a list on the server at ~3 ms per row and the browser restyles every row (finding 4 of 2026-11-18):
   100 rows ≈ 0.45 MB per click. 60 rows is the default; the user's own choice stays.
4. A record page still reads its comments and 変更履歴 while the side panel is closed (2 queries): loading them when
   the panel opens would save them but make opening wait for the server.
5. List summary cards add up again on every page / sort click (1–2 queries). The DataCache is exact only for Eloquent
   writes, so they are not cached (money must never be stale).
6. Filament's modals (削除 confirmation, アプリの設定 dialogs) are drawn by the server when they open — one round trip
   by design.

## Pass 2026-11-18: big lists

What was measured, what was found, what changed and what is left. Every number below was measured; nothing is
estimated unless it says so.

### How it was measured

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

### Findings

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

### Changes

| Change | Where |
|---|---|
| Table cells are plain links marked `data-sfk-navigate`; one click listener for the page opens them in place (`Alpine.navigate`). No hover prefetch on rows; middle / Ctrl / ⌘ click and new tabs work as before. Swapped into Filament's table view by a Blade precompiler — when Filament changes the line, the stock link comes back and a test fails | `Core\Filament\Tables\RecordLink`, `core::filament.table-links-script`, `CoreServiceProvider` |
| `#[Renderless]` on calls that change nothing on the page: `filterDialog()` (絞り込む), and the drag-and-drop order saves (`reorderViews`, `reorderOutputs`, `saveAppOrder`, `saveMyAppOrder`) | `AppListRecords`, `AppListViewSettings`, `ManagesDocuments`, `PortalPage` |
| First 担当者 of a sale found once for all sales (grouped `min(id)`), not per sale — same results for every 担当者 (checked both ways on the copy) | `Analytics\Application\FirstStaff`, `StaffSalesAnalysis`, `SalesDashboard` |
| `売上分析` reads the years and 実績 once per request | `SalesAnalysis` |
| `受注` form: totals once per form state (per request); suppliers once per request and user; a row's supplier once per supplier | `OrderForm` |
| SQLite search function registered when a connection really opens — the image builds without a database again | `Core\Application\Search\SearchQuery` |
| Measurement: `Server-Timing` header and log (opt-in) | `Core\Http\Middleware\ServerTiming`, `core.server_timing*` |

### Results (same data, same machine, idle)

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

### Remaining, and what to decide

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
