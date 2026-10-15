# ADR 0001: Modular monolith with self-describing modules

- Status: accepted · Date: 2026-10-03

## Context
The legacy system has 28 apps covering one company's ERP. Future secondary products (SNS management, SEO analyzer) must be addable without touching the ERP, and usable outside it. The team is small.

## Decision
One Laravel application. Features live in `modules/<Name>/` with a `module.json` manifest (type `kernel` | `business` | `extension`). `App\Modules\ModuleManager` discovers modules, applies env switches, resolves dependencies and registers providers in dependency order. A generator (`make:module`) enforces the folder conventions.

We did not use `nwidart/laravel-modules`: our needs are small (about 650 lines of glue code including comments), we want first-class Filament panel/plugin integration, and we avoid a dependency on its upgrade cycle.

## Consequences
- + One deploy, one database, transactional consistency across ERP modules
- + Extensions isolated by convention and dependency rules; can be disabled per environment
- + Module boundaries make later extraction (separate service) possible if a product grows
- − Boundaries are enforced by review and tests, not by the runtime; keep `depends_on` honest
