<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Apps;

/**
 * What a user may do in an app — the legacy app permissions (レコード閲覧・追加・編集・削除, ファイル読み込み・
 * 書き出し) and アプリ管理 (user, 2026-11-11): the app's administrator. アプリ管理 includes every other ability and what
 * only administrators may do in that app — アプリの設定 (一覧, 設定, 帳票出力), the buttons and steps for administrators,
 * no レコード / フィールドのアクセス権 limits (AppAccess::manages()). It stays inside the app: ダッシュボード設定, 共通管理
 * and the like follow ユーザー種別 only.
 */
enum AppAbility: string
{
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Import = 'import';
    case Export = 'export';
    case Manage = 'manage';

    public function label(): string
    {
        return match ($this) {
            self::View => '閲覧',
            self::Create => '追加',
            self::Update => '編集',
            self::Delete => '削除',
            self::Import => 'ファイル読み込み',
            self::Export => 'ファイル書き出し',
            self::Manage => 'アプリ管理',
        };
    }

    /** Column of app_permissions / space_permissions that stores this ability. */
    public function column(): string
    {
        return 'can_'.$this->value;
    }

    /** Abilities that do not change data (still allowed during メンテナンス中). */
    public function isReadOnly(): bool
    {
        return $this === self::View || $this === self::Export;
    }

    /** @return list<string> */
    public static function columns(): array
    {
        return array_map(fn (self $ability): string => $ability->column(), self::cases());
    }
}
