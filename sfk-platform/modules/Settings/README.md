# Settings (extension module)

Platform settings panel (`/settings`, brand「ERP 共通管理」): the place where administrators maintain
system-wide settings. Japanese UI.

| Area | Screen | What it does |
|---|---|---|
| 設定ダッシュボード | `/settings` | Settings menu (one card per area) and key figures |
| ユーザー管理 | `/settings/users` | Headline figures (登録ユーザー, 在籍中) + user list with create / view / edit / delete, パスワード再設定 |
| 組織・役職 | `/settings/organizations` | Nestable organizations; positions (役職) per organization; read-only member list |

## User fields
Required: 表示名 (`name`), メールアドレス (`email`, login id), ロール (typed freely; new roles are created), 在籍状況.
Optional: password (blank ⇒ a temporary one is generated and shown once), 姓・名・フリガナ, 社員番号, 従業員区分
(SYSTEM_BRAIN APP_02 categories), 生年月日, 入社日・退職日, 郵便番号・住所, 電話・携帯・連絡用メール, 緊急連絡先, 備考,
利用可能なダッシュボード (`panel.<id>.access`), 所属組織・役職 (several organizations, one 主所属).

## Rules
- Data and use cases live in **Core** (`UserAccountService`, `SuperAdminProvisioner`, `Organization`, `Position`,
  `OrganizationMembership`); this module is only the UI plus `Application\UserStatistics` for the figures.
- Passwords set here are temporary: the user must change them at the next sign-in.
- The env-managed super admin cannot be edited or deleted here; `super-admin` cannot be assigned; nobody can delete
  their own account.
- Add a new settings area as a resource/page under `Filament/` and a card in `Filament/Widgets/SettingsMenuWidget.php`.

## Access & toggles
- Permission `panel.settings.access` (or super-admin). Run the Core seeder after enabling.
- Path/domain/brand: `SETTINGS_PANEL_PATH`, `SETTINGS_PANEL_DOMAIN`, `SETTINGS_PANEL_BRAND`.
- Toggle: `MODULE_SETTINGS_ENABLED=false`.
