# 移行 (legacy data import)

Roadmap Phase 5: the data of the legacy system is taken over from its app exports — one JSON file per app
(`data_json/<app id>_<app name>.json`: `app`, `fields`, `records`). The folder holds business and personal data: it is
git-ignored and never committed.

```
php artisan legacy:import                 # every app, in the order below
php artisan legacy:import 02 03 01        # some apps (their legacy numbers; 「23」 = both 23. apps)
php artisan legacy:import --list          # the apps, their order and their files
php artisan legacy:import --path=/data    # another folder
```

Safe to run again: records are matched by their legacy record number (`legacy_id`) and updated. Each app writes its
warnings to `storage/logs/legacy-import/<app id>.log` (values the new system cannot hold, references not found, what is
not taken over, reconciliation differences).

## Order — the business process (SYSTEM_BRAIN 02_BUSINESS_PROCESSES)

| Step | App | Into | Notes |
| --- | --- | --- | --- |
| 10 | 02 従業員マスタ | users | matched by 作業員コード, else name; new ones get `<作業員コード>@employees.invalid` (the app holds no emails) |
| 20 | 03 機械マスタ | machines | |
| 30 | 01 取引先マスタ | partners | `LegacyPartnerImport` |
| 40 | 19 消耗品マスタ | consumables | 仕入先 by 得意先ID |
| 45 | 13 在庫管理 (not rebuilt) | consumables + one 調整 stock movement | |
| 50 | 09 見積書管理 | quotations | |
| 60 | 04 受注入力 | orders (+ 用紙情報 / 印刷 / トムソン… rows) | jobs without 注番 get the next numbers |
| 70 | 12 製袋予定管理 | bag_schedules | a 注番 that is no job → フリー入力 |
| 80 / 85 | 08 日報入力, 08N 【新】日報入力 (not rebuilt) | daily_reports | 08N: one report per 日報詳細 row (legacy_id = 1,000,000,000 + row id) |
| 90 | 14 事故報告管理 | accident_reports | |
| 100 | 05 納品(仕入)入力 | purchases | |
| 110 | 15 運送請求データ取込 | freight_charges | |
| 120 | 20 消耗品発注 | consumable_orders | |
| 130 | 07 支払入力 | payments | |
| 140 | 23 買掛管理 | payable_months (derived) | only 消費税端数調整 taken over |
| 150 | 06 売上入力 | sales | |
| 160 / 170 | 10 納品書管理, 11 請求書管理 | delivery_notes / invoices | rows linked to the sales by 売上No |
| 180 | 21 売入金管理 | receipts | |
| 190 / 195 | 23 売掛管理, 23-2 売掛管理(締日別) | receivable_months (derived) | only 消費税端数調整 taken over |
| 200 / 210 | 22 過去の売上データ, 25 販売先別売上計画 | past_sales / sales_plans | |
| 220–240 | 16 申請管理, 17 安全衛生チェック, 18 アルコールチェック | leave_requests / safety_checks / alcohol_checks | |

## Rules (every app)

- **Record numbers kept**: `legacy_id` = the legacy record number, which also becomes the `id` when free (the numbers
  staff know stay the same); the id sequences continue after the highest. Document numbers (注番, 納品(仕入)入力No,
  納品書番号, 請求書番号, 取引先コード) are kept and their sequences continue after the highest.
- **People**: legacy logins (`作成者` / `更新者` / user fields, emails) are the users of that email; else the employee of
  02 with the same name, who then gets the email; else a new employee. Nobody can sign in before an administrator issues
  a password (random temporary password, no dashboards).
- **Who / when**: 作成者 / 更新者 and the times are the legacy ones; a record new to the system starts its 変更履歴 with
  version 1 by its 更新者 at its 更新日時. No notifications are sent, no observers run.
- **Derived values are derived again** (SYSTEM_BRAIN 16_REBUILD_SPEC database 10): totals, tax, 月度, the jobs' 製造済数量
  / 仕入金額 / 売上済数量, the payables and receivables. Where they differ from the stored legacy values the log says so
  (reconciliation) — expected where the legacy formulas or automations had defects (17_CONTRADICTIONS).
- **Options**: a value the new system does not offer is left blank and reported; on plain text columns an option that
  was removed from the legacy list later (e.g. 口糊 「HM」) stays on the old record (reported).
- **Not taken over**: attachments (the export holds only their names — reported), calculated legacy fields, the
  process-management 作業者, the 04 table 売上履歴.

## Reconciliation of the first run (dev database, 2026-10-29)

- 04: 6,919 jobs; the recalculated 見積原価合計 / 小計 equal the stored ones.
- 05 → 04 仕入金額: 1,190 jobs lower than stored — the legacy Customine added a purchase again on every re-save (C-02);
  the new value is the sum of the job's purchases.
- 08 → 04 製造済数量: the legacy 08 had no roll-up (only 08N's Customine wrote 製造済数量), so 4,743 jobs stored 0; the
  new rule (JobProduction) adds 出来上 of every report — of every process step — so jobs reported at several steps
  exceed their 数量. **To be decided** (see below).
- 23 買掛: 1 of 1,546 months differs; 23 売掛: 20 of 2,736; 23-2: 37 of 2,840.
- 10 / 11: 115 delivery notes stored 消費税合計 0 for rows without 税率 (the note's 10% applies now); 39 / 40 rows refer
  to sales deleted in the legacy app (the rows keep what was printed).

**Open question**: 製造済数量 of a job = Σ 出来上 of all its daily reports (08N rule) counts each process step; whether
only the last step (or the reports with ステータスの更新) should count is a business decision.
