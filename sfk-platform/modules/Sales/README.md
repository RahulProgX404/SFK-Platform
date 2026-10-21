# Sales (business module)

Sales & receivables: 06 売上入力, 10 納品書管理, 11 請求書管理, 21 売入金管理, 23 売掛管理, 23-2 売掛管理(締日別)

- Slug: `sales` · Host panel: `erp` · Toggle: `MODULE_SALES_ENABLED`
- Business rules: link the SYSTEM_BRAIN rule IDs (BR-xx) this module implements.

| Folder | Put here |
|---|---|
| `Domain/` | Pure business rules: value objects, enums, calculators (no framework, no DB) |
| `Application/` | Use cases / actions orchestrating domain + persistence (one class per action) |
| `Models/` | Eloquent models |
| `Database/Migrations`, `Database/Seeders` | Schema and reference data |
| `Filament/Resources`, `Pages`, `Widgets` | UI — auto-discovered into the `erp` panel. App screens extend `Modules\Core\Filament\AppResource` (see docs/permissions.md) |
| `Tests/Unit`, `Tests/Feature` | Pest tests |
