# Project guidelines (for developers and AI assistants)

SFK Platform: replacement of a kintone ERP as a Laravel 13 / Filament 5 / PostgreSQL **modular monolith**.
Requirements come from the reverse-engineered knowledge base `../SYSTEM_BRAIN` (rules BR-xx, defects D-/C-xx).

## Architecture (read docs/architecture.md)
- Features live in `modules/<Name>/` with `module.json`. Types: `kernel` (Core, Erp), `business` (ERP features plugged into the `erp` panel), `extension` (own panel + API, e.g. SNS/SEO).
- Create modules with `php artisan make:module <Name> [--type=extension]`, never by hand-copying.
- Layering inside a module: Filament/Http → Application → Domain; Application → Models. Domain is framework-free.
- Cross-module use only via declared `depends_on`, through Application services/models. Extensions depend on kernel only.

## Rules
- Money = integer yen; use `Modules\Core\Domain\Money\Yen` and the partner's `RoundingMode` (blank ⇒ 切捨).
- Tax via `Modules\Core\Domain\Tax\TaxRate`; billing cycle via `Modules\Core\Domain\Billing\BillingCycle`.
- Document numbers via `Modules\Core\Application\Numbering\DocumentNumberGenerator` inside the save transaction.
- Derive aggregates (job cost, AR/AP, stock) from source rows. Do not keep incrementing totals.
- Real foreign keys + unique constraints; no string join keys.
- No user names/emails in code; use roles/permissions (`panel.<id>.access`, `super-admin`).
- Cite the SYSTEM_BRAIN rule id in tests/docblocks when implementing a rule.

## Commands
- `composer test` (Pest, app + modules), `composer analyse` (Larastan L6), `composer lint` (Pint)
- `php artisan module:list`
- Docker: `docker compose up -d --build`; app at http://localhost:8080/erp

## Done = Pint clean + PHPStan clean + tests green (CI runs SQLite and PostgreSQL).
