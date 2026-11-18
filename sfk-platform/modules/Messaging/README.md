# Messaging (business module)

社内メッセージ: direct and group conversations, attachments, reactions, mentions, announcements (LINE / Messenger style)

- Slug: `messaging` · Host panel: `erp` · Toggle: `MODULE_MESSAGING_ENABLED`
- Not in the legacy system (no SYSTEM_BRAIN rule): a new feature. Design, policy and security: `docs/messaging.md`.

| Folder | Put here |
|---|---|
| `Domain/` | Pure business rules: value objects, enums, calculators (no framework, no DB) |
| `Application/` | Use cases / actions orchestrating domain + persistence (one class per action) |
| `Models/` | Eloquent models |
| `Database/Migrations`, `Database/Seeders` | Schema and reference data |
| `Filament/Resources`, `Pages`, `Widgets` | UI — auto-discovered into the `erp` panel. App screens extend `Modules\Core\Filament\AppResource` (see docs/permissions.md) |
| `Tests/Unit`, `Tests/Feature` | Pest tests |
