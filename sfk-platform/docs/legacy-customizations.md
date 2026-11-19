# Legacy customizations — port check (2026-11-19)

Every behaviour the legacy JavaScript / Customine flows added, checked against this system: kept without the legacy
defects. Status: **DONE** (existing, free of the defects), **FIXED** (existing, a defect removed), **IMPLEMENTED** (new).
Open business questions (❓) use the safe default and are listed at the end. Accounts receivable / payable:
`docs/receivables.md`.

## Cross-cutting rules

| # | Rule | Status | Where | Notes |
|---|---|---|---|---|
| R1 | Secrets server-side | DONE | `Orders/Application/FaxImport/ClaudeFaxOrderReader.php`, `Orders/Config/config.php` | The FAX reading runs on the server with `ANTHROPIC_API_KEY`; nothing reaches the browser. |
| R2 | Permissions server-side | DONE | `Core/Access`, `AppAccess`, `AppResource::appCan()` | App permissions / アプリ管理 / ユーザー種別, set in the admin screens; no user names or e-mails in code (AGENTS.md). |
| R3 | Derived values computed | DONE / FIXED | job totals, AR/AP, rankings | Recomputed from source rows on every change; nothing is written when a page is viewed. Fixed here: credits (AR/AP), see receivables.md. |
| R4 | One transaction per action | FIXED | `Core/Filament/AppPages/AppListRecords::deleteRecords()`, `Sales/Application/Sales/SaleDeletion.php` | Issuers and the single delete already ran in transactions; the list's bulk delete is now one transaction, and deleting a sale includes its documents. |
| R5 | Optimistic locking | IMPLEMENTED | `Core/Application/Records/RecordVersion.php`, `AppEditRecord`, `AppListRecords` (インライン編集) | The version (更新日時) read when a record is opened must still be the stored one on save; otherwise 「他のユーザーがこのレコードを更新しました…」 and nothing is written. Precision is one second; a figure recalculated by the system (e.g. a job's totals after a purchase) also counts as a change. |
| R6 | IDs, not names | DONE | all models | Foreign keys everywhere; documents group by `customer_id`; names of 取引先 are unique. The FAX import resolves the issuer / 製造場所 name to its partner id once, at import. |
| R7 | Structured keys | DONE | `receivable_months` / `payable_months` | (entity id, YYYY-MM) unique + DATE `month_start` (CHECK on PostgreSQL). |
| R8 | One timezone | DONE | `config/app.php` (`Asia/Tokyo`) | Today, months and business years are computed on the server. |
| R9 | Business year May → April | DONE | `Analytics/Domain/FiscalYear.php` | One helper (`startingYear()`, `MONTHS`, `days()`), used by 売上分析, 日報分析, plans. |
| R10 | Choices as enums / constants | DONE | `ClosingDay`, `RoundingMode`, `TaxRate`, `OrderChoices`, … | Values are validated against them; the FAX mapping writes only listed options (else a warning, nothing written). |
| R11 | Safe numbering | DONE | `Core/Application/Numbering/DocumentNumberGenerator.php` (+ test) | Locked sequence row per document type, 7 digits; a deleted number is never reissued. The `-R<n>` revision suffix belongs to the re-issue flow, which is not built (❓ Q4). |
| R12 | Idempotent batches | DONE / FIXED | `sales:receivables-roll`, `purchasing:payables-roll`, 一括更新 / 未作成 buttons | Every run rebuilds all months from the sources; a second run changes nothing. |
| R13 | Audit trail | DONE | `RecordsHistory` on 売上, 納品書, 請求書, 入金, 仕入, 支払, 売掛 / 買掛 months, 受注 | 変更履歴 with who and when. |
| R14 | Accounts receivable | FIXED | `docs/receivables.md` | Applied to 23, 23-2 and 23 買掛. |

## Items

| # | Item | Status | Files | Notes / open questions |
|---|---|---|---|---|
| 1.1 | FAX purchase-order import | DONE | `Orders/Application/FaxImport/*`, `Orders/Domain/FaxImport/*`, `CreateOrder` | Issuer check, 検印欄 → one surname → second reading, 金山 override, 口糊 rules with warnings, preview, blank values never overwrite, one call (+1 for 検印欄), 429 wait time. The issuer / stamp rules are in `modules/Orders/Config/config.php` (env), not an admin screen (❓ Q8). |
| 1.2 | Purchase total & 社内原価 per job | DONE | `Purchasing/Application/Purchases/PurchaseOrderTotals.php`, `Production/Application/DailyReports/JobProduction.php` | By `order_id`, on every save / delete of a purchase or daily report. |
| 1.3 | 利益 / 売上金額済 | DONE | `Sales/Application/Sales/SaleOrderTotals.php`, `Orders/Domain/Orders/JobCosting.php` | All sales of the job, without tax (❓ Q1); read-only. |
| 1.4 | Order list summary | DONE | `Orders/.../ListOrders::orderSummary()` | One SQL aggregate over the filtered list; 利益率 「—」 without sales. The cards are always shown (the app list design); not collapsible. |
| 1.5 | Bag details section | DONE | `OrderLayout` (`bagOpen`, open by default) | |
| 2.1 | Sales list summary | FIXED | `Sales/.../ListSales::salesSummary()` | 粗利 = 売上金額(税抜) − 実原価合計, 粗利率 over 税抜 (was 税込: tax counted as profit, ❓ Q1). One SQL aggregate; Filament pages work on phones. |
| 2.2 | Delivery notes from sales | DONE | `DeliveryNoteIssuer`, `CustomerDocuments` | By customer id; validation of every group first; existing note → rows synced by 売上No; one transaction. Nothing is decremented or counted: 済 = the sale's link, 売上残数量 of the job = 数量 − Σ sales (each sale is one delivery). Re-issue with `-R<n>` not built (❓ Q4). |
| 2.3 | Invoices from sales | FIXED | `InvoiceIssuer`, `Core/Domain/Billing/BillingCycle::closingDateOf()` | 請求日付 by default = the closing date of the sales' 月度 (5〜25日 → that day, 末日 / blank → last day of the 月度), no longer of today (❓ Q2). 前月御請求額 from `ReceivableLedger::openingBalance()` (credits included; a gap is refused). Unit price is filled on the rows (❓ Q3). 21 record created with the invoice. |
| 2.4 | Delete sales with cascade | FIXED | `Sales/Application/Sales/SaleDeletion.php`, `Core/Application/Records/DeletionGuard.php`, `RecordDeletion` | Blocked when it would empty an invoice whose 21 record has 入金 / 手数料 / 歩引き ≠ 0 (named); emptied 納品書 / 請求書 deleted (the invoice with its 21 record), others re-totalled and 請求金額 of the 21 record updated; job profit and AR follow. Warning lists all of it; two confirmations; one transaction. Hard delete as before (❓ Q5). |
| 2.5 | Payment tracking per invoice | DONE / IMPLEMENTED | `Receipt` (`paymentStatus()`, `shortfall()`, `overpayment()`), `ReceivableBasis::periodOf()` | 入金判定 不足 / 過入金 / 済 added. |
| 3.1 | 残業単価 on daily reports | FIXED | `DailyReport::booted()`, `Production/Application/DailyReports/OvertimeRate.php` | Copied by user id on create and on a change of 作業者 (snapshot). New: a 残業 report for a worker without 残業単価 is refused with a message on the form and the quick entry. Rates are not effective-dated (❓ Q6). |
| 4 | 日報分析 ranking | FIXED | `Analytics/Application/DailyReportRanking.php` | Live query per business year (past years follow edits), cut per month; ties now by the lower user id (were by name). No stored rank. |
| 5 | 売上分析 | DONE | `StaffSalesAnalysis`, `SalesForecast`, `SalesAnalysis::canEditForecast()` | Actuals from sales; only 予想 / 仕掛 stored (user id, business year, month); edit checked in the Livewire method; staff by 担当者コード. |
| 6.1 | 買掛 monthly rows | FIXED | `PayableRoll`, `PayableLedger` | Credits carried (docs/receivables.md §7). 13.原運送代 / 23.運送代 are included in their category columns (❓ Q7). |
| 6.2 | 前月未作成 / 一括更新 | FIXED | `ListPayables`, `PayablePreview`, `purchasing:payables-roll` | Every balance ≠ 0 rolls forward; whole history rebuilt in order; no opening of 0 for a missing month. |
| 7.1 | Billing conditions on 23-2 rows | DONE | `ReceivablesTable::MASTER` | Read from the customer master by id, never copied. |
| 7.2 | 23-2 recalculation | DONE | `ReceivableLedger` | One ledger, two bases (docs/receivables.md). |
| 8 | Data export / backup | IMPLEMENTED | `Core/Application/Export/ListCsvExport.php`, `AppListRecords::exportCsvAction()` | 「ファイルに書き出す」 (⚙) on every app list for users with ファイル書き出し: the filtered list's stored columns the user may see, as CSV. The real backup is the database's (RDS daily, point-in-time, `docs/deploy-aws.md`). |

Tests: `Sales/Tests/Feature/LegacyCustomizationsTest.php` (1.2/1.3, 2.1, 2.2/2.3, 2.4, R5, 8),
`Sales/Tests/Feature/ReceivableLedgerTest.php` + `Unit/ReceivableRulesTest.php` (R14, 2.3 carry-over, 2.5, 7),
`Purchasing/Tests/Feature/PurchasingFlowTest.php` (6), `Analytics/Tests/Feature/AnalysisPageTest.php` (4, 5),
`Core/Tests/Feature/DocumentNumberGeneratorTest.php` (R11), `Production/Tests/Feature/ProductionScreensTest.php` (3.1 copy).

## Open questions

1. **Profit on tax-excluded sales?** 利益 (04) and now 粗利 (06 summary) subtract cost from sales without tax; the legacy
   took 売上金額(税込), so the tax counted as profit.
2. **請求日付 for 末日:** now the last day of the 月度 being invoiced (October sales → 10/31, whenever the invoice is made);
   the legacy took "last day of the previous month" from today, which is the same date when invoices are made early in the
   following month.
3. **Unit price on invoice rows:** filled (the legacy left it blank on purpose). Leave blank?
4. **Re-issue with `-R1`, `-R2`** (cancel a note / invoice, restore, issue a revision): coded in the legacy script but never
   wired; not built. Needed?
5. **Void instead of delete** for 納品書 / 請求書: deletes are hard deletes with 変更履歴 kept; a void status would keep
   the numbers visible.
6. **残業単価 by date:** the report keeps the rate of the worker when it was entered (snapshot); rates have no
   effective dates, so a report entered late takes the current rate.
7. **Freight categories in 買掛:** 13.原運送代 / 23.運送代 are counted (the legacy left them out on purpose).
8. **FAX import settings** (issuer, stamps → 製袋予定規格, 金山 rule, 分類) are configuration, not an admin screen.
