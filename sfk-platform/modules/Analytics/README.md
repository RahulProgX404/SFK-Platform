# Analytics (business module)

Analytics: 22 過去の売上データ (past sales per salesperson and fiscal year, with charts), 25 販売先別売上計画 (sales plan vs actual per customer)

- Slug: `analytics` · Host panel: `erp` · Toggle: `MODULE_ANALYTICS_ENABLED`
- Business rules: link the SYSTEM_BRAIN rule IDs (BR-xx) this module implements.
- 分析 (legacy `SalesAnalysis.bundle.js` and `DailyReportAnalysis.bundle.js`, not in SYSTEM_BRAIN): two pages, not apps yet —
  no AppResource, not in the app list or the menu (`Filament/Pages`, frame `Concerns/HasAnalysisChrome`).
  - 売上分析 (`/erp/analysis/sales`, `SalesAnalysis` + `Application/StaffSalesAnalysis`; users with 財務分析を表示 who may
    view 06. 売上入力): 06. 売上入力 summed per first 担当者, business year (May–April) and month; a month opens its 売上先 and
    their lines. 予想 / 仕掛 are typed in for the current year (`sales_forecasts`; administrators, or the 担当者 for their
    own rows — the legacy script allowed one e-mail address). 財務分析の範囲 個人のみ: only the user's own row.
  - 日報分析 (`/erp/analysis/daily-reports`, `DailyReportAnalysis` + `Application/DailyReportRanking`; users with 財務分析の範囲
    全体 who may view 08. 日報入力 — user 2026-11-09; 個人のみ get 売上分析 only): 枚数単価 + 型替え単価 per 作業者 and month (cut to whole yen per month) with the hours
    worked (作業時間 + 残業時間), ranked by 年間合計.
  - Both sum the source rows the user may see (`onlyVisible()`) on every visit; the legacy daily copy into apps 338 / 685
    is not kept, so there is nothing to import.
- The cards of the dashboard (/erp, Core `PortalPage`; bug.md 2026-11-05, `design_ref/Dashboard.png`): `Filament/Widgets`
  (base `Filament/Dashboard/DashboardCard`; a card's view says its width, `sfk-dash-span-<n>` of 12), for the period of the
  header (`Domain/Period`). Rows 1–2 need 財務分析を表示 (`User::seesFinancialAnalysis()`): 売上合計 / 売上概要 / 入出金,
  上位の売上先 / 担当者別売上 / 売れ筋 TOP3 (`Application/SalesDashboard`: 入金済 / 入金待ち / 期限超過 / 未請求 from the
  請求書 and its 入金 — due on 請求日 + 回収予定区分 months, 回収予定日 —, 売上目標 = the monthly plans of 25, 入金 of 21 and
  出金 of 07). Rows 3–4 are for everyone: 作業者ランキング (amount and hours), 休暇・勤怠予定 and クイックアクション
  (`Application/TeamDashboard`: the one-click steps of 16. 申請管理 and 20. 消耗品発注, run through their workflows), and
  最近のアクティビティ (`Application/ActivityFeed`: the user's 通知 and what they did themselves).
- bug.md 2026-11-06: 全体 | 個人 in the header (`scope`; `SalesDashboard::forStaff()` — the first 担当者's sales, their 予想
  as the target, the 入金 of receipts they are 担当者 of and, instead of 出金, 入金待ち / 期限超過; 担当者別売上 becomes 担当した売上);
  個人の概要 (`Widgets/PersonalOverviewCard`, `Application/PersonalOverview`: 作業時間 + 残業時間 and 時給計算 + 残業時給計算
  of 08. 日報入力, 承認済 days off of 16. 申請管理 by 申請分類) as row 1 for users without 財務分析を表示, and on a user's page
  (`Filament/Users/UserOverviewCard` via Core `UserDetailWidgets`, person and administrators only); 作業者ランキング with
  each one's share (the user's own row framed); クイックアクション lists the last opened apps and records when nothing waits
  (Core `AppUsageLog`) and is then called 最近表示した項目. Without 財務分析を表示, row 3 is クイックアクション, 休暇・勤怠予定,
  作業者ランキング (`getSort()` of those cards).

| Folder | Put here |
|---|---|
| `Domain/` | Pure business rules: value objects, enums, calculators (no framework, no DB) |
| `Application/` | Use cases / actions orchestrating domain + persistence (one class per action) |
| `Models/` | Eloquent models |
| `Database/Migrations`, `Database/Seeders` | Schema and reference data |
| `Filament/Resources`, `Pages`, `Widgets` | UI — auto-discovered into the `erp` panel. App screens extend `Modules\Core\Filament\AppResource` (see docs/permissions.md) |
| `Tests/Unit`, `Tests/Feature` | Pest tests |
