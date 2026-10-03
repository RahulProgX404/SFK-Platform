# Architecture

## 1. Style: modular monolith

One deployable Laravel application, divided into **modules** with explicit boundaries. Compared with microservices it is cheaper to build, run and debug for a company of SFK's size. Compared with a plain Laravel app it keeps features isolated, so modules can be switched on/off or extracted later.

```
┌──────────────────────────── one Laravel app, one PostgreSQL database ────────────────────────────┐
│                                                                                                    │
│   /erp  (Filament panel "erp")                 /sns (panel "sns")         /seo (panel "seo") …     │
│   ┌──────────────────────────────────────┐     ┌──────────────────┐       ┌──────────────────┐     │
│   │ business modules plug in here:        │     │ extension module │       │ extension module │     │
│   │ MasterData · Orders · Production ·    │     │ Sns              │       │ SeoAnalyzer      │     │
│   │ Purchasing · Sales · Accounting · HR  │     │ + /api/sns       │       │ + /api/seo       │     │
│   └──────────────────┬───────────────────┘     └────────┬─────────┘       └────────┬─────────┘     │
│                      │                                   │                          │               │
│   ┌──────────────────▼───────────────────────────────────▼──────────────────────────▼─────────┐     │
│   │ Erp (kernel): panel shell, navigation      Core (kernel): money, tax, 締日/月度,            │     │
│   │                                            numbering, roles & panel permissions             │     │
│   └──────────────────────────────────────────────────────────────────────────────────────────┘     │
│   app/Modules: module discovery, base service provider, base panel provider, generators            │
└────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

## 2. Module types

| Type | Purpose | UI | May depend on | Can disable |
|---|---|---|---|---|
| **kernel** | Foundation (Core shared kernel, Erp panel shell) | Erp owns `/erp` | other kernel modules | no |
| **business** | ERP features | Filament plugin added to the host panel (`"panel": "erp"`) | kernel + other business modules (declared in `depends_on`) | yes |
| **extension** | Secondary products used outside the ERP | Own panel (`/<slug>` or own domain) + own API (`/api/<slug>`) | kernel only | yes |

A module is a folder `modules/<Name>/` with a `module.json` manifest. The `ModuleManager` discovers manifests, applies switches (`MODULE_<SLUG>_ENABLED`), checks dependencies (a module whose dependency is off is disabled too, with a reason) and registers providers in **dependency order**.

## 3. Module anatomy (conventions loaded by `App\Modules\ModuleServiceProvider`)

```
modules/Sales/
  module.json                 manifest (name, slug, type, provider, filament_plugin, panel, depends_on, order)
  Providers/SalesServiceProvider.php
  Config/config.php           → config('sales.*')
  Domain/                     pure business rules: value objects, enums, calculators — no framework, no DB
  Application/                use cases / actions (one class per operation, transactional)
  Models/                     Eloquent models
  Database/Migrations, Seeders, Factories
  Filament/SalesPlugin.php    + Resources/ Pages/ Widgets/ (auto-discovered)
  Http/Controllers/           API / public endpoints
  routes/web.php, routes/api.php   (api → /api/<slug>, names <slug>.api.*)
  Resources/views, Resources/lang  → "<slug>::" namespaces
  Tests/Unit, Tests/Feature   Pest, run by `composer test`
```

**Layering rule inside a module:** `Filament/Http → Application → Domain`, and `Application → Models`. Domain classes never touch the framework, the database or the UI, which keeps the business rules from SYSTEM_BRAIN unit-testable (see `modules/Core/Domain`).

## 4. Dependency rules between modules

1. Everything may use **Core** (`Modules\Core\…`).
2. A business module may use another business module **only if declared** in `depends_on`, and only through its `Application` services or public models. Never reach into another module's Filament or Http layer.
3. Extension modules depend on kernel only. If an extension needs ERP data, it calls an Application service published by the ERP module (or an internal API), so it keeps working when ERP modules change.
4. No circular dependencies; the manager refuses to boot on a cycle.

## 5. Panels, authentication and access

- One `users` table and one `web` guard for all panels. Each panel has its own login page (`/erp/login`, `/sns/login`).
- `User::canAccessPanel()` allows **super-admin** or the permission `panel.<panel-id>.access`. The Core seeder creates one permission per registered panel, so re-run it after enabling an extension.
- Panels can be served on separate (sub)domains through env (`ERP_PANEL_DOMAIN`, `<SLUG>_PANEL_DOMAIN`). This is how an extension is "outside" the ERP while sharing the codebase.
- Business roles (accounting admin, sales, production, approver) will replace the hard-coded single user of the kintone system (SYSTEM_BRAIN 11_PERMISSIONS).

## 6. Data principles (lessons from the kintone audit)

| kintone problem (SYSTEM_BRAIN 17) | Rule in this codebase |
|---|---|
| Totals stored and updated by scripts drift (D-05-1, C-01) | Derive aggregates from source rows (SQL views / queries). Persist snapshots only for closed periods, with a recompute command. |
| Text join keys (`得意先ID_2026年06月`) | Real foreign keys and unique constraints, e.g. `(customer_id, period)`. |
| `max+1` numbering, duplicates (D-GEN-1) | `Core` `DocumentNumberGenerator` (row-locked sequence table). |
| Logic bound to one login | Roles and permissions; no user names in code. |
| Float/rounding inconsistencies | Integer yen + `Core\Domain\Money\Yen` (bcmath) + per-partner `RoundingMode`. |
| Dev code in production (C-04) | Environments via `.env`; CI gate (Pint, PHPStan, Pest on SQLite & PostgreSQL). |

## 7. Cross-cutting services

| Concern | Implementation |
|---|---|
| Audit trail | spatie/laravel-activitylog (add `LogsActivity` to business models) |
| Permissions | spatie/laravel-permission |
| Background work | Laravel queues (Redis) for bulk invoicing, month opening, imports; scheduler for periodic jobs |
| PDF documents | Blade HTML templates rendered by Gotenberg (planned Core/Documents service) |
| Locale/time | `ja`, `Asia/Tokyo` by default |

## 8. Decisions

Recorded as ADRs in [docs/adr](adr/).
