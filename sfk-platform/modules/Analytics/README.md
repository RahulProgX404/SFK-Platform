# Analytics (business module)

Analytics: 22 過去の売上データ (past sales per salesperson and fiscal year, with charts), 25 販売先別売上計画 (sales plan vs actual per customer)

- Slug: `analytics` · Host panel: `erp` · Toggle: `MODULE_ANALYTICS_ENABLED`
- Business rules: link the SYSTEM_BRAIN rule IDs (BR-xx) this module implements.

| Folder | Put here |
|---|---|
| `Domain/` | Pure business rules: value objects, enums, calculators (no framework, no DB) |
| `Application/` | Use cases / actions orchestrating domain + persistence (one class per action) |
| `Models/` | Eloquent models |
| `Database/Migrations`, `Database/Seeders` | Schema and reference data |
| `Filament/Resources`, `Pages`, `Widgets` | UI — auto-discovered into the `erp` panel. App screens extend `Modules\Core\Filament\AppResource` (see docs/permissions.md) |
| `Tests/Unit`, `Tests/Feature` | Pest tests |
