# Settings (extension module)

Platform settings panel (`/settings`, brand「共通管理」): the place where administrators maintain
system-wide settings. Japanese UI.

| Area               | Screen                          | What it does                                                                                                                                     |
| ------------------ | ------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| 設定ダッシュボード | `/settings`                     | Settings menu (one card per area) and key figures                                                                                                |
| ユーザー管理       | `/settings/users`               | Headline figures (登録ユーザー, 在籍中) + user list with create / view / edit / delete, パスワード再設定                                         |
| 従業員カテゴリ     | `/settings/employee-categories` | Options of the 従業員カテゴリ dropdown and the 利用可能なダッシュボード of each category (add / rename / reorder / deactivate)                   |
| 役職               | `/settings/positions`           | Options of the 役職 dropdown (add / rename / reorder / deactivate)                                                                               |
| アプリ設定         | `/settings/apps`                | App registry as a drag-and-drop board over the six statuses (apps come from the code; super admins set the icon text; see `docs/permissions.md`) |

## User fields

Required: 表示名 (`name`), メールアドレス (`email`, login id), ユーザー種別 (`user_type`), 在籍状況, and the whole
従業員マスタ情報 (SYSTEM_BRAIN APP_02): 担当者コード, 作業員コード (both unique), 部課コード, 従業員カテゴリ
(`employee_categories`; seeded with the legacy options, administrators add more from the dropdown's
「選択肢を追加」 or the 従業員カテゴリ screen), 作業単価, 残業単価 (integer yen per hour).
従業員カテゴリ allows several choices (`employee_category_user`).
Optional: password (「パスワードを自動生成」 fills it and shows it for copying; blank ⇒ generated on save and shown
once), 姓・名・フリガナ, 性別, 社員番号, 生年月日,
入社日・退職日, 郵便番号・住所, 電話・携帯・連絡用メール, 緊急連絡先, 備考, 役職 (`positions`: one op共通管理esignation such as
部長・課長; administrators add options from the dropdown's 「選択肢を追加」 or the 役職 screen — not in the legacy system),
利用可能なダッシュボード (`users.dashboards`: extra dashboards for this user; 共通管理 is not offered).

There are no roles and no organizations: 従業員カテゴリ is the person's duty in the company and decides their dashboards;
役職 is their designation and grants nothing. Dashboards: see below.

## Rules

- Data and use cases live in **Core** (`UserAccountService`, `SuperAdminProvisioner`, `EmployeeCategory`, `Position`);
  this module is only the UI plus `Application\UserStatistics` for the figures. Both option-list screens share
  `Filament\Support\OptionListResource` / `ManageOptionList`; the models share `Core\Models\Concerns\IsOptionList`.
- Passwords set here are temporary: the user must change them at the next sign-in.
- **ユーザー種別 ≠ 従業員カテゴリ.** ユーザー種別 (`Core\Access\UserType`) is how the person uses this system;
  従業員カテゴリ is their position/duty in the company (営業, 製造 …) and grants no system rights.

    | ユーザー種別                 | Creates / edits / deletes / resets passwords of | Notes                                                          |
    | ---------------------------- | ----------------------------------------------- | -------------------------------------------------------------- |
    | スーパー管理者 `super_admin` | 管理者, 従業員                                  | only共通管理e environment (`core:super-admin`); read-only here |
    | 管理者 `admin`               | 従業員                                          | not other admins, not themselves                               |
    | 従業員 `employee`            | nobody                                          | no access to 共通管理                                      |

    Enforced in `UserAccountService` (and `UserResource::canCreate/canEdit/canDelete` for the UI). Nobody can delete
    their own account.

- 従業員マスタ情報 is created and edited by administrators (super_admin / admin) only. 作業単価 / 残業単価 are
  visible only to administrators and to the user themselves (cost data is confidential, SYSTEM_BRAIN 11_PERMISSIONS §3,
  `Core\Access\EmployeeDataAccess`); the rates are also `#[Hidden]`.
- 共通管理ards** (`Core\Access\DashboardAccess`): a user may open the dashboards ticked on their own user form
  **plus\*\* those of their 従業員カテゴリ (a deactivated category keeps granting them to the users who still have it).
  共通管理 is never offered on either form (and `UserAccountService` drops it) — only super_admin / admin open it,
  by ユーザー種別. Clicking the dashboard name (top left) lists the user's dashboards for switching.
- Add a new settings area as a resource/page under `Filament/` and a card in `Filament/Widgets/SettingsMenuWidget.php`.

## Access & toggles

- ユーザー種別 super_admin / admin only (`settings.panel.admin_only = true`).
- Path/domain/brand: `SETTINGS_PANEL_PATH`, `SETTINGS_PANEL_DOMAIN`, `SETTINGS_PANEL_BRAND`.
- Toggle: `MODULE_SETTINGS_ENABLED=false`.
