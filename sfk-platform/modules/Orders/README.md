# Orders (business module)

Orders: 04 受注入力 (jobs / 注番, estimate, actual costs).

- Slug: `orders` · Host panel: `erp` (白石封筒) · Toggle: `MODULE_ORDERS_ENABLED` · Depends on `master-data`
- Business rules: BR-OR-01 (注番 `sfk-` + 7 digits, numbered on create — fixes D-04-1), BR-OR-02..06 (estimate),
  BR-OR-09/10 (remaining quantities), BR-OR-11 (actual cost / profit, redefined per D-04-2), BR-OR-12 (FSC),
  BR-OR-13 (AM/PM), BR-TX-01 (customer 端数区分, blank ⇒ 切捨), BR-MD-02 / BR-MD-03 (lookup choices).

## Apps

| App (registry code) | Screen | SYSTEM_BRAIN |
|---|---|---|
| 04. 受注入力 (`order-entry`) | `/erp/orders` — `OrderResource` | APP_04 (legacy app 9) |

04. 受注入力 rebuilds the legacy app with its template layout (`OrderLayout`): the head rows, 用　紙 (table
用紙情報, 小計), 版 下・製 版・刷 版, 印　刷 (tables 印刷 and トムソン・NO・シルク・箔), 製 袋・製 本・他 加 工 (folding with
「▲ 折りたたむ / ▼ 展開する」, open at first), その他, 各種原価 and 開発者用フィールド (super admins). The tables are
child rows (`order_papers`, `order_printings`, `order_finishings`); partners, the machine, the designer and 担当者 are
foreign keys instead of lookup copies. 締日 and 端数区分 of the customer are kept as of the order.

The calculated fields follow the inputs while typing and are written on every save (`Domain\Orders\JobCosting`):
用紙金額 per sheet or per kg, 断裁 / 刷版 / 印刷 / トムソン / 製袋 amounts, manual amounts with 入力方法 「手動」, 小計,
見積原価合計, 見積利益, 仕入原価計 (= 仕入金額), 実原価合計, 実売上金額, 利益 = 現在売上済金額 − 実原価合計 and 利益率.

Features of the legacy customization scripts:

| Legacy script | Here |
|---|---|
| `auto_number.js` (注番 on the edit screen) | numbered on create with Core `DocumentNumberGenerator` (sequence `order`) |
| `required_field_validation.js` (得意先 required, 数量 > 0) | form and インライン編集 rules with the same messages |
| `04_OrderEntry.bundle.js` — 利益 after save, disabled 利益 | calculated on every save, shown read-only |
| — 集計サマリー on the list | `ListOrders::orderSummary()` over the filtered and searched list |
| — 製袋 field toggle | `orders::filament.bag-toggle` |
| — 「ハート封筒受注書を読み込む」 (Claude API in the browser) | `Application\FaxImport` on the server: upload → read → preview → fill the form |
| — 仕入合計 / 社内原価 recalculated on view | entered in 各種原価 / その他 until 05 / 08N exist (see below) |
| `OrderEntryAppSearchPastOrdersHistory.bundle.js` (never mounted, D-04-3) | 過去の受注履歴検索 on the record page |
| `disableSidebar.js` | Core: the コメント / 変更履歴 panel opens collapsed |

Views (legacy order, topmost = default): 自分が担当の受注一覧, 受注一覧, 売上完了, 未売上あり, 未製造あり, 製造完了,
発送完了済, 製袋予定一覧, 製袋予定表出力, （すべて）. Template features: コメント (to 作成者 / 更新者), 変更履歴 (incl.
担当者 and the table rows), インライン編集, 再利用 (table rows copied; 注番, attachments and actual results not), 一括削除
off (`appSettingDefaults()`).

### 受注書の読み込み (FAX order import)

Settings in `Config/config.php` (`orders.fax_import`): issuer, stamp surnames → 製袋予定規格, the priority stamp,
仕入先(製造場所), model (`claude-opus-5-5`, adaptive thinking, effort `low`, server-side fallbacks). The API key is read
from `ANTHROPIC_API_KEY` on the server and never sent to the browser. Tests bind a fake `FaxOrderReader`.

## Not rebuilt yet

- Actual results written by other apps in the legacy system: 仕入金額 … 仕入特色 (05), 売上済数量 / 現在売上済金額
  (06), 製造済数量 / 社内原価 / 製造売上 (08N). They are inputs until those apps derive them from their rows
  (BR-OR-07..10); 日報一覧 and 仕入履歴 stay empty until then.
- The 売上入力 action (needs 06), the 作業明細表 PDFs (PrintCreator layouts not exported; the three attachment fields
  are kept), and the 受注入力 action on 01. 取引先マスタ.
- Dropped on purpose: 売上履歴 (purpose unclear), PDF file-name fields, string keys (関連レコードキー, 加算更新用キー,
  注番_日報連携キー), the duplicate view 「自分が担当の受注一覧 - コピー」, the plugin view 受注一覧(検索特化).
