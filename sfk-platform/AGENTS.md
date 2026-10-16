# Project guidelines (for developers and AI assistants)

SFK Platform: replacement of the legacy ERP as a Laravel 13 / Filament 5 / PostgreSQL **modular monolith**.
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
- No user names/emails in code; use the ユーザー種別 `user_type` (`super_admin` / `admin` / `employee`) for system rights and
  user / 従業員カテゴリ dashboards (`Modules\Core\Access\DashboardAccess`) for panel access. There are no roles and no
  organizations; never gate features on a category or 役職 name.
- Screens of an app (feature) extend `Modules\Core\Filament\AppResource` (app permissions, spaces, status) and its
  pages extend `Modules\Core\Filament\AppPages\App{List,Create,Edit,View}Record(s)` (the shared app screen design) —
  see `docs/permissions.md`.
- App lists are filtered only through the shared 絞り込む dialog (legacy design, `design_ref/filter1-3.png`): return the
  app's fields from `filterFields()` on its `AppListRecords` page (`Modules\Core\Application\Filters\FilterField`:
  text / textarea / number / date / dateTime / choice / user, `relationship()` or `query()` for special storage). Do not
  add Filament table `->filters()`. 適用 filters for the session, 保存 (administrators) stores a list view.
- 一覧 (list views) follow the legacy view settings: an app's list page supplies `listColumns()` (all columns, order of
  （すべて）), `defaultListViews()` (`Modules\Core\Application\Views\ListView::table()/calendar()`, from SYSTEM_BRAIN
  views.md; stored once) and `filterFields()`; its table builds columns from `viewColumns()`; its 「…」 entries go in
  `optionActions()` (表示件数 is added by Core). Register a `views` page (subclass of `AppListViewSettings`, before
  `view`) for アプリの設定 → 一覧: 追加 / 編集 / 複製 / 削除 / drag order (top = default), 表形式 or カレンダー形式.
- Never write the word for the old product anywhere; call it 旧システム in the UI and "legacy system" in English.
- Cite the SYSTEM_BRAIN rule id in tests/docblocks when implementing a rule.

## Commands
- `composer test` (Pest on SQLite, app + modules), `composer test:pgsql` (same on PostgreSQL database `sfk_test`),
  `composer analyse` (Larastan L6), `composer lint` (Pint)
- `php artisan module:list`
- Docker: `docker compose up -d --build`; app at http://localhost:8080/erp

## Done = Pint clean + PHPStan clean + tests green (CI runs SQLite and PostgreSQL).
