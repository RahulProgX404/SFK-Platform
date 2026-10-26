# SFK Platform

Replacement for SFK's legacy system, built as a **modular monolith** on Laravel 13 · Filament 5 · PostgreSQL 17.

- **ERP core**: masters, orders (注番), production, purchasing, sales → delivery notes → invoices, receivables/payables. Implemented step by step from the reverse-engineered knowledge base in `../SYSTEM_BRAIN`.
- **Extension products** (SNS management, SEO analyzer, …): live in the same codebase and share users and the core kernel, but each has its own panel (URL or domain) and its own API, so it can be used outside the ERP.

> Status: **foundation only**. No business screens yet; see [docs/roadmap.md](docs/roadmap.md).

## Stack

| Layer                   | Choice                                                                   |
| ----------------------- | ------------------------------------------------------------------------ |
| Runtime                 | PHP 8.4, Laravel 13                                                      |
| Admin UI                | Filament 5 (Livewire 4) — one panel per product                          |
| Database                | PostgreSQL 17 (SQLite works for a quick local look and the test suite)   |
| Queue / cache / session | Redis                                                                    |
| Permissions / audit     | spatie/laravel-permission 8, spatie/laravel-activitylog 5                |
| PDF                     | 帳票出力: FPDF + FPDI over uploaded PDF forms (docs/documents.md); Gotenberg for HTML → PDF |
| Quality                 | Pest 4, Larastan 3 (level 6), Pint, GitHub Actions (SQLite + PostgreSQL) |

## Quick start

### A. Docker (recommended — matches production)

```bash
cp .env.example .env              # set SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

Open http://localhost:8080 → sign in → the dashboard selection portal (`/portal`) offers the ERP (`/erp`)
and the platform settings (`/settings`: users, 従業員カテゴリ, 役職). Mail: http://localhost:8025.
After changing `SUPER_ADMIN_*` in `.env`, run `php artisan core:super-admin` to apply it.

Speed: the app container does not check the source files on each request (slow through the Windows/macOS file
sharing). Its watcher (`docker/php/dev-watch.sh`, see `docker compose logs app`) reloads PHP a few seconds after an
edit and keeps the route / Filament component caches and the class map current. Should an edit not show up,
`docker compose restart app`.

### B. Without Docker (Laravel Herd / local PHP)

The host PHP needs `pdo_pgsql` for PostgreSQL; otherwise use SQLite for a quick look:

```bash
cp .env.example .env
# set DB_CONNECTION=sqlite and delete the other DB_* lines; set CACHE_STORE/SESSION_DRIVER/QUEUE_CONNECTION=database
php artisan key:generate
php artisan migrate --seed
php artisan serve                 # http://localhost:8000/erp
```

## Everyday commands

| Command                                        | Purpose                                                  |
| ---------------------------------------------- | -------------------------------------------------------- |
| `php artisan module:list`                      | Modules, type, enabled state, dependencies               |
| `php artisan make:module Sales`                | New business module (plugs into the ERP panel)           |
| `php artisan make:module Sns --type=extension` | New extension product (own panel `/sns`, API `/api/sns`) |
| `composer test`                                | Pest test suite (app + all modules)                      |
| `composer analyse`                             | PHPStan / Larastan                                       |
| `composer lint`                                | Pint formatter                                           |

## Repository layout

```
app/Modules/              Module system (discovery, base providers, panel base, generators) — framework glue
modules/
  Core/                   kernel    shared kernel: money & rounding, tax, 締日/月度, numbering, users
  Erp/                    kernel    ERP shell: /erp panel, navigation groups, dashboard
  <Business>/             business  ERP features (to be built: MasterData, Orders, Sales, …)
  <Extension>/            extension secondary products (Sns, SeoAnalyzer, …)
stubs/module/             templates used by make:module
docker/                   PHP-FPM image, nginx config
docs/                     architecture, module guide, ADRs, roadmap
```

Read next: [docs/architecture.md](docs/architecture.md) → [docs/modules.md](docs/modules.md) → [docs/roadmap.md](docs/roadmap.md).
