# 社内メッセージ (Messaging module)

Employees talk inside the ERP instead of switching to LINE / Messenger: one-to-one トーク, グループ, チャンネル (部署 /
プロジェクト), files, images, voice messages, stickers, reactions, replies, mentions, 重要 / 至急 messages, shared ERP
records and 全社アナウンス. Company settings, channels, figures and an audit are in メッセージ管理 (administrators). Module `modules/Messaging` (business, panel `erp`,
toggle `MODULE_MESSAGING_ENABLED`). The page is メッセージ in the dashboard's sidebar and the 💬 button after アプリ in the dashboard's top bar
(`/erp/messages`, `Filament\Pages\MessengerPage`).

## Architecture

```
MessengerPage (Filament page, drawn once with the inbox, the people, settings, stickers)
  └─ Resources/assets/messenger.js  — one Alpine component, talks JSON to /messaging/api (no Livewire round trips)
       ├─ MessengerController (API)  ─┐
       ├─ MessengerFileController     ├─ Application: MessagingAccess (every check) · Conversations · Messages ·
       └─ MessengerPulseController    │   MessageFeed · MessagePresenter · Inbox · AttachmentStore · MessageSearch ·
                                      │   Announcements · Stickers · Preferences · RecordLinks · Directory · Signals ·
                                      │   MessagingSettings · MessagingAudit
                                      └─ Domain: ConversationType, ParticipantRole, MessageType, AttachmentKind,
                                         GroupRule / GroupSettings, TimeWindow, DirectKey, MessageText, Priority,
                                         NotificationLevel, Availability
MessagingAdminPage — メッセージ管理 (システム group): 設定 · チャンネル · 統計 · 監査ログ
Core RecordCards — the cards of shared ERP records, registered by each module's provider
badge-script (in every page's theme.js via ThemeAssets::extend) — the unread badge, toasts outside the messenger
header-button — メッセージ in the dashboard's top bar, right after アプリ (with the unread badge)
```

The messenger's CSS / JS (~90 KB) are served only on its page (`MessengerAssets`, versioned, cached a year); the page
is excluded from SPA navigation so its script starts before Alpine.

## Data

| Table | Holds |
|---|---|
| `conversations` | type (direct / group / channel), name, description, image, settings (group rules; channels: kind 部署 / プロジェクト, 公開, 投稿できる人), `direct_key` (one per pair of users, unique), pointer to the newest message, `archived_at` (channels) |
| `conversation_participants` | membership + each member's own state: role, `left_at` / `removed_by`, read / delivered watermarks, `visible_from_message_id`, `cleared_message_id` (「トークを削除」), pinned (+ `pin_order`) / archived / favorite / muted_until, `notification_level` (all / mentions / none) |
| `messages` | sender (null = system line), type, body, `priority` (0 通常 / 1 重要 / 2 至急), meta (sticker, forward origin, @全員, shared record `{app, id}`, `moderated_by`), reply, forward, `client_id` (unique per sender — no double sending), edited / unsent |
| `message_attachments` | files on the private uploads disk (null message = uploaded, not sent yet) |
| `message_reactions` | one per person and message |
| `message_hides` | 「自分のみ削除」 |
| `message_edits` | audit: the text before every edit, 送信取消 and removal by a 管理者 (`moderate`) |
| `message_mentions`, `message_pins` | mentions (for the @ mark / 通知), pinned messages |
| `messaging_stickers` | company stickers (administrators) |
| `messaging_preferences` | 通知音, ブラウザ通知, 本文を表示, オンライン状態を表示, おやすみモード, 在席状況 (`status`: auto / away / dnd) |
| `announcements`, `announcement_attachments`, `announcement_recipients` | 全社アナウンス with `priority` (normal / important / urgent) and `expires_at`; audience 全員 / 従業員カテゴリ / people / the publisher's groups, fixed when published, one row per recipient with 既読 / 確認 |
| `messaging_settings` | the company's settings from メッセージ管理 (key → JSON value; over the config defaults) |
| `messaging_audit_logs` | administrative actions: channel created / archived, members added / removed / roles, message removed by a 管理者, settings changed, announcement published (ids and names — never message texts) |

Read state is **watermarks**, not a row per message and reader: a member reading writes one row; "既読 N" and the
unread counts are derived from them (one grouped query over the `(conversation_id, id)` index). History is paged by
keyset (`id < ?`, 40 a page), never OFFSET, never the whole conversation. `messages.body` and attachment names have
trigram indexes (PostgreSQL).

## Real time (polling; WebSockets optional)

Production polls the cache pulses below (no WebSocket server, no paid add-on — [realtime.md](realtime.md)). With the
optional Laravel Reverb push (`BROADCAST_CONNECTION=reverb`) changes are also **pushed**: `changed`, `read` and `typing`
on the conversation's channel `sfk.conversation.<id>` (current members only), `inbox` on each member's
`sfk.user.<id>`. A push carries a version or an id, never a message: the open chat then fetches only what changed
(`sync`: new messages + messages changed since the last sync; `reads`; `inbox`) through the authorized API.

`Signals` also keeps the same versions in the cache (`inbox:<user>`, `conversation:<id>`, `read:<id>`, `typing:<id>`,
`seen:<user>`), which the pages poll (the production path) and which give presence:

- An open messenger asks `GET /messaging/pulse/{user}/{conversation}/{peer}/{key}` (outside the web middleware, signed
  per user + conversation, `MessengerPulse`; a cache read, no session, no database) every 15 s while its socket is
  connected (presence, safety net) — every 1.5 s in view / 8 s in the background without one. 入力中 is a POST to the
  same address (at most one per 3 s while typing), which also pushes `typing`.
- Every other page: the unread badge listens to `inbox` on the user's channel; without a socket it reads the inbox
  version from Core's 通知 pulse (`PulseChannels`, key `m`).
- オンライン / 最終アクセス come from `seen:<user>` (refreshed by every page's pulse, at most every 20 s).
- Measured: a message pushed at commit reached the other user's page before the sender's request returned; the open chat
  showed it after one sync request.

## Features of the 2026-11-18 upgrade

- **Look** (`../messenger-design.png`): the user's avatar with 在席状況 at the top of the list, underline tabs (すべて /
  未読 / お気に入り / 個人 / グループ / チャンネル / アナウンス / 通知オフ / アーカイブ), grey bubbles for others and blue for
  one's own with the time and ✓✓ inside, 重要 (green) / 至急 (red) bubbles, reactions on the bubble's edge, the avatars
  of who read the newest message, a pill composer (emoji, expand, attach, ＋ menu: 画像 / ファイル / スタンプ /
  ERPレコード / 重要・至急として送信, voice).
- **チャンネル**: created by administrators (or everyone — `channel_creators`), kind 部署 / プロジェクト, members by name or
  従業員カテゴリ, 公開 (listed in チャンネルを探す and joinable) or invite-only, 投稿: everyone or 管理者のみ (read-only
  channels for news). An archived channel stays readable, nobody posts. Archiving: the channel's 管理者 or an administrator.
- **Priorities**: 重要 / 至急 messages are marked in the list and the chat and send a 通知 (kind `messaging.priority`).
- **通知 levels** per conversation (details panel): すべて / メンションのみ / 通知しない; 通知オフ for a time still lets
  mentions and replies through. One 通知 per member and message, for its strongest reason (mention › reply › @全員 ›
  priority); the button says トークを開く, and no toast appears for the conversation the user is looking at.
- **Replies** notify the author (`messaging.reply`).
- 未読にする / すべて既読, drag order of pinned conversations, 日付へ移動 (calendar), the details' リンク tab, メッセージ情報
  (sender only: 既読 / 未読 with times, as the read watermarks say).
- **Moderation**: a group's / channel's 管理者 removes a member's message (「管理者によって削除されました」), never in a
  direct conversation; the text stays in `message_edits`, the action in the audit.
- **Presence**: オンライン / 離席中 (5 min without activity, or chosen) / 取り込み中 (chosen; also おやすみモード) / オフライン
  with 最終アクセス; the company can switch presence and 既読 off.
- **Search** filters: 個人 / グループ / チャンネル / 画像 / ファイル / リンク / ピン留め, plus person and dates.
- **ERP records** (ＥＲＰレコード in the ＋ menu): the sender picks one of the records they may open (Core
  `RecordCards::search`, asked as them); the message stores only `{app, id}`. Each reader sees a card drawn from the record
  as it is now — title, the app's chosen fields under their フィールドのアクセス権 and 開く — only if they may open it;
  otherwise only 「<app>のレコード（閲覧権限がありません）」; a deleted record says so. Registered: 01. 取引先マスタ, 受注, 売上,
  請求書, 納品書, 仕入, 日報, 製袋予定.
- **メッセージ管理** (`/erp/messages/admin`, administrators): 設定 (編集・送信取消の期間, who creates groups / channels,
  attachment size, retention, 既読, オンライン状態), チャンネル (archive / reopen), 統計 (counts and sizes of the last 30
  days, by 従業員カテゴリ — never texts) and 監査ログ.

## Security

- `MessagingAccess` is asked for every read, write, download and forward. A conversation is open only to current
  members; a wrong id answers **404** like a missing one. Members who leave or are removed lose the conversation, its
  history and its files entirely.
- History rule: a member added later sees the earlier history when the group shares it (`share_history`, default on),
  else only messages after joining.
- Uploads: allowed extensions only (PDF, DOCX, XLSX, CSV, TXT, JPG/PNG/WEBP/GIF; voice WEBM/OGG/M4A), content checked
  with finfo against the extension, 20 MB, random file names on `Uploads::disk()` (private). Served only through
  `/messaging/attachments/{id}` after the check, with `nosniff` and a sandbox CSP; only images, audio and PDF open
  inline. Files of an unsent message are kept for the audit but nobody can read them.
- Texts are stored as typed and escaped when drawn (links only http(s), `rel=noopener noreferrer`). No outside link
  previews (no server requests to typed addresses). Links to this system's record pages become cards only for readers
  who may open the record (`RecordLinks` → Core `RecordDirectory`).
- CSRF on the API (web middleware); rate limits: send 60/min, upload 30/min, other writes 120/min per user.
- Shared ERP records: checked when sent (the sender must be able to open it) and drawn per reader (app permissions,
  レコード / フィールドのアクセス権); nothing of the record is stored with the message.
- Read-only and archived channels refuse posts and forwards on the server (`MessagingAccess::canPost`), not only in the UI.
- Notifications (mentions, replies, priorities, announcements) never contain the message text; toasts and browser notifications show the
  text only with 通知に本文を表示 (off by default).
- Not end-to-end encrypted: HTTPS in transit, the database and bucket at rest. E2E would remove search, audit and
  previews — not done.

## Company policy (config/messaging → `Config/config.php`)

Administrators change these in メッセージ管理 → 設定 (stored in `messaging_settings`, read once per request by
`MessagingSettings::ensure()` through ReferenceCache); the config gives the defaults.

| Key | Default |
|---|---|
| `edit_window_minutes` / `unsend_window_minutes` (`MESSAGING_EDIT_WINDOW_MINUTES` …) | 24 h |
| `group_creators` (`MESSAGING_GROUP_CREATORS`) | `everyone` (or `administrators`) |
| `channel_creators` | `administrators` (or `everyone`) |
| `max_attachment_mb` | 20 |
| `read_receipts` / `presence` | on |
| `profile_fields` (shown in a direct conversation's details) | 役職, 従業員カテゴリ (add `email`, `phone`) |
| `retention_days` (`MESSAGING_RETENTION_DAYS`) | null = keep; set to delete older messages + files nightly |
| 全社アナウンス | administrators (ユーザー種別 管理者 / システム管理者) |
| company stickers | administrators |

Group rules (グループの設定, by the group's 管理者): メンバーの追加, グループ情報の変更, ピン留め, @全員 — 全メンバー or
管理者のみ; 新しいメンバーに過去のメッセージを表示.

## Scheduled commands

- `messaging:announcements` — every minute: notifies scheduled announcements whose time came.
- `messaging:prune` — 03:30: removes uploads never sent (24 h) and, with a retention set, old messages and files.

## Not done yet / ideas

Video / audio calls, screen sharing, translation, AI summaries, tasks from messages, order-specific rooms — the data
model leaves room (conversation types, message meta). Voice messages need a browser with MediaRecorder (current
Chrome, Edge, Safari, Firefox) and the microphone permission (`Permissions-Policy: microphone=(self)`).
