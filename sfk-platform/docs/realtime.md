# Real time (polling; WebSockets optional)

**Production runs without a WebSocket server** (user decision 2026-11-18: no paid Laravel Cloud WebSockets add-on).
`BROADCAST_CONNECTION=null` everywhere by default — local Docker, Render and Laravel Cloud. 通知 (the bell) and
社内メッセージ reach the open pages through the **cache pulses**: every page asks a signed, session-less URL that is a
single cache read (no database, no session) — the bell every 4 s while in view, an open messenger every 1.5 s in view /
8 s in the background, at once when the tab or the network comes back; tabs out of view do not ask. A change fetches
only what changed. Nothing extra to run or pay for: the pulses use the app servers and the Valkey cache the plan already
has. Delay: a new message shows within ~1.5 s in the open messenger, a 通知 / badge within ~4 s.

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
