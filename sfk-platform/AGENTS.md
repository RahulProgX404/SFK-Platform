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
- Real foreign keys + unique constraints; no string join keys. Every foreign key column gets an index (`->index()`;
  PostgreSQL does not add one) — `DatabaseIndexTest` fails otherwise.
- Records of an app shown on another screen (related lists, 履歴 sections, searches) are read through
  `<Resource>::onlyVisible(<Model>::query())`, so app permissions and レコードのアクセス権 apply — never a bare
  `Model::query()` for what the user sees.
- Speed: nothing large goes into every page — panel CSS / JS live in the theme views served as one cached file
  (`ThemeAssets`), big data of a dialog is fetched when it opens (e.g. 絞り込む). Modules are cached
  (`php artisan module:cache`, part of `optimize`); a new conventional folder in a module needs it rebuilt.
  Lists draw 100+ rows, so nothing per row may be a component of its own (2026-11-09): user names are
  `UserPopup` buttons carrying their card (one script draws it, `core::filament.user-popup-script`; 従業員カテゴリ /
  役職 of all users read in two queries per request), the row 「…」 is one button per row with the page's one menu
  (`AppRecordActions` → `core::filament.tables.row-menu`, `row-menu-script`; the actions stay on the table) and its
  icons are CSS (`.sfk-i-*`). Pages and Livewire updates are sent without the indentation of their HTML
  (`Core\Http\HtmlIndentation`, about two thirds smaller). Dialog windows clip with `overflow: clip`, never `hidden`
  (hidden cut long dialogs off without a scrollbar).
- Uploaded files go on `Modules\Core\Application\Files\Uploads::disk()` (private; object storage on Laravel Cloud, whose
  servers keep no files) — never `disk('local')`; a tool that needs a file path reads `Uploads::localFile()`. No new
  system programs or extra containers (the host runs only the PHP app, Postgres, Valkey and the queue).
- No user names/emails in code; use the ユーザー種別 `user_type` (`super_admin` / `admin` / `employee`) for system rights and
  user / 従業員カテゴリ dashboards (`Modules\Core\Access\DashboardAccess`) for panel access. There are no roles and no
  organizations; never gate features on a category or 役職 name.
- Screens of an app (feature) extend `Modules\Core\Filament\AppResource` (app permissions, spaces, status) and its
  pages extend `Modules\Core\Filament\AppPages\App{List,Create,Edit,View}Record(s)` (the shared app screen design) —
  see `docs/permissions.md`.
- Components (SFK UI, `core::filament.ui`, included by the theme and the login layout): our own styles for check boxes,
  radio buttons, option lists, switches, tooltips, attachments, collapsible groups, multi-select chips, drop-down
  options and read-only tables, whose design language follows KUC (Cybozu's MIT UI component library — see
  `THIRD_PARTY_NOTICES.md`; no library code is copied). Style a new component there, by component; our own native
  inputs take the look with `sfk-ui-check` / `sfk-ui-radio`. Toasts keep their own design. A new Composer / npm
  dependency gets a license check (`composer licenses --no-dev`) and a line in THIRD_PARTY_NOTICES.md.
- Access rights beyond the app permissions (docs/permissions.md): レコード (`RecordAccess`, via
  `AppResource::getEloquentQuery()` and `canView/canEdit/canDelete($record)`) and フィールド (`FieldAccess`, via
  `RecordSections::gate()` and AppListRecords). Keep them working: overrides of `getEloquentQuery()` call the parent,
  forms and record pages build their fields through `RecordSections::rows()` (or call `RecordSections::gate()`), list
  column names are the form's attribute names, and records that have a 作成者 store it in `created_by`.
- Look (since 2026-11, `../design_ref/*.png`, `core::filament.design` over `core::filament.theme`): white surfaces with
  hairline borders and small rounded corners (`--sfk-radius` 6px, cards `--sfk-radius-lg` 10px), the brand blue
  (`BasePanelProvider::BRAND_BLUE`, #05467E) as primary — through the `--sfk-primary` / `--kt-blue` tokens, never a
  hard-coded colour; dark text, soft grey table headers. No top bar except on the ERP home (/erp, ErpPanelProvider) —
  elsewhere the sidebar (search, bell, user menu inside) collapses fully and a floating button opens it. An app's
  screens (`HasAppChrome`) always open with the sidebar collapsed; other screens get back the state the user had before
  the apps (Core `AppPages\AppSidebarState`, a body-start script before Alpine reads Filament's stored state). The sidebar
  follows `../sidebar.png` (bug.md 2026-11-07): a grey column (`--sfk-sidebar`), the dashboard name with ≡ (closes it) at
  the right, quiet section names (⌄ only on hover / when closed) with a hairline between sections, dark icon + label
  rows, the open screen a white raised card with a blue bar on its left edge and dark text. While the
  server works, three dots move over a transparent blur (no text). Style new parts only with the `--sfk-*` tokens at the
  top of `core::filament.design` (ui-refine.md, 2026-11; sizes of `../index-ref.png`): type (`--sfk-fs-title` 18px,
  `-heading` 16, `-body` 14, `-label` 14 for labels / table headers / buttons / tabs / navigation, `-small` 13, `-badge` 12,
  `-figure` 18; nothing above 22px;
  weights `--sfk-fw-*` 400/500/600/700, never heavier), colour roles (`--sfk-ink` / `-text` / `-muted` / `-subtle`,
  `-line` / `-line-soft` / `-line-strong`, `-page` / `-surface` / `-head` / `-hover`, `-success` / `-warning` / `-danger`
  with `-soft` grounds), spacing (`--sfk-space-1…10` on a 4px scale, `--sfk-gutter` 24px / 16px on phones) and one control
  height (`--sfk-control` 36px for buttons, inputs, selects and toolbar tools); table header rows on `--sfk-table-head`,
  the app lists' (index pages) on the dark `--sfk-list-head` with white labels. Font: Noto Sans JP, served by this system
  (`public/fonts/noto-sans-jp`, OFL — no font host is allowed by the CSP), system Japanese fonts as fallback; amounts,
  quantities, dates and codes use tabular numerals. Filament's `text-sm` is 14px (the theme redefines `--text-xs` …
  `--text-3xl`, at most 20px). Table headers (except the dark ones, which are white) and field labels (key names on record / edit / new pages)
  are black (`--kt-label`), never grey. No table column is bold (no `->weight()` on table columns; the theme forces normal weight in table cells —
  only the header row is bold).
- App screens (Core `app-header`, nothing per app): the breadcrumb home › app name on a list, home › app name ›
  レコードの追加 / レコードの編集 / レコードの詳細 on the record screens, with (i) for the description popup. List: エクスポート (帳票出力; same ボタン名 = sub-menu) and 新規 at the
  top right, summary cards (`summaryCards()` with `SummaryCard::make()` — never a toolbar render hook of your own), the
  一覧 as tabs, then search, 絞り込む, the switch of the legacy buttons (+ ボタンの説明 popup), 並び替え and ⚙ (the
  app's `optionActions()`, 一括削除, アプリの設定) — no 列 chooser, the columns come from the 一覧; rows end with 「…」 (編集する → edit page, 削除する); 表示件数 and the page
  numbers under the table, which fills the screen; cell values stay on one line (full text on hover). Record page:
  編集, 再利用, 「…」 (印刷 / 削除), エクスポート, ⚙, ‹ › at the top right; the page's other buttons and the legacy buttons
  under the name. Create / edit: キャンセル (left) and 保存 (right) in the form's footer, which stays at the bottom of the
  screen; other buttons (e.g. FAX 読込) under the name. An app may add records in a panel over the screen instead of a
  create page (`AppResource::quickCreateComponent()`, e.g. 08. 日報入力's step-by-step, mobile-first QuickDailyReport): Core
  then opens it from 新規 (event `AppResource::QUICK_CREATE_EVENT`), the dashboard's 新規作成, `?new=1` (再利用:
  `&duplicate=<id>`, all through `AppResource::newRecordUrl()`) and marks the saved record in the list (`?created=<id>`).
  New quick entries extend Core `Filament\QuickEntry\QuickEntry` (user 2026-11-09; 14. 事故報告管理, 16. 申請管理,
  17. 安全衛生チェック, 18. アルコールチェック — their create pages are gone): the app lists `QuickStep`s of `QuickField`s
  (choice / person / lookup taps move on, date / time chips, number, text, select, custom partials such as 損害 and the
  チェックリスト as cards), the optional fields (追加情報), defaults, the record form's rules and `create()`; the engine
  keeps 戻る / 次へ, the check (a tap on a row goes back to its step, 次へ there returns to the check), フィールドのアクセス権,
  再利用, 変更履歴 and opens the saved record. Look: `core::filament.quick-entry-styles` (shared with QuickDailyReport).
  An app that is one screen (`AppResource::isSingleScreen()`, e.g. 12. 製袋予定管理's 製袋予定表 board) shows only its first
  view — no 一覧 tabs, 絞り込む or toolbar — with ⚙ (administrators: アプリの権限 only) in place of 新規.
  Every table on a record / form / dialog has the dark header row
  (`kt-subtable`, `sfk-related-table`, `sfk-data-table`).
- A record page of another module's app can get tabs and cards from other modules (`Core\Filament\Records\RecordTabs`,
  e.g. 受注 / 請求書 / 売掛明細 on 01. 取引先マスタ, `ViewPartner::TABS`), read through the providing app's `onlyVisible()`;
  fields shown in such a head respect フィールドのアクセス権.
- Field order (create, edit and record page — one layout per app, `<App>Layout` rows): related fields in labelled
  groups (string rows = group headings, left out when nothing under them is shown), the most important first (who /
  when / what, then details, amounts, notes and attachments; derived and internal fields last), each row filling the
  12-column grid of the 1,500 px canvas (125 px a column: 250 dates / amounts / codes, 375 users / selects, 500–750
  names, 1,500 notes and tables) and every field wide enough for its content at 16px. Legacy labels, drop-downs
  showing 「-----」 when blank, lookups listing their choices when opened. Table fields (サブテーブル) are built with
  `Modules\Core\Filament\Records\RecordTable::form()` / `entry()`: legacy column widths, the first row always shown,
  ⊕ / ⊖ at the end of each row, empty rows not saved; drop-downs inside a table are native selects (a custom
  drop-down is cut off by the table's scroll box). The table's frame is as wide as its columns (`--kt-subtable-width`),
  not the whole row; a group heading with the table's own name hides its label (`->hiddenLabel()`, e.g. 損害).
- What a record must be — required fields, values that must differ or be unique, rules between fields — is one
  `Modules\Core\Application\Records\RecordRules` class per app (`AppResource::recordRules()`, e.g.
  `AccidentReportRules`, `LeaveRequestRules`, `SafetyCheckRules`, `AlcoholCheckRules`), checked by Core on every screen
  that saves: the quick entry (`QuickEntry::recordRules()`), the edit page (`AppEditRecord::beforeValidate()`, shown with
  the inputs' own errors) and インライン編集 (the edited fields only, so a legacy record is not blocked by what it lacks
  elsewhere). Uniqueness is checked there, not by database constraints (legacy records hold a few duplicates); decide
  rules from SYSTEM_BRAIN and the legacy data, and mark the inputs `->required()` to match.
- App names: `AppResource::appName()` is the name the app is built with (the registry starts with it); what users see —
  sidebar, titles, notices, delete warnings — is `AppResource::displayName()`, the registry name, which administrators
  change in アプリの設定 → 設定 → アプリ名 (`AppLifecycle::rename()`, `business_apps.name_customized`; the code sync then
  leaves it alone until 元の名前に戻す). Never show `appName()` to users.
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
  core::filament.app-menu: icon + label; a checked-row button may carry a live 「N件」 badge via
  `MenuButtons::withCheckedCount()`); give each one an icon. On a list they sit behind the switch next to 絞り込む
  (user's design, 2026-11); do not move them into 「…」 or bulk actions.
  Buttons of the legacy JavaScript (`../js-customization/<app>/`) are legacy buttons too: keep their labels, including
  the 【新】 / [新] / (新) trial ones, as separate buttons next to the Customine ones (after them, in the script's order).
- 検索 of the app lists (Core): an app marks its default columns `->searchable()` on AppTextColumn (a related record's
  column: `->searchable(query: SearchQuery::through('customer', 'name'))`, never a raw `whereLike`); words compare
  folded (全角 / 半角, case, カタカナ / ひらがな, romaji → kana — `Domain\Search\SearchText`, `Application\Search\SearchQuery`)
  and the icon right of the box searches one column of the 一覧 (searchable columns + 絞り込む fields). Every default
  search column gets a trigram index in its module's migration (`SearchIndex::create($table, [...])`, PostgreSQL) —
  `ListSearchTest` fails otherwise.
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
    `->disabledClick()`) for the ユーザー情報 popup; 印刷する (print view) needs nothing per app;
  - 帳票出力 (PrintCreator replacement, `docs/documents.md`) needs nothing per app: アプリの設定 → 帳票出力 (templates from
    uploaded PDFs, designer, 出力設定) and the buttons / 上部スペース on the list and record pages come from Core. Its
    fields are read from the app's record page (infolist), so keep every printable field and table there; tables
    built with `RecordTable::entry()` give their column headers. Page overrides of `getHeaderActions()` keep the
    parent's actions (the 帳票 buttons are among them).
- Deleting: parts of a record (its rows, sub-tables, 変更履歴, コメント) cascade in the database; references from other
  records (a 取引先 used by orders, a 機械 by daily reports …) block (`restrictOnDelete()`), and every delete goes through
  `Modules\Core\Application\Records\RecordDeletion` (`GuardedDelete::apply()` on a DeleteAction) so a blocked delete says
  what uses the record. App lists have one red 「…」 item (Core `bulkDelete`, two confirmations): 一括削除 of the
  filtered list when no row is checked (その他の設定 + administrators), 選択したレコードを削除 of the checked rows (削除 permission).
  The record page's 削除する asks twice too (Core `delete` → `confirmDelete`). Every first confirmation (and the list
  row ×) warns which other apps' records are deleted, unlinked or keep the delete from happening
  (`RecordDeletion::impact()` via `Support\DeleteImpact`, read from the foreign keys). A model that deletes other
  records' rows itself in a `deleting` hook (e.g. 売上 → 納品書 / 請求書 rows) implements
  `Modules\Core\Application\Records\DeletesWith` so the warning counts them. No per-app 削除 buttons.
- 通知 (bell + e-mail, `docs/notifications.md`): every notification goes through Core `Notifier::send(Notice, $recipients, $actor)`
  with a kind the module registers in `NotificationKinds` (it then appears in プロフィール → 通知設定); `Notice::about($record)`
  so only users who may open the record get it. Never `Notification::sendToDatabase()` directly (toasts: `->send()`).
  Conditions found by looking (dates, thresholds) are daily `NotificationCheck`s with `once()` keys.
- A Filament action put straight into a Blade view (`{{ $this->fooAction }}`, `{{ ($page->fooAction)([...]) }}`) is drawn
  even when it is hidden — disabled, but visible. Wrap it: `@if (($a = ($this->fooAction)([...]))->isVisible()) {{ $a }} @endif`.
- プロフィール: employees see only 一般設定 (their own 氏名 / 住所・連絡先); 通知設定, 従業員マスタ情報, パスワード設定 and
  利用可能なダッシュボード are for administrators (user's decision, 2026-11). お知らせ is shown on /portal (edited on the
  dashboard's home, `?action=editNotice`).
- The home of a dashboard (/erp, Core `PortalPage`; bug.md 2026-11-05, `../design_ref/Dashboard.png`) is the dashboard:
  ダッシュボード with 全体 | 個人 (page filter `scope`), the period (date button → page filters `from` / `to`) and
  新規作成 (`config('<panel>.dashboard.create')`, only apps the user may add to — one app = its page, none = disabled),
  then the cards: the panel's Filament widgets
  (business modules, e.g. Analytics `Filament/Widgets`), each saying its width in a 12-column grid. The money (rows 1–2,
  分析 → 売上分析) needs 財務分析を表示 (`users.show_financial_analysis`, ticked by administrators in ユーザー管理;
  `User::seesFinancialAnalysis()`, super admins always) and 財務分析の範囲 (`users.financial_scope`, bug.md 2026-11-06):
  全体 sees everyone's money and may switch to 個人, 個人のみ only the sales they are the first 担当者 of
  (`User::seesOverallFinance()`; money cards read `DashboardCard::salesData()`, 売上分析 its own row only; 分析 → 日報分析 and its tab only for 全体 — user
  2026-11-09). Users without
  財務分析を表示 get 個人の概要 as the first row (their 勤務時間, 出勤日数 — the days they filled a 日報 for —, 収入, 休暇日数,
  有給休暇), then 勤務時間・収入の推移 (`WorkTrendCard`, two 12-month charts: hours and yen never share an axis); the same card is on the user's
  page (ユーザー管理, 02. 従業員マスタ) for the person and administrators — other modules add cards there through Core
  `UserDetailWidgets`. 作業者ランキング, 上位の売上先, 担当者別売上 and 売れ筋 TOP3 are only for 全体
  (`DashboardCard::seesEveryonesMoney()`, bug.md 2026-11-07). 個人 has one layout for 個人のみ and for 全体 switched to 個人
  (bug.md 2026-11-09; `DashboardCard::personalLayout()`, cards say `shownInLayout()` / `sortInLayout()`, PortalPage asks
  through Core `FollowsDashboardFilters`): 売上合計 · 売上概要 (span 4 + 8, no 入出金); 売上の推移 · 最近表示した項目 (8 + 4;
  `SalesTrendCard`: their own sales per month of a chosen year against the year before, personal-growth.png; 12-month
  charts are drawn by `Dashboard\TrendChart` + `widgets.partials.trend-chart`); 休暇・勤怠予定 · 最近のアクティビティ (6 + 6,
  the activity in one column). Rows 3–4 are for everyone (休暇・勤怠予定 and クイックアクション, half
  the row each without 作業者ランキング, クイックアクション first without 財務分析を表示; 作業者ランキング frames the user's own
  row; クイックアクション is called 最近表示した項目 while it lists recent apps). The
  top bar has アプリ in place of the search box (the search is in the sidebar): one panel over the whole screen with a
  search box, スペース, よく使うアプリ (the five the user opens most, `AppUsageLog` written by every app screen; before
  they opened any, those everyone opens most — always only apps they may open) and the apps. Administrators' drag
  changes the default order (the registry); everyone else's drag only their own (`users.app_order`, 既定の並びに戻す);
  without one they see the default. No アプリショートカット any more.
- Never write the word for the old product anywhere; call it 旧システム in the UI and "legacy system" in English.
- Cite the SYSTEM_BRAIN rule id in tests/docblocks when implementing a rule.

## Commands
- `composer test` (Pest on SQLite, app + modules), `composer test:pgsql` (same on PostgreSQL database `sfk_test`),
  `composer analyse` (Larastan L6), `composer lint` (Pint)
- `php artisan module:list`
- Docker: `docker compose up -d --build`; app at http://localhost:8080/erp

## Done = Pint clean + PHPStan clean + tests green (CI runs SQLite and PostgreSQL).
