# Master Data (business module)

Masters: 01 取引先マスタ (partners), 03 機械マスタ (machines); later employees, consumables.

- Slug: `master-data` · Host panel: `erp` (白石封筒) · Toggle: `MODULE_MASTER_DATA_ENABLED`
- Business rules: BR-MD-01 (unique 取引先名 / 取引先コード), BR-MD-02 (roles 得意先 / 仕入先 / その他), BR-MD-06
  (invoice / delivery-note routing to 親会社 or 得意先); APP_03 unique 機械コード / 機械名 and the seven rate types.

## Apps

| App (registry code) | Screen | SYSTEM_BRAIN |
|---|---|---|
| 01. 取引先マスタ（得意先・仕入先） (`partner-master`) | `/erp/partners` — `PartnerResource` | APP_01 (legacy app 6) |
| 03. 機械マスタ (`machine-master`) | `/erp/machines` — `MachineResource` | APP_03 |

03. 機械マスタ rebuilds the legacy app with its template layout (`MachineLayout`): 機械コード (unique, optional) and
機械名 (unique, required — the record title other apps look up), the seven rates 加工単価(枚), 型替単価(回), 内職単価(H),
機械単価(H), 印刷単価, 版替単価, 練インキ単価 as unit prices in yen with up to 4 decimals (shown 「¥0.35」, Core
`UnitPrice`; amounts derived from them are rounded with Core `Yen`), and 備考. The list offers 機械一覧 (newest
first) and （すべて）; the search box replaces the 「機会一覧(検索特化)」 plugin view. Its template switches 一括削除 off
(`appSettingDefaults()`); コメント (to 作成者 / 更新者), 変更履歴, インライン編集 and 再利用 are on. The daily reports,
the bag-making schedule and accident reports (08 / 08N / 12 / 14) will reference machines by id.

01. 取引先マスタ rebuilds the legacy app: the form and record page follow the template layout row by row with the
legacy field widths (`PartnerLayout` + `Core\Filament\Records\RecordLayout`) and the tabs 基本情報 / 仕入先情報 /
見積履歴 / 納品履歴 / 請求履歴 above the fields; the pages use the shared app screen design (`Core\Filament\AppPages`); the
list offers the legacy views 取引先一覧 and （すべて）, search, and 「ファイルに書き出す」 (CSV, ファイル書き出し permission).
取引先コード is numbered on save (6 digits, sequence `partner`, codes taken from the legacy data are skipped);
取引先名(ふりがな) is new and required on the form. All option lists are the legacy ones exactly (`Domain/Partners`,
shared billing/tax enums in Core). The legacy app features of the template (useComment, useHistory,
useInlineEditAndDelete, enableRecordReuse, enableBulkDeletion) are on: コメント with 宛先 (notifying 作成者 / 更新者
like the template's app notifications), 変更履歴, インライン編集 in the list (every field but 取引先コード and the
attachments), レコードを再利用する and 一括削除, each switchable in アプリの設定 → 設定 → その他の設定.
Not rebuilt yet: the records in 見積履歴 / 納品履歴 / 請求履歴 and the 受注入力 action (they need apps 09 / 10 / 11 /
04), file import and attachment thumbnails. The その他関連情報 tab of the legacy tab script pointed to a missing group
(D-01-1). Dropped on purpose: address split fields, stored 前回支払残高 / 前回買掛残高, フラグ.

### Taking over the legacy data

`php artisan master-data:import-partners [seed.json] [--warnings]` (also run by `db:seed` when `seed.json` is at the
project root) imports the record export of legacy app 6 (`Application/Partners/LegacyPartnerImport`). It is safe to
run again: partners are matched by 得意先ID, which also stays their `id`. 取引先名 is kept exactly (some legacy names
differ only by a trailing space); 自社担当者 / 作成者 / 更新者 become users by their legacy login, and missing people
are created without sign-in (【NN】 → 営業担当者コード, 会長 / 社長 / 専務 → 役職). The live app's options that the
template lacks are included (42.雑包装費, (無)指定納品書), and the disabled グループ名 stay on their records but are not
offered. Attachments are not in the export and are reported, like values outside the option lists and 登録番号 that are
not T + 13 digits.

| Folder | Put here |
|---|---|
| `Domain/` | Pure business rules: value objects, enums, calculators (no framework, no DB) |
| `Application/` | Use cases / actions orchestrating domain + persistence (one class per action) |
| `Models/` | Eloquent models |
| `Database/Migrations`, `Database/Seeders` | Schema and reference data |
| `Filament/Resources`, `Pages`, `Widgets` | UI — auto-discovered into the `erp` panel. App screens extend `Modules\Core\Filament\AppResource` (see docs/permissions.md) |
| `Tests/Unit`, `Tests/Feature` | Pest tests |
