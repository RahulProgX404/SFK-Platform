<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Apps;

/**
 * Lifecycle of an app (feature) — permission.md "Permission hierarchy".
 *
 *   開発中 → テスト中 → 準備完了 → 公開中 ⇄ メンテナンス中
 *                                    公開中 ⇄ 停止中
 *
 * Nothing moves back to 開発中; テスト中 only comes from 開発中, 準備完了 only from テスト中; メンテナンス中 and 停止中
 * never move into each other. Super admins may make every move above; admins only 準備完了 → 公開中,
 * 公開中 → 停止中 and 停止中 → 公開中.
 */
enum AppStatus: string
{
    case Developing = 'developing';
    case Testing = 'testing';
    case Ready = 'ready';
    case Published = 'published';
    case Maintenance = 'maintenance';
    case Stopped = 'stopped';

    public function label(): string
    {
        return match ($this) {
            self::Developing => '開発中',
            self::Testing => 'テスト中',
            self::Ready => '準備完了',
            self::Published => '公開中',
            self::Maintenance => 'メンテナンス中',
            self::Stopped => '停止中',
        };
    }

    /** @return list<self> */
    public function targets(bool $superAdmin): array
    {
        if ($superAdmin) {
            return match ($this) {
                self::Developing => [self::Testing],
                self::Testing => [self::Ready],
                self::Ready, self::Maintenance, self::Stopped => [self::Published],
                self::Published => [self::Maintenance, self::Stopped],
            };
        }

        return match ($this) {
            self::Ready, self::Stopped => [self::Published],
            self::Published => [self::Stopped],
            default => [],
        };
    }

    public function canMoveTo(self $to, bool $superAdmin): bool
    {
        return in_array($to, $this->targets($superAdmin), true);
    }

    /** While being built (開発中・テスト中) an app is visible to super admins only. */
    public function isSuperAdminOnly(): bool
    {
        return $this === self::Developing || $this === self::Testing;
    }

    /** Employees use published apps, and may still look at apps under maintenance. */
    public function isOpenToEmployees(): bool
    {
        return $this === self::Published || $this === self::Maintenance;
    }

    /** During maintenance employees can only read, whatever their permission says. */
    public function isReadOnlyForEmployees(): bool
    {
        return $this === self::Maintenance;
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
