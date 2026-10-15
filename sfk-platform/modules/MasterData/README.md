# Master Data (business module)

Masters: 01 取引先マスタ (partners); later employees, machines, consumables.

- Slug: `master-data` · Host panel: `erp` (白石封筒) · Toggle: `MODULE_MASTER_DATA_ENABLED`
- Business rules: BR-MD-01 (unique 取引先名 / 取引先コード), BR-MD-02 (roles 得意先 / 仕入先 / その他), BR-MD-06
  (invoice / delivery-note routing to 親会社 or 得意先).

## Apps

| App (registry code) | Screen | SYSTEM_BRAIN |
|---|---|---|
| 01. 取引先マスタ（得意先・仕入先） (`partner-master`) | `/erp/partners` — `PartnerResource` | APP_01 (legacy app 6) |

01. 取引先マスタ rebuilds the legacy app: the form and record page follow the template layout row by row with the
legacy field widths (`PartnerLayout` + `Core\Filament\Records\RecordLayout`) and the tabs 基本情報 / 仕入先情報 /
見積履歴 / 納品履歴 / 請求履歴 above the fields; the pages use the shared app screen design (`Core\Filament\AppPages`); the
list offers the legacy views 取引先一覧 and （すべて）, search, and 「ファイルに書き出す」 (CSV, ファイル書き出し permission).
取引先コード is numbered on save (6 digits, sequence `partner`, codes taken from the legacy data are skipped);
取引先名(ふりがな) is new and required on the form. All option lists are the legacy ones exactly (`Domain/Partners`,
shared billing/tax enums in Core). Not rebuilt yet: the records in 見積履歴 / 納品履歴 / 請求履歴 and the 受注入力 action
(they need apps 09 / 10 / 11 / 04), file import,
comments and change history. Dropped on purpose: address split fields, stored 前回支払残高 / 前回買掛残高, フラグ.

| Folder | Put here |
|---|---|
| `Domain/` | Pure business rules: value objects, enums, calculators (no framework, no DB) |
| `Application/` | Use cases / actions orchestrating domain + persistence (one class per action) |
| `Models/` | Eloquent models |
| `Database/Migrations`, `Database/Seeders` | Schema and reference data |
| `Filament/Resources`, `Pages`, `Widgets` | UI — auto-discovered into the `erp` panel. App screens extend `Modules\Core\Filament\AppResource` (see docs/permissions.md) |
| `Tests/Unit`, `Tests/Feature` | Pest tests |
