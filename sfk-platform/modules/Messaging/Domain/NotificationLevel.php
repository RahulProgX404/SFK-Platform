<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain;

/**
 * How much of a conversation reaches a member (通知設定 of the conversation): every message, only what is meant for them
 * (@mentions, replies to their messages, 重要 / 至急 messages), or nothing. 至急 always comes through, except with nothing.
 */
enum NotificationLevel: string
{
    case All = 'all';
    case Mentions = 'mentions';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::All => 'すべてのメッセージ',
            self::Mentions => 'メンション・返信・重要のみ',
            self::None => '通知しない',
        };
    }

    /** Whether a message reaches the member: for them ($personal = mention / reply) or with its priority. */
    public function allows(bool $personal, Priority $priority): bool
    {
        return match ($this) {
            self::All => true,
            self::Mentions => $personal || $priority !== Priority::Normal,
            self::None => false,
        };
    }
}
