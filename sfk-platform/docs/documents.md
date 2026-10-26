# 帳票出力 (document output)

Our own replacement for the legacy PrintCreator service (SYSTEM_BRAIN 08_PRINTCREATOR, integration I-01; defects R-02
token in client code and R-04 SaaS dependency). Every app gets it from Core — no code per app.

- **Templates (帳票テンプレート)**: an administrator uploads an existing PDF form (or picks a blank sheet) and places
  the record's fields on it in a drag-and-drop designer.
- **Output settings (出力設定)**: which template a button prints, where the button is shown, how several records come
  out and what the file is called. An app may have several templates and several output settings.
- **Output**: one record → one PDF; the listed records (filtered/searched, or checked) → one PDF or a ZIP of one PDF
  per record (at most 500 records per click, the limit of the legacy tool). Generation is manual (a button).

## Where it is

| Who | Screen | What |
| --- | --- | --- |
| Administrators | アプリの設定 → **帳票出力** (`…/settings?tab=documents`, also in the gear ▾ menu) | 帳票テンプレート: ＋ (PDF or 白紙 + 用紙), 編集 (designer), 複製, 削除 (refused while an 出力設定 prints it). 出力設定: ＋ / 編集 / 削除, drag to reorder (= button order) |
| Administrators | Designer (`…/settings?tab=documents&template=<id>`) | テンプレート名, pages, 表示倍率, 1mm単位, 背景を差し替える, プレビュー (unsaved layout, with a chosen record or the field labels), キャンセル / 保存 |
| Users | Record page | a button per 出力設定 at **レコード詳細画面のメニュー** (before 編集), or the **上部スペース** drop-down + 出力 |
| Users | List page | a button per 出力設定 at **一覧画面のメニュー** (listed records, asks 「N件のレコードを出力しますか？」) or **（チェックしたレコード）** (check boxes appear), or the **上部スペース** drop-down + 出力 |

出力設定 options: ボタン名, テンプレート, 表示する場所 (several of the five places above), 複数のレコードを出力するとき
(1つのPDFにまとめる / レコードごとのPDF（ZIP）), 出力したファイル (新しいタブで開く / ダウンロードする), ファイル名
(fields in braces, e.g. `{number}_{customer_id}` for the legacy 見積書番号_得意先名; 「ファイル名にフィールドを追加」 inserts
one), 管理者にだけ表示する (the legacy 06 PrintCreator buttons were for one administrator only).

### Designer

- Palette: フィールド (every field of the app's record page, by its legacy label), テーブル (each サブテーブル, e.g.
  納品内容詳細), その他 (固定テキスト, 出力日, 出力者, ページ番号, 総ページ数). Drag onto the page or click to add.
- Page: the background PDF drawn by pdf.js; drag to move, corner handle to resize; arrows move 1 mm (0.5 mm without
  1mm単位, Shift 5 mm); Delete removes; Ctrl+D copies; Ctrl+S saves; Esc unselects.
- Properties: X / Y / 幅 / 高さ (mm), ページ, フォント (ゴシック / 明朝), サイズ (pt), 文字の色, 太字, 横 / 縦の配置,
  長い文字 (縮小して全体を表示 / 折り返して表示 / はみ出す部分を表示しない), 罫線を引く; fields: 書式 (表示どおり, 数値,
  金額, 日付, 日付（年月日）, 和暦, 日時) and text before / after; tables: 行の高さ, 1ページの行数 and the columns
  (field, width, alignment, 書式; reorder / remove / 列を追加).
- A table with more rows than its lines continues on another copy of all the template's pages (header fields repeated);
  ページ番号 / 総ページ数 count the pages of each record.

## Architecture

```
Filament (Core)                         Http (Core)                      Application (Core)                Domain (Core)
AppSettings + ManagesDocuments ───────▶ ─────────────────────────────▶  DocumentSettings ───────────────▶ TemplateLayout
  designer (Alpine + pdf.js)            DocumentController               TemplateBackgrounds (qpdf)        ValueFormat
AppListRecords / AppViewRecord          GET  …/background                DocumentSource ◀── record page    FileNamePattern
  buttons, 上部スペース bar ───────────▶ GET|POST …/preview  ──────────▶  DocumentPrinter                   OutputPlacement
  PrintJobs token (list)                GET  …/records/{record}            └ DocumentRenderer               OutputMode / Delivery
                                        GET  print/{token}                     └ Pdf\JapanesePdf (FPDF+FPDI)  PaperSize
```

- **Field source — the record page.** `DocumentSource` builds the app's own record page schema (its infolist) and reads
  every entry: name, legacy label and the text as shown on screen (¥ amounts, names instead of ids, dates); tables are
  the `RepeatableEntry` fields with the column headers given to `RecordTable::entry()`. So every app has its fields
  without code, and they follow its record page. Entries drawn as HTML (e.g. the ユーザー情報 popup) are read from the
  model (`ModelValues`: relation → name, attribute → value). The stored value is kept as well for 書式.
  The schema is built once and re-bound per record (500 records in well under a second).
- **Access.** Records are read through the app's resource: app permissions (`canViewAny`), レコードのアクセス権
  (`getEloquentQuery()` + `canView()`); フィールドのアクセス権 なし fields print empty (`FieldAccess::levels()` is applied
  explicitly, the PDF routes run outside the Livewire pages). Settings, designer, background and preview are for
  administrators. A list job is stored under a random token for 30 minutes and only its user can open it.
- **PDF.** `JapanesePdf` extends FPDI (FPDF): imports the background PDF pages and writes text on top. Japanese text uses
  the PDF standard Japanese CID fonts (MS-Gothic / MS-Mincho names, Adobe-Japan1, `UniJIS-UCS2-HW-H`), not embedded:
  no font files, small files, and half-width Latin / digits / kana measure exactly (alignment, shrink-to-fit,
  wrapping with 禁則). Characters outside the BMP print as 〓.
- **Uploaded PDFs** the free FPDI reader cannot open (PDF 1.5+ compressed cross-reference streams, encryption without a
  password) are rewritten once by `qpdf --object-streams=disable --decrypt` (in the PHP image); otherwise the upload is
  refused with 「このPDFファイルは読み込めません。…」. At most 20 pages, 20 MB.
- **Opening in a tab** from a list: the Livewire action stores the job and opens `/documents/print/<token>` with
  `window.open` while the click still counts as a user action; record buttons are plain links.

## Database

`document_templates`

| Column | Type | |
| --- | --- | --- |
| id | bigint | |
| business_app_id | FK business_apps, cascade | the app |
| name | varchar(100), unique per app | テンプレート名 |
| background_path | varchar null | local disk `documents/templates/<app>/<uuid>.pdf`; null = 白紙 |
| background_name | varchar null | uploaded file name |
| pages | jsonb | `[{"width": 210, "height": 297}, …]` mm |
| layout | jsonb | `{"elements": [...]}` (`TemplateLayout`) |
| created_by / updated_by | FK users null | |

`document_outputs`

| Column | Type | |
| --- | --- | --- |
| id | bigint | |
| business_app_id | FK business_apps, cascade | |
| document_template_id | FK document_templates, restrict | the template printed |
| name | varchar(64), unique per app | ボタン名 |
| placements | jsonb | `record_menu`, `record_space`, `list_menu`, `list_checked`, `list_space` |
| mode | varchar | `merged` / `zip` |
| delivery | varchar | `open` / `download` |
| file_name | varchar(200) null | `FileNamePattern` |
| admin_only | bool | 管理者にだけ表示する |
| sort_order | int | button order |

Layout element (mm from the top left of the page; sizes in pt):

```json
{"id": "e1", "type": "field", "page": 0, "x": 20, "y": 37, "w": 90, "h": 8, "field": "customer_id",
 "format": "display", "prefix": "", "suffix": " 御中", "font": "gothic", "size": 12, "bold": false,
 "color": "#000000", "align": "left", "valign": "middle", "overflow": "shrink", "border": false}
{"id": "e2", "type": "text", "text": "御中", "...": "same box and text properties"}
{"id": "e3", "type": "table", "table": "lines", "x": 15, "y": 98, "rowHeight": 8, "rows": 12,
 "columns": [{"field": "product_name", "width": 80, "align": "left", "format": "display"}], "...": "font properties"}
```

## HTTP endpoints (web session, signed-in users; `core.documents.*`)

| Method | Path | Who | Returns |
| --- | --- | --- | --- |
| GET | `/documents/templates/{template}/background` | administrators | the background PDF (designer) |
| GET / POST | `/documents/templates/{template}/preview` | administrators | PDF of the saved layout, or of `layout` (JSON, POST); `record` = key of a record, else field labels |
| GET | `/documents/outputs/{output}/records/{record}` | users who may view the record | the record's PDF (`inline` or `attachment` by 出力したファイル) |
| GET | `/documents/print/{token}` | the user who chose the records | PDF or ZIP of the records of a list job |

Settings and the designer's 保存 are Livewire calls on the アプリの設定 page (`saveDesign`, actions `addTemplate`,
`replaceBackground`, `deleteTemplate`, `output`, `deleteOutput`, `reorderOutputs`), as for the other app settings.

## Libraries and licenses

| Library | License | Use |
| --- | --- | --- |
| setasign/fpdf 1.9 | MIT | PDF writer |
| setasign/fpdi 2.6 | MIT | importing the background PDF pages |
| pdf.js (pdfjs-dist 4.10.38), `public/vendor/pdfjs` | Apache-2.0 (LICENSE file included) | drawing the background in the designer |
| qpdf (Debian package in the PHP image) | Apache-2.0 | rewriting PDFs FPDI cannot read |

The PrintCreator customization sample of its vendor (toyokumo on GitHub, MIT) was studied for its workflow — a 帳票
drop-down + 出力 on the record header (one PDF), on the list header (records matching the search, up to 500, with a
confirmation) and for checked rows. It calls the vendor's SaaS API from the legacy platform's JavaScript, so none of
its code applies here and none was copied; templates, designer and renderer are our own.

## Not done yet

- Saving the PDF into the record's attachment field (legacy `見積書PDF` etc.).
- Templates of the legacy documents: the PrintCreator layouts were never exported (SYSTEM_BRAIN U-03); administrators
  upload the forms and place the fields.
