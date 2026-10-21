# Production (business module)

Production: 08 日報入力 (daily reports per task, job costing, box usage), 12 製袋予定管理 (machine schedule with Gantt charts), 14 事故報告管理 (incidents)

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
