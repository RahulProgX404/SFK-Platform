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
- Look: no rounded corners anywhere (the theme forces `border-radius: 0`; only radio buttons stay round) — never add
  a radius. Table headers and field labels (key names on record / edit / new pages) are black (`--kt-label`), never
  grey. No table column is bold (no `->weight()` on table columns; the theme forces normal weight in table cells —
  only the header row is bold). App lists show the paging 「1 - 100（N件中）」 at the right end of the search row (Core render hook). On a record page every toolbar button sits on the right: 編集, 再利用, 「…」 (印刷 / 削除), gear, ‹ ›
  (Core `app-header`, nothing per app).
- The new-record page resembles the legacy create page: legacy field order and widths, legacy labels, drop-downs
  showing 「-----」 when blank, lookups listing their choices when opened. Table fields (サブテーブル) are built with
  `Modules\Core\Filament\Records\RecordTable::form()` / `entry()`: legacy column widths, the first row always shown,
  ⊕ / ⊖ at the end of each row, empty rows not saved; drop-downs inside a table are native selects (a custom
  drop-down is cut off by the table's scroll box).
- On record forms every field is either typeable or visibly not: inputs that cannot be changed are `->disabled()`,
  and calculated values / lookup copies are shown with an entry (`TextEntry`, state from the form) — the Core theme
  draws both as a grey disabled input with the not-allowed cursor. Never show a non-editable value that looks like
  an input, and never leave an editable field looking disabled.
- App lists are filtered only through the shared 絞り込む dialog (legacy design, `design_ref/filter1-3.png`): return the
  app's fields from `filterFields()` on its `AppListRecords` page (`Modules\Core\Application\Filters\FilterField`:
  text / textarea / number / date / dateTime / choice / user, `relationship()` or `query()` for special storage). Do not
  add Filament table `->filters()`. 適用 filters for the session, 保存 (administrators) stores a list view.
- 一覧 (list views) follow the legacy view settings: an app's list page supplies `listColumns()` (all columns, order of
  （すべて）), `defaultListViews()` (`Modules\Core\Application\Views\ListView::table()/calendar()`, from SYSTEM_BRAIN
  views.md; stored once) and `filterFields()`; its table builds columns from `viewColumns()`; its 「…」 entries go in
  `optionActions()` (表示件数 is added by Core). Register a `views` page (subclass of `AppListViewSettings`, before
  `view`) for アプリの設定 → 一覧: 追加 / 編集 / 複製 / 削除 / drag order (top = default), 表形式 or カレンダー形式.
  A legacy CUSTOM view (e.g. Gantt chart, graph) is a カスタマイズ形式 view: `ListView::custom($key, $name, $screen)` in
  `defaultListViews()` and `customListViews()` (screen => Blade view, drawn with `$page`; read the view's records with
  `$page->getFilteredTableQuery()`); it cannot be created in the 一覧 editor, only renamed / filtered / copied / deleted.
- Legacy Customine / JS buttons keep their legacy label and place: 「一覧画面メニューの右側」 → `menuActions()` of the list page
  (buttons right of 一覧 / 絞り込む; a button on the checked rows wraps its action in `onCheckedRows()` and the table is
  `->selectable()`), 「レコード詳細メニューの上側」 → `menuActions()` of the record page (buttons before 編集). A legacy
  「メニュー位置に文字を表示する」 text goes in `menuHelp()` as button label => explanation (the collapsible 「ボタンの説明」
  panel, shown to the users the legacy text was shown to). Core draws the buttons as the app's button bar (MenuButtons,
  core::filament.app-menu: icon + label, checked-row buttons behind 「N件選択」); give each one an icon. Do not hide
  legacy buttons in 「…」 or in bulk actions.
- App lists (Core, automatic): search box on the left, one horizontal scroll with its bar at the end of the table (and a synced copy fixed at the bottom of the screen while the end of the table is out of view),
  表示件数 20–300 per user, and every column sorts on a header click (昇順 ⇄ 降順). Database columns become sortable by
  themselves; give computed columns (state from relations or flags) `->sortable(query: …)`.
- Record features (legacy app settings / record screen), for every app:
  - register a `settings` page (subclass of `AppSettings`): アプリの設定 → 設定 with アクセス権 and その他の設定
    (インライン編集, コメント, 「レコードの再利用」, 「変更履歴」, 一括削除 per app); the gear has a ▾ menu of the
    settings shortcuts; when the legacy template switches one off (template.json info: useInlineEditAndDelete,
    useComment, enableRecordReuse, useHistory, enableBulkDeletion), return it from `appSettingDefaults()` on the
    resource — the registry starts the app that way;
  - list rows end with `Modules\Core\Filament\Tables\AppRecordActions::make(<Resource>)` (pencil / ×, 保存 / キャンセル)
    and use `AppTextColumn` for their columns; the list page returns `inlineFields()` (`Tables\InlineField`, rules like
    the form) and overrides `inlineEditState()` / `saveInlineRecord()` for related data. 一括削除 (「…」) is Core;
  - the model implements `Modules\Core\Application\Comments\HasRecordComments` with `Models\Concerns\RecordsComments`
    and returns the app's comment recipients (legacy app notification, e.g. 作成者 / 更新者) from
    `commentRecipients()`; the record page shows コメント | 変更履歴 and sends 通知 (database notifications). That
    side panel is always collapsed when a record opens (a bar of icons, comment count on コメント; 閉じる collapses
    it again) — keep it so in every app (Core `record-sidebar`, nothing per app);
  - the model implements `Modules\Core\Application\History\HasRecordHistory` with `Models\Concerns\RecordsHistory`
    (override for related data, e.g. users of a ユーザー選択); versions are written by the App create/edit pages;
  - the create page lists `duplicateExcludedFields()` (auto-numbered codes, attachments, legacy ids) and adds related
    data in `mutateDuplicateData()` for レコードを再利用する; the view page may give `historyLabels()`;
  - related-record sections of a record (e.g. 見積履歴 / 納品履歴 / 請求履歴) stay empty when there are no records —
    never a message such as 「関連するレコードはありません。」;
  - user names in lists and records use `Modules\Core\Filament\Support\UserPopup` (`->html()`, list cells
    `->disabledClick()`) for the ユーザー情報 popup; 印刷する (print view) needs nothing per app.
- Never write the word for the old product anywhere; call it 旧システム in the UI and "legacy system" in English.
- Cite the SYSTEM_BRAIN rule id in tests/docblocks when implementing a rule.

## Commands
- `composer test` (Pest on SQLite, app + modules), `composer test:pgsql` (same on PostgreSQL database `sfk_test`),
  `composer analyse` (Larastan L6), `composer lint` (Pint)
- `php artisan module:list`
- Docker: `docker compose up -d --build`; app at http://localhost:8080/erp

## Done = Pint clean + PHPStan clean + tests green (CI runs SQLite and PostgreSQL).
