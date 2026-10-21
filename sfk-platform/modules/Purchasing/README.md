# Purchasing (business module)

Purchasing & payables: 05 納品(仕入)入力, 07 支払入力, 15 運送請求データ取込, 20 消耗品発注, 23 買掛管理

- Slug: `purchasing` · Host panel: `erp` · Toggle: `MODULE_PURCHASING_ENABLED`
- Business rules: link the SYSTEM_BRAIN rule IDs (BR-xx) this module implements.

| Folder | Put here |
|---|---|
| `Domain/` | Pure business rules: value objects, enums, calculators (no framework, no DB) |
| `Application/` | Use cases / actions orchestrating domain + persistence (one class per action) |
| `Models/` | Eloquent models |
| `Database/Migrations`, `Database/Seeders` | Schema and reference data |
| `Filament/Resources`, `Pages`, `Widgets` | UI — auto-discovered into the `erp` panel. App screens extend `Modules\Core\Filament\AppResource` (see docs/permissions.md) |
| `Tests/Unit`, `Tests/Feature` | Pest tests |
