# 売掛管理 (accounts receivable)

23. 売掛管理 (by calendar month) and 23-2. 売掛管理(締日別) (by 月度, the billing-cycle month) in the Sales module, built
so that the defects of the legacy AR apps cannot recur. SYSTEM_BRAIN: APP_23, APP_23-2, BP-07, BR-AR-01..08, D-06, D-23-2.

## 1. Data model

The **source of truth** is the transactions; the monthly rows are a **cache** derived from them.

| Table | What | Key / constraints |
|---|---|---|
| `sales` (06) | 売上: 売上日, amount, tax bucket (`tax_rate` 10% / 8% / 非課税 / 対象外), 月度 `billing_month` | FK `customer_id` → partners |
| `invoices` (11) | 請求書: 請求日, totals | unique `number`, FK `customer_id` |
| `receipts` + `receipt_lines` (21) | 入金 of an invoice; rows: 入金日, 入金額, 手数料, 歩引き, 入金方法, 銀行 | FK `invoice_id` (nullable: a payment may come first), FK `customer_id` |
| `receivable_months` | cache: 前月売掛残, 当月入金額, 売上額 per bucket, 消費税額, 消費税端数調整, 当月合計, 当月売掛残 | **unique (basis, customer_id, year_month)**; `year_month` ISO `YYYY-MM` + `month_start` DATE (1st), kept equal by a PostgreSQL CHECK |

- The key is structured: `customer_id` (real FK) + the period. `year_month` is the ISO machine format, not a display
  string (「2026年09月」 is formatted by `YearMonth::label()` on screens only). Deviation from "DATE or YYYYMM": the whole
  system (sales `billing_month`, invoices) keys months by `YYYY-MM`; it sorts and compares like a date, and `month_start`
  carries the DATE type, tied to it by the CHECK `receivable_months_period_check`.
- Only `tax_adjustment` (消費税端数調整) is entered by hand. Every other figure is recalculated; nothing is incremented.
- Invoice payment status stays on the 21 record: paid = Σ (入金 + 手数料 + 歩引き) of its rows (BR-AR-04);
  `Receipt::paymentStatus()` = 不足 / 過入金 / 済, `shortfall()` / `overpayment()` the amounts.
- 次月分判定 is **computed** (`ReceivableMonth::carriesForward()`: 「要作成」 while 当月売掛残 ≠ 0), never stored.

## 2. Rules (one place each)

| Rule | Where |
|---|---|
| Period a transaction belongs to | `ReceivableBasis::periodOf($date, $closingDay)` — Calendar: the calendar month; Cycle: `BillingCycle::monthFor()` (day ≤ 締日 5/10/15/20/25 → that month, later → next; 末日 / 随時請求 / blank → calendar month). `ReceivableBasis::rule()` names the rule on the list heading and in the command output. |
| 月度 of a sale | fixed when the sale is saved, by its effective 締日 (請求締日変更 else the customer's, BR-AR-02) — an issued invoice keeps its cycle when the master changes |
| 月度 of a payment | by the invoice's 今回締日; without an invoice (paid first) by the customer's 締日 (BR-AR-06) |
| Opening balance | `ReceivableRoll::opening()` — 当月売掛残 of the month right before; 0 when there is no earlier month or the latest earlier month closed at exactly 0; any other hole throws `ReceivableGap` (never 0). Over stored rows: `ReceivableLedger::openingBalance()`, used by 請求書 (InvoiceIssuer) and 請求書更新 (InvoiceBalances). |
| Roll-forward | `ReceivableRoll::months()`: 差引残高 = 前月売掛残 − 当月入金額; 消費税額 per rate by the customer's 端数区分; 当月合計 = 売上額 + 消費税額 + 調整; 当月売掛残 = 差引残高 + 当月合計 (BR-AR-03). A month without activity gets a row while the balance is not 0 — **a credit carries like a debt**; at exactly 0 the rows may stop and later activity starts from 0. |

Calendar AR and cycle AR are two views (`basis`) computed from the same sales and receipts by the same code, never
copies maintained apart.

## 3. Recompute and month close

- `ReceivableLedger::refresh($customer)` rebuilds both bases of one customer from its first month up to the current
  month: an idempotent upsert (create missing, update differing, remove months without balance or activity). Triggered
  on every save / delete of a sale, receipt, receipt row or 消費税端数調整 (SalesObserver) and on a change of the
  customer's 締日 / 端数区分 — so a back-dated change moves every later month.
- `php artisan sales:receivables-roll [--customer=ID…]` — the month close: every customer with sales, receipts or
  months, all missing periods filled. Scheduled daily at 00:30; a second run reports `0 created, 0 updated, 0 removed`.
  Run it by hand after any rule change to re-apply it to all existing data.
- Rebuilding a customer from scratch reproduces the stored rows exactly (tested by deleting a month).
- Period locking (締め処理) is not built yet: closed months would need a lock table and adjustment entries in an open
  month; the ledger then stops rewriting locked months.

## 4. Lists

- 23: 先月売掛一覧, 当月売掛一覧, 先々月売掛一覧, 売掛一覧; 23-2: 売掛一覧, the same three month lists, one per 締日.
- The month lists are relative to the page's **基準月** (`?base=YYYY-MM`, default the current month; ‹ › and 当月 above the
  table), not to today. Their filter is 対象月 (`base_offset`) = 当月 / 前月 / 前々月 of the 基準月 (stored as `m0` /
  `m1` / `m2`), so a saved view never holds a month. Users can build their own with 対象月 in 絞り込む / アプリの設定 → 一覧.
- The heading names the month shown: 「先月売掛一覧（2026年10月）」 (its tooltip states the period rule).
- Migration `2026_11_19_000001` converts stored views with 年月日 = 今月 / 先月 to 対象月 and adds the missing month lists.

## 5. Migration of the legacy data

`php artisan legacy:import 23` (step 190 / 195, after 06 and 21), `LegacyReceivables`:

1. The legacy months are **not copied**. Only 消費税端数調整 is taken over.
2. Before anything is derived, each customer's legacy months are checked (`LegacyLedgerCheck`) and every finding is
   written to `storage/logs/legacy-import/38.log` (23) / `45.log` (23-2):
   - a hole between months although the earlier one did not close at 0 (the legacy month creation looked back one month,
     carried > 0 only);
   - 前月売掛残 ≠ 当月売掛残 of the month before (later months not updated after a correction);
   - a balance ≠ 0 in a customer's last month before the end of the export — 「マイナス残高（過入金）… が繰り越されていません」 for
     the credits that vanished.
3. Every customer's months are rebuilt from the imported sales and receipts (`ReceivableLedger::refresh`), up to today.
4. Each legacy 当月売掛残 is compared with the rebuilt one; differences are logged (reconciliation).
5. After go-live, run `sales:receivables-roll` once; review the log with the accounting staff before switching off the
   legacy apps.

## 6. Legacy defects → design → tests

| # | Legacy defect | Here | Test (`modules/Sales/Tests/…`) |
|---|---|---|---|
| 1 | next month only if 当月売掛残 > 0 | carry ≠ 0; 次月分判定 ≠ 0 | ReceivableLedgerTest: customer 629 table; Unit: credit carries |
| 2 | stored flags recalculated on save only | flag computed; cache rebuilt on every input change, master changes and the daily run | 端数区分 change re-applied |
| 3 | 「未作成」 only inserted | full rebuild, upsert, back-dated changes move later months | back-dated 1,000 sale |
| 4 | two meanings of "previous month" | one `opening()`; gaps throw `ReceivableGap` | Unit: opening; 請求書更新 over a gap |
| 5 | batches looked back one month | roll-forward fills all periods, idempotent, unique key | deleted month recreated; daily run |
| 6 | key 「629_2026年09月」 | FK + ISO period + DATE, CHECK | PostgreSQL CHECK test |
| 7 | 先月 relative to today, 先々月 hardcoded | 基準月 + offsets, heading with month | 基準月 lists; view migration |
| 8 | two implicit month keys | `ReceivableBasis::periodOf()` | 2026-09-09 → 09, 2026-09-25 → 10 |

## 7. 買掛管理 (payables)

23. 買掛管理 had the same > 0 rule. `Purchasing\Domain\Payables\PayableRoll` now carries any balance ≠ 0 (a credit with a
supplier too) and stops only at exactly 0; `PayableLedger::refresh()` is the same idempotent upsert and
`php artisan purchasing:payables-roll [--supplier=ID…]` (daily 00:35) rolls every supplier forward
(PurchasingFlowTest: overpaid supplier).

## 8. Applied to the shared database (2026-11-19)

Migration `2026_11_19_000001` run, then `sales:receivables-roll` and `purchasing:payables-roll`. Backup of
`receivable_months`, `payable_months` and `app_list_views` before: `storage/app/backup-before-ar-credit-fix-2026-11-19.json`
(git-ignored).
