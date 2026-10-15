# Working with modules

## Create a business module (ERP feature)

```bash
php artisan make:module MasterData --description="Partners, employees, machines, consumables"
php artisan make:module Sales --description="Sales lines, delivery notes, invoices"
```

Then:

1. Edit `modules/Sales/module.json` → add dependencies, e.g. `"depends_on": ["core", "erp", "master-data"]`, and set `order`.
2. Domain rules → `Domain/` (unit-test them, citing the SYSTEM_BRAIN rule id, e.g. `BR-SA-10`).
3. Tables → `Database/Migrations/`, models → `Models/`.
4. Screens → `Filament/Resources/…` (Filament: `php artisan make:filament-resource`, then move the class into the module namespace). Set the sidebar group:
    ```php
    protected static string|\UnitEnum|null $navigationGroup = \Modules\Erp\Filament\NavigationGroup::Sales->value;
    ```
5. `php artisan migrate`, `composer test`.

## Create an extension product (outside the ERP)

```bash
php artisan make:module Sns --type=extension --description="SNS account management"
php artisan make:module SeoAnalyzer --type=extension --slug=seo --description="SEO analyzer"
php artisan migrate
```

You get:

- panel at `/sns` (change with `SNS_PANEL_PATH`, or serve on a subdomain with `SNS_PANEL_DOMAIN=sns.example.co.jp`)
- API at `/api/sns/status` (add endpoints in `routes/api.php`; protect them with auth before exposing data)
- its own config, migrations, models and tests

Grant access: in 共通管理, tick the new dashboard on the 従業員カテゴリ whose employees should use it, or on individual users
(ユーザー種別 super_admin sees every panel). Set `<slug>.panel.admin_only = true` in the module config for a dashboard
that only super_admin / admin may open (like 共通管理); such dashboards are not offered on the category or user forms.

## Switch modules on/off

```dotenv
MODULE_SNS_ENABLED=false
MODULE_SEO_ENABLED=false
```

Run `php artisan module:list` to see the state and the reason a module is disabled (for example, a disabled dependency). Kernel modules cannot be disabled.

## Manifest reference (`module.json`)

| Key                      | Required | Meaning                                                              |
| ------------------------ | -------- | -------------------------------------------------------------------- |
| `name`                   | ✔        | StudlyCase; also the folder and namespace (`Modules\<name>`)         |
| `slug`                   | ✔        | kebab-case; config key, URL segment, permission name, env switch     |
| `type`                   | ✔        | `kernel` · `business` · `extension`                                  |
| `provider`               |          | Service provider class (extends `App\Modules\ModuleServiceProvider`) |
| `filament_plugin`        | business | Plugin class (extends `App\Modules\Filament\ModulePlugin`)           |
| `panel`                  | business | Host panel id (normally `erp`)                                       |
| `depends_on`             |          | Slugs that must be enabled first                                     |
| `enabled`                |          | Default switch (env overrides)                                       |
| `order`                  |          | Sort key for boot/display (dependencies always first)                |
| `version`, `description` |          | Shown in `module:list` and the dashboard                             |

## Conventions checklist

- [ ] No business logic in Filament resources or controllers. Call an `Application` action.
- [ ] Domain classes are framework-free and unit-tested.
- [ ] Money is integer yen; rounding via `Core\Domain\Money\Yen` with the partner's `RoundingMode`.
- [ ] Document numbers via `Core\Application\Numbering\DocumentNumberGenerator`, inside the saving transaction.
- [ ] Aggregates (job cost, AR/AP balances, stock) are derived, not incremented.
- [ ] Every table has foreign keys and the unique constraints the business needs.
- [ ] Business models use `LogsActivity` (audit) where the legacy system kept history.
- [ ] Cross-module access only through declared dependencies.
