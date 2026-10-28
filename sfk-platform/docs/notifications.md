# 通知 (notifications)

Users learn what concerns them through the **bell** in the top bar of every dashboard (Filament database
notifications, polled every 30 s) and, when they ask for it, by **e-mail**. Every 通知 is sent through one Core
service, `Modules\Core\Application\Notifications\Notifier` — never `Filament\Notifications\Notification::sendToDatabase()`
(that is for toasts only, `->send()`).

Source: SYSTEM_BRAIN 11_PERMISSIONS §3 (legacy notifications), 16_REBUILD_SPEC ("Events → in-app/e-mail notifications"),
the rules cited below.

## Who receives a 通知

`Notifier::send(Notice, $recipients, $actor)` leaves out a recipient who

| Rule | Why |
|---|---|
| is the `$actor` (who caused it) | they know already |
| can no longer sign in (退職・定年退職・解雇) | — |
| switched the kind off in プロフィール → 通知設定 | per-user choice |
| may not open the record the 通知 is about | app permissions, the app's own narrowing (e.g. 16: own requests only) and レコードのアクセス権 — the legacy system notified only users who could see the record; nothing of a record reaches a user who may not see it |
| is not an administrator, for an administration kind (`forAdministrators`) | e.g. 要発注, 酒気帯び |
| already has a 通知 with the same `Notice::once()` key | daily checks, conditions lasting several days |

The 通知 is written synchronously in the caller's transaction (rolled back with it). The e-mail copy is queued after
the commit (`SystemNotificationMail`, to the 連絡用メールアドレス, else the login address). Read 通知 are deleted after
180 days, every 通知 after a year (`core:notifications-prune`, nightly).

## Kinds

| Key | 通知設定 label | Sent when | To | Rule |
|---|---|---|---|---|
| `comment` | コメント | a コメント is written on a record of any app | 宛先 + the app's `commentRecipients()` (作成者 / 更新者) | legacy app notification |
| `hr.leave-request.status` | 16: ステータスの変更 | 申請する / 承認済 / 差戻し | administrators on 申請する, else 申請者 + 作成者 | BR-HR-01 |
| `purchasing.consumable-order.status` | 20: ステータスの変更 | 承認依頼 / 承認する | administrators on 承認依頼, else 作成者 + 発注者 | BR-PU-09 |
| `purchasing.purchase.overrun` | 05: 仕入原価超過 | a purchase saved with 仕入原価超過 (new, or 仕入原価超過 / 通知先 changed) | 通知先 | BR-PU-05 |
| `master-data.consumable.reorder` | 19: 要発注 | the stock falls to the 発注点 (stock movement, new 発注点; daily check as safety net) — once until it is above again | administrators | BR-MD-05 |
| `production.accident-report.created` | 14: 事故報告 | a new 事故報告 | administrators + 営業関係者 | APP_14 (legacy review switched off) |
| `hr.alcohol-check.detected` | 18: 酒気帯び運転の有無: 有 | a check saved with 有 for 運転前 / 運転後 that was not 有 before | administrators | BR-HR-04 |
| `orders.order.due` | 04: 納期が近い受注 | every morning: 納期 today or on the next weekday, ステータス not 発送完了 — once per job and 納期 | 担当者, else 作成者 | APP_04 (new) |

The last three kinds and the daily 納期 reminder are new (the legacy system had no notification for them).

## Adding a kind

1. Register it in the module's service provider (`bootModule()`):

   ```php
   $this->app->make(NotificationKinds::class)->register(new NotificationKind(
       key: OverrunAlert::NOTIFICATION, label: '仕入原価超過', appCode: 'purchase-entry',
       icon: Heroicon::OutlinedExclamationTriangle, color: 'warning',
   ));
   ```

2. Send it from the Application layer:

   ```php
   $this->notifier->send(
       Notice::make(self::NOTIFICATION)->about($purchase, $purchase->purchase_number)->body('仕入原価超過があります。'),
       $purchase->notifiedUsers()->get(),
       $actor,
   );
   ```

   `about($record, $name)` gives the title 「<app name>: <name>」, the link to the record page and the visibility check
   (Core `AppRecordDirectory`, through the app's `AppResource`). Prefer it to `title()` / `url()`.

3. Something found by looking (dates, thresholds) is a daily check: implement `NotificationCheck`, pass it as
   `check:` of the kind and give each Notice a `once()` key. `core:notification-checks` runs the checks every
   morning at 07:30 (scheduler container); `--date=Y-m-d` runs them for another day.

The kind appears in 通知設定 by itself (受け取る on, メールでも受け取る off by default).
