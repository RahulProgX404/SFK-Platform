# Permissions (legacy-style)

How access is decided, from the design in `../permission.md`. The real legacy ACLs were never exported
(SYSTEM_BRAIN 15 U-01; 11_PERMISSIONS §1), so administrators enter them here; this model replaces the
hard-coded single-user conditions of the old system (11_PERMISSIONS §2, 16_REBUILD_SPEC backend "role-based
permissions").

## Concepts

| Concept                | the legacy system            | Here                                                                                                                              | Maintained by                                                           |
| ---------------------- | ------------------ | --------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| Dashboard (department) | —                  | A Filament panel (白石封筒 `erp`, future products). 共通管理 (`settings`) is administrator-only                                   | code                                                                    |
| Space (group)          | スペース           | `spaces` (per dashboard) + members `space_user` (employees only). A user may be in several spaces                                 | administrators, in the dashboard's **ダッシュボード設定 → スペース**    |
| App (feature)          | アプリ             | `business_apps` registry: `code`, name, status, at most one space                                                                 | the code (AppResource screens); s共通管理 **共通管理 → アプリ設定** |
| App permission         | アプリのアクセス権 | `app_permissions`: rows for 全員 / one 従業員カテゴリ / one employee × 閲覧・追加・編集・削除・ファイル読み込み・ファイル書き出し | administrators, in **ダッシュボード設定 → アプリの権限**                |

## Who may open a dashboard (`Modules\Core\Access\DashboardAccess`)

super_admin: all. Administrator-only dashboards (`<id>.panel.admin_only`): super_admin / admin. Others: the
user's own `dashboards` plus the `dashboards` of all their 従業員カテゴリ (several allowed).

## What a user may do in an app (`Modules\Core\Access\AppAccess`)

1. **super_admin** — everything, in every status (an unregistered code counts as 開発中).
2. **admin** — everything once the app is 準備完了 / 公開中 / メンテナンス中 / 停止中; nothing while 開発中・テスト中.
3. **employee** — only 公開中 and メンテナンス中 apps, only when the app is placed in a space whose dashboard they can
   open and of which they are a member. Then the first matching level wins: **個人 → 従業員カテゴリ → 全員**; a
   matching level replaces the lower ones entirely, so it can also take rights away. With several categories, the
   rights of all their matching category rows are combined. No row ⇒ no access.
   During メンテナンス中 only 閲覧 and ファイル書き出し remain. Without 閲覧 a row grants nothing (like the legacy system).

## What a dashboard lists (`AppAccess::listsOnDashboard`)
The ポータル アプリ list, the shortcuts and the sidebar show super admins every app of the dashboard in every stage —
including apps whose screens are built there but not yet placed in a space — dimmed (sidebar: stage badge) while not
公開中. Everyone else sees only 公開中 and メンテナンス中 apps they may view.

## App lifecycle (`Modules\Core\Domain\Apps\AppStatus`, `Application\Apps\AppLifecycle`)

```
開発中 → テスト中 → 準備完了 → 公開中 ⇄ メンテナンス中
                              公開中 ⇄ 停止中
```

- Nothing moves back to 開発中; メンテナンス中 and 停止中 never move into each other.
- super_admin: every arrow. admin: only 準備完了 → 公開中, 公開中 → 停止中, 停止中 → 公開中.
- Apps come from the code: `AppDiscovery` finds every concrete `AppResource` under `modules/*/Filament` (disabled
  modules included) and `AppLifecycle::syncWithCode` adds new codes as 開発中 / unplaced, renames, and deletes apps (with
  their permissions) whose code is gone. It runs whenever アプリ設定 is opened and via `php artisan core:apps-sync`
  (run it on deploy). There is no manual create/delete. The board shows all six columns to admins too.

## Building an app's screens

Extend `Modules\Core\Filament\AppResource` instead of `Filament\Resources\Resource` and return the registry
code from `appCode()`:

```php
final class PartnerResource extends AppResource
{
    public static function appCode(): string { return 'partner-master'; } // 01. 取引先マスタ
}
```

List/view need 閲覧, create 追加, edit 編集, delete 削除; the resource appears in the sidebar under its space's
name, only for users who may view it. Gate custom actions with `static::appCan(AppAbility::Import)` /
`AppAbility::Export`. Override `appName()` for the registry name (default: navigation label). The app then appears
on アプリ設定 as 開発中, and a super admin moves it through testing.

Every app screen has the same design (like the legacy apps): the pages extend `Modules\Core\Filament\AppPages\`
`AppListRecords` / `AppCreateRecord` / `AppEditRecord` / `AppViewRecord`, which replace Filament's page header with
`core::filament.app-header`:

- blue title bar with the app icon (アイコン文字) and name, breadcrumb (home › スペース › アプリ), grey band with the app
  description (`BusinessApp.description`, else `AppResource::appDescription()`);
- list: the app's views as a dropdown (`getListViews()`; columns read `$livewire->listView`), round ＋ (追加), gear
  (administrators: the app's permissions, else アプリ設定), 「…」 with the header actions (e.g. ファイルに書き出す), and
  「1 - 100（N件中）」 paging (100 per page) above and below striped light-blue rows;
- create / edit: キャンセル and 保存 above the form (none below), light-blue ground; record tabs sit above the fields
  (`RecordLayout::tabbed()` / `tabPanel()`) and switch only their own sections;
- record page: the header actions (編集する, 削除する) in the toolbar.

## Not covered yet

Record-level rules (e.g. 16 申請管理 "only own records") and field-level rules (e.g. 作業単価) from the legacy system are
not part of app permissions; implement them in the app (see 従業員マスタ情報 for the field-level pattern).
