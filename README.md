# SFK Platform

A new business platform for **SFK** (envelope and print manufacturer, `s-f-k.co.jp`) that will replace the company's current **kintone** system: 28 kintone apps plus gusuku Customine automations, custom JavaScript and PrintCreator documents.

It is built as a **modular monolith** on Laravel 13 + Filament 5 + PostgreSQL 17:

- **ERP core**: master data, orders/jobs (注番), production, purchasing, sales → delivery notes → invoices, receivables and payables. Rebuilt step by step from a reverse-engineered knowledge base of the old system.
- **Extension products** (e.g. SNS management, SEO analyzer): live in the same codebase and share user accounts, but each runs in its **own panel and URL/domain with its own API**, so it can be used outside the ERP.

> **Status:** foundation complete (module system, shared business kernel, ERP panel shell, Docker, CI, docs, 39 tests).
> No business screens yet. Development of the ERP modules starts with Phase 1 of the [roadmap](#roadmap).

---

## Contents

1. [Repository layout](#repository-layout)
2. [Quick start](#quick-start)
3. [Everyday commands](#everyday-commands)
4. [How the code is organised](#how-the-code-is-organised)
5. [Adding features](#adding-features)
6. [Configuration](#configuration)
7. [Quality gate](#quality-gate)
8. [Roadmap](#roadmap)
9. [Known gaps and next steps](#known-gaps-and-next-steps)
10. [Documentation index](#documentation-index)

---

## Repository layout

```
sfk-brain/                     ← this repository
├── README.md                  you are here
├── ARCHITECTURE.md            complete technical description
├── sfk-platform/              the Laravel application (tracked)
│
├── SYSTEM_BRAIN/              reverse-engineered knowledge base of the kintone system   (local, git-ignored)
├── kintone-templates/         27 kintone app template ZIPs + an older Gusuku export        (local, git-ignored)
└── gusuku/                    gusuku Customine customization exports (Excel)              (local, git-ignored)
```

Only `sfk-platform/` and these two documents are version-controlled. The three other folders are **source material and analysis** kept locally (see `.gitignore`). They contain internal company data and the old system's definitions. `SYSTEM_BRAIN/` is the **requirements source** for every business module: processes (BP-xx), business rules (BR-xx), known defects (D-/C-xx), and a beginner course (`SYSTEM_BRAIN/18_BEGINNER_GUIDE.pdf`).

---

## Quick start

All commands run inside `sfk-platform/`.

### Option A: Docker (recommended; same services as production)

Requires Docker Desktop.

```bash
cd sfk-platform
cp .env.example .env            # set ADMIN_EMAIL and ADMIN_PASSWORD
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

| URL                   | What                                                                         |
| --------------------- | ---------------------------------------------------------------------------- |
| http://localhost:8080 | redirects to the ERP panel `/erp` (log in with ADMIN_EMAIL / ADMIN_PASSWORD) |
| http://localhost:8025 | Mailpit (catches all outgoing mail)                                          |
| localhost:5433        | PostgreSQL (credentials from `.env`)                                         |

### Option B: Without Docker (Laravel Herd / local PHP 8.4)

PostgreSQL needs the PHP extension `pdo_pgsql`. Without it, use SQLite for a quick look:

```bash
cd sfk-platform
cp .env.example .env
# in .env: DB_CONNECTION=sqlite (delete the other DB_* lines)
#          CACHE_STORE=database  SESSION_DRIVER=database  QUEUE_CONNECTION=database
#          ADMIN_EMAIL=...  ADMIN_PASSWORD=...
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve                # http://localhost:8000/erp
```

---

## Everyday commands

| Command                                        | Purpose                                                                         |
| ---------------------------------------------- | ------------------------------------------------------------------------------- |
| `php artisan module:list`                      | All modules: type, version, enabled/disabled (with reason), panel, dependencies |
| `php artisan make:module Sales`                | Create a **business** module (screens appear in the ERP panel)                  |
| `php artisan make:module Sns --type=extension` | Create an **extension** product (own panel `/sns`, API `/api/sns`)              |
| `php artisan migrate --seed`                   | Run all module migrations and module seeders                                    |
| `composer test`                                | Pest test suite (app + all modules)                                             |
| `composer analyse`                             | Static analysis (PHPStan/Larastan, level 6)                                     |
| `composer lint`                                | Code formatter (Pint)                                                           |

---

## How the code is organised

```
sfk-platform/
├── app/Modules/          module system: discovery, base providers, panel base, generators
├── modules/
│   ├── Core/             kernel    shared business rules: yen rounding, tax, 締日/月度, numbering, roles
│   ├── Erp/              kernel    ERP shell: the /erp panel, menu groups, dashboard
│   ├── <Business>/       business  ERP features (MasterData, Orders, Sales, …), to be built
│   └── <Extension>/      extension secondary products (Sns, SeoAnalyzer, …)
├── stubs/module/         templates used by make:module
├── docker/               PHP-FPM image, nginx
├── docs/                 architecture notes, module guide, ADRs, roadmap
└── tests/                platform-level tests (module tests live inside each module)
```

| Module type   | What it is                                | Where it shows up                                           | Can be switched off |
| ------------- | ----------------------------------------- | ----------------------------------------------------------- | ------------------- |
| **kernel**    | Foundation used by everything (Core, Erp) | Erp owns `/erp`                                             | no                  |
| **business**  | ERP features                              | plugs into the `/erp` panel automatically                   | yes                 |
| **extension** | Separate product used outside the ERP     | own panel `/<slug>` (or own domain) + own API `/api/<slug>` | yes                 |

One user base for everything; a user's **roles/permissions** decide which panels they can open (`panel.<id>.access`, or `super-admin`).

Full details: **[ARCHITECTURE.md](ARCHITECTURE.md)**.

---

## Adding features

**A new ERP feature**

```bash
php artisan make:module MasterData --description="Partners, employees, machines, consumables"
```

Then put business rules in `Domain/`, use cases in `Application/`, tables in `Database/Migrations/`, models in `Models/` and screens in `Filament/Resources/`. They appear in the ERP sidebar automatically.

**A new separate product (e.g. SNS handling, SEO analyzer)**

```bash
php artisan make:module Sns --type=extension --description="SNS account management"
php artisan migrate
php artisan db:seed --class="Modules\Core\Database\Seeders\CoreDatabaseSeeder"   # creates panel.sns.access
```

You get a panel at `/sns` (or a subdomain via `SNS_PANEL_DOMAIN`), an API at `/api/sns/...`, config, migrations and tests. Give users the `panel.sns.access` permission.

**Turn a module off** without code changes: `MODULE_SNS_ENABLED=false` in `.env`.

See [sfk-platform/docs/modules.md](sfk-platform/docs/modules.md) for the full guide and conventions checklist.

---

## Configuration

Main settings (all in `sfk-platform/.env`; see `.env.example`):

| Variable                                                | Default                          | Meaning                                   |
| ------------------------------------------------------- | -------------------------------- | ----------------------------------------- |
| `APP_LOCALE` / `APP_TIMEZONE`                           | `ja` / `Asia/Tokyo`              | Language and time zone                    |
| `DB_CONNECTION` …                                       | `pgsql` (Docker service `pgsql`) | Database                                  |
| `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`     | `redis`                          | Use `database` without Docker             |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`           | —                                | Initial super-admin created by `db:seed`  |
| `ERP_PANEL_PATH`, `ERP_PANEL_DOMAIN`, `ERP_PANEL_BRAND` | `erp`, —, `SFK システム`     | ERP panel URL, optional own domain, title |
| `<SLUG>_PANEL_PATH`, `<SLUG>_PANEL_DOMAIN`              | slug, —                          | Same for each extension product           |
| `MODULE_<SLUG>_ENABLED`                                 | manifest default                 | Switch business/extension modules on/off  |
| `GOTENBERG_URL`                                         | `http://gotenberg:3000`          | PDF rendering service                     |

---

## Quality gate

A change is done when all three pass:

```bash
composer lint      # Pint (Laravel style)
composer analyse   # PHPStan level 6 — 0 errors
composer test      # Pest — currently 39 tests, all green
```

A GitHub Actions workflow (`sfk-platform/.github/workflows/ci.yml`) runs the same checks and the tests on **both SQLite and PostgreSQL**. See [Known gaps](#known-gaps-and-next-steps) for the workflow location.

---

## Roadmap

| Phase | Scope                                                                                                                         | Status  |
| ----- | ----------------------------------------------------------------------------------------------------------------------------- | ------- |
| 0     | Foundation: module system, Core kernel, ERP shell, Docker, CI, docs                                                           | ✅ done |
| —     | Decide rules marked "F" (fix) in `SYSTEM_BRAIN/12_BUSINESS_RULES`; collect permissions, plugin settings, PrintCreator layouts | next    |
| 1     | Master data, orders (注番), sales → delivery notes → invoices, receipts & receivables                                         | planned |
| 2     | Purchasing (CSV import) & payables                                                                                            | planned |
| 3     | Production: schedule, daily reports, stock                                                                                    | planned |
| 4     | HR & safety, analytics, extension products                                                                                    | planned |
| 5     | Data migration from kintone, reconciliation, cut-over                                                                         | planned |

Details: [sfk-platform/docs/roadmap.md](sfk-platform/docs/roadmap.md).

---

## Known gaps and next steps

| Item                    | Detail                                                                                                                                                                                                                             |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| CI workflow location    | GitHub only runs workflows from `.github/workflows/` at the **repository root**. The workflow currently lives in `sfk-platform/.github/`, so it will not run until it is moved to the root with `working-directory: sfk-platform`. |
| Docker not yet run      | The Docker setup is written but was not started on the development PC (Docker Desktop was not running).                                                                                                                            |
| PostgreSQL locally      | The local Herd PHP lacks `pdo_pgsql` and `intl`; use Docker, or install those extensions. Tests run on SQLite locally.                                                                                                             |
| No admin user yet       | Set `ADMIN_EMAIL` / `ADMIN_PASSWORD`, then `php artisan db:seed`.                                                                                                                                                                  |
| Missing source material | Permissions, plugin settings, PrintCreator layouts and the シライシ apps are not exported yet (see `SYSTEM_BRAIN/AUDIT_REPORT.md` §8).                                                                                             |

---

## Documentation index

| Document                                                     | Content                                                                         |
| ------------------------------------------------------------ | ------------------------------------------------------------------------------- |
| [ARCHITECTURE.md](ARCHITECTURE.md)                           | Complete technical architecture (this repository's main reference)              |
| [sfk-platform/docs/modules.md](sfk-platform/docs/modules.md) | How to create and work with modules                                             |
| [sfk-platform/docs/roadmap.md](sfk-platform/docs/roadmap.md) | Phases mapped to the old system's apps and rules                                |
| [sfk-platform/docs/adr/](sfk-platform/docs/adr/)             | Architecture decision records                                                   |
| [sfk-platform/AGENTS.md](sfk-platform/AGENTS.md)             | Coding rules for developers and AI assistants                                   |
| `SYSTEM_BRAIN/` (local)                                      | Old-system knowledge base; start with `00_README.md` or `18_BEGINNER_GUIDE.pdf` |
