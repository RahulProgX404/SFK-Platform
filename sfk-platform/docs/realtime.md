# Real time (polling; WebSockets optional)

**Production runs without a WebSocket server** (user decision 2026-11-18: no paid Laravel Cloud WebSockets add-on).
`BROADCAST_CONNECTION=null` everywhere by default — local Docker, Render and Laravel Cloud. 通知 (the bell) and
社内メッセージ reach the open pages through the **cache pulses**: every page asks a signed, session-less URL that is a
single cache read (no database, no session) — the bell every 4 s while in view, an open messenger every 1.5 s in view /
8 s in the background, at once when the tab or the network comes back; tabs out of view do not ask. A change fetches
only what changed. Nothing extra to run or pay for: the pulses use the app servers and the Valkey cache the plan already
has. Delay: a new message shows within ~1.5 s in the open messenger, a 通知 / badge within ~4 s.

## Live pages (売上分析, 日報分析, the dashboard)

Pages whose figures add up other apps' records follow changes without a reload (user 2026-11-21; the dashboard /erp —
every card, every user type — 2026-11-24). Core
`Application\Realtime\DataPulse`: a module lists the tables its live pages read (`DataPulse::watch()`; the pages say
them in `liveTables()` — 売上分析: 売上, 予想 / 仕掛, users, 取引先; 日報分析: 日報, users; both: `DataPulse::REFERENCE`,
access rights). When a record of a watched table is saved or deleted anywhere (the Eloquent listener of
CoreServiceProvider, also queue jobs and commands), its stamp in the cache is renewed after the commit, once at the end
of the request (a long process: at most once a second). The stamps travel on the page pulse (`x.d`, cache reads only).
The page draws a marker with the stamps it was drawn with (`core::filament.live-data` inside its Livewire component);
`core::filament.live-data-script` compares each pulse with it and runs `$refresh` on the component when a stamp moved —
not while the user is typing in the page (then on leaving the input). Delay: about the pulse interval (~4 s in view, at
once when the tab comes back). With WebSockets on, a page with a marker asks the pulse every 4 s itself. Writes outside
Eloquent (query-builder updates, a pivot sync without its parent) stamp nothing; the cache is per environment, so a
change made on Render is not seen by a page served locally.

**The dashboard** (Analytics `DashboardCard`): each card is its own Livewire component with its own marker and its own
`liveTables()` (registered from `DashboardCard::CARDS`, which DashboardTest keeps complete), so a change redraws only the
cards that read it — same period, scope and chosen year; the figures come fresh because every Eloquent write also
renews the DataCache version. The page itself has a `DataPulse::REFERENCE` marker: when who may see what changes
(財務分析を表示 / 範囲, アクセス権, apps) the cards shown, 新規作成 and アプリ are redrawn.

| Card | Tables (+ `@reference`) |
|---|---|
| 売上合計 | sales, sales_plans, sales_forecasts |
| 売上概要 | sales, invoices, receipts, partners |
| 入出金 | receipts, receipt_lines, payments, sales, invoices, partners |
| 売上の推移 · 売れ筋 TOP3 | sales |
| 上位の売上先 | sales, partners |
| 担当者別売上 | sales, partners, users |
| 作業者ランキング | daily_reports, users |
| 休暇・勤怠予定 | leave_requests, users |
| クイックアクション / 最近表示した項目 | leave_requests, consumable_orders, partners, users, `@me:app-usage` |
| 最近のアクティビティ | notifications, record_versions, record_comments |
| 個人の概要 | daily_reports, leave_requests, users |
| 勤務時間・収入の推移 | daily_reports, users |

`@me:…` channels (`DataPulse::PERSONAL`) are one user's own: `DataPulse::touchedBy($channel, $userId)` stamps them at
once, and the pulse and the marker carry the stamp of their own user. `AppUsageLog::touch()` (one upsert, no model
events) stamps `@me:app-usage`, so 最近表示した項目 follows the apps the user opens in another tab without moving anyone
else's dashboard. Sales whose 請求書 is issued get `invoice_id` by a query-builder update, but the same request saves
the 請求書 (invoices is watched), so 売上概要 follows. Not followed: a record's title changed by someone else in
最近表示した項目 / 最近のアクティビティ (until the next change of those cards' tables), and the day turning (今日・明日).

**Laravel Reverb stays as an option** (code and dependency kept): set `BROADCAST_CONNECTION=reverb` with the `REVERB_*`
settings and the same pages are also pushed to, while polling drops to a safety net. Turn it on only where a Reverb
server exists at no cost (local: `docker compose --profile realtime up -d`; Render: start.sh runs it in the container)
— on Laravel Cloud it would need the paid WebSockets add-on, which is **not** used.

The rest of this page describes the optional push.

## How it works

```
app (PHP)  ── Realtime::send() after the commit ──►  Reverb (php artisan reverb:start)  ──►  open pages
   ▲   HTTP publish to REVERB_HOST:REVERB_PORT                     ▲  wss://<site>/app/<key> (nginx forwards /app)
   └──────────── POST /broadcasting/auth (may this user subscribe?) ┘
```

- **Server** (`Modules\Core\Application\Realtime\Realtime`): `send($channels, $event, $payload)` publishes after the
  transaction commits, on private channels only, with a 2 s timeout; when Reverb cannot be reached the error is reported,
  pushes pause 30 s and the request carries on (the pages keep polling). Channels and who may subscribe are declared
  with `Realtime::channel($pattern, $rule)` in the module providers.
- **Browser** (`core::filament.realtime-script`, part of theme.js): our own small client of the Pusher protocol Reverb
  speaks (no pusher-js / laravel-echo). One connection per page lifetime, kept across the SPA page swaps, opened on the
  first `window.sfkRealtime.subscribe(channel, event, handler)`; private channels are authorized by the app
  (`POST /broadcasting/auth`, CSRF), pings answered, reconnect with back-off 1 s → 30 s and at once when the tab or the
  network returns. `<meta name="sfk-realtime">` (key, address, user) is in every panel page while real time is on.

| Channel | Who may subscribe | Events |
|---|---|---|
| `sfk.user.<id>` | that user | `notification` (the 通知 pulse: title, body, link — the bell refreshes, a toast shows) · `inbox` (messenger inbox version → badge, inbox) |
| `sfk.conversation.<id>` | current members (left / removed: refused) | `changed` (new / edited / reacted / pinned / unsent → the open chat syncs) · `read` (read marks) · `typing` (`{user, typing}`) |

Pushes carry versions, ids and the 通知's own title — never the text of a message: the page fetches what it may see
through the authorized API. With a live socket the bell's pulse is asked only every 60 s and the messenger's every 15 s
(presence, safety net); after a reconnect both catch up once.

Measured (local Docker, 2026-11-17): publish incl. the HTTP call to Reverb 24 ms; the event reached a subscriber through
nginx before the publishing request returned. In the browser the badge and the open chat changed without polling.

## Security

- Only private channels; `/broadcasting/auth` (signed-in users, 120 / min) asks the channel rule — a user's own channel,
  conversation membership (one indexed query). The socket key is public by design; the secret never leaves the server.
- Reverb accepts connections only from the site's own host (`allowed_origins` = host of `APP_URL`, plus
  `REVERB_ALLOWED_ORIGINS`), refuses client events (`accept_client_events_from = none`: 入力中 goes through the app,
  which checks the signed pulse address) and rate-limits messages per connection.
- The Content-Security-Policy allows the WebSocket origin in `connect-src` (`Realtime::cspOrigins()`).
- Only nginx's `/app` (the browsers' socket) is public; Reverb's publishing API (`/apps`) stays on the internal address.

## Settings

| Env | Meaning |
|---|---|
| `BROADCAST_CONNECTION` | `reverb` = push; `null` = poll only |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | the app's credentials on the Reverb server (key public, secret private) |
| `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` | where the **app** publishes (internal) |
| `REVERB_PUBLIC_HOST`, `REVERB_PUBLIC_PORT`, `REVERB_PUBLIC_SCHEME` | where **browsers** connect; empty = the page's own origin (nginx `/app`) |
| `REVERB_ALLOWED_ORIGINS` | more hosts allowed to connect (comma separated) |
| `REVERB_SCALING_ENABLED` | several Reverb servers behind a load balancer, coordinated through Redis / Valkey |

## Where it runs

| Environment | Reverb | Notes |
|---|---|---|
| Laravel Cloud (production) | **none** — `BROADCAST_CONNECTION=null`, the pages poll | no WebSockets add-on, no extra cost (decided 2026-11-18) |
| Local Docker | off by default; opt in: `BROADCAST_CONNECTION=reverb` in `.env` + `docker compose --profile realtime up -d` (service `reverb`, nginx forwards `/app` to `reverb:8080`) | `.env`: `REVERB_HOST=reverb`, port 8080, http |
| Render (free) | off by default (`render.yaml`: `null`, like production); with `reverb` it is started by `docker/render/start.sh` inside the web container (127.0.0.1:8080), nginx forwards `/app` | Render generates key / secret once; one more PHP process (~40 MB) |

After changing the Reverb settings restart the Reverb process (`docker compose restart reverb`); after changing the app
key every page reconnects by itself on its next load.
