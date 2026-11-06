# Production (business module)

Production: 08 日報入力 (daily reports per task, job costing, box usage), 12 製袋予定管理 (製袋予定表), 14 事故報告管理 (incidents)

- Slug: `production` · Host panel: `erp` · Toggle: `MODULE_PRODUCTION_ENABLED`
- Business rules: link the SYSTEM_BRAIN rule IDs (BR-xx) this module implements.

| Folder | Put here |
|---|---|
| `Domain/` | Pure business rules: value objects, enums, calculators (no framework, no DB) |
| `Application/` | Use cases / actions orchestrating domain + persistence (one class per action) |
| `Models/` | Eloquent models |
| `Database/Migrations`, `Database/Seeders` | Schema and reference data |
| `Filament/Resources`, `Pages`, `Widgets` | UI — auto-discovered into the `erp` panel. App screens extend `Modules\Core\Filament\AppResource` (see docs/permissions.md) |
| `Tests/Unit`, `Tests/Feature` | Pest tests |

## 12. 製袋予定管理 — 製袋予定表

The app is one screen (`isSingleScreen()`: no 一覧 tabs, 絞り込む, 新規 or create page; ⚙ with アプリの権限 only): the board of
`production-schedule.js` (sfk-hub, user 2026-11-06) on the page itself (no masthead, backdrop or footer), kept as it was in
`Resources/js/production-schedule.js` and served versioned by `Http/Controllers/ProductionScheduleScript`
(`/production/production-schedule.js?v=…`). It draws the lines × days (「1日」「7日」「30日」, `?date=&span=`), adds / edits
in place, drags (copy or move) and highlights; every change goes through `Filament/Resources/BagSchedules/Board/BagScheduleBoard`
(Livewire, `adapters.livewire($wire)`), which checks the app's rights and writes 変更履歴.

- Lines: `Domain/Schedules/ScheduleBoard::LINES` — machines of 03 by 機械コード (K00001 …), in the board's order; a line
  whose machine is missing is not shown.
- 注番 (digits → sfk-0000123): 担当者 / 品名 / 部数 come from **06. 売上入力** — the job's latest sale, a マイナス処理 only
  when there is no other (`Application/Schedules/SalesLookup`); without a 注番, 品名 and 部数 are typed. Rules:
  `Application/Schedules/ProductionSchedule`.
- Holidays: Core `CompanyCalendar` (共通管理 → 会社カレンダー); Sundays are closed by the board.
- Records of the legacy app keep 開始 / 終了日時, 使用状況 and free 注番 / 品名 (開発者用フィールド); their 日付 is the day of
  開始日時. The fonts of the original (Google Fonts) are not loaded (CSP): the board uses the system's Mincho / Gothic.
