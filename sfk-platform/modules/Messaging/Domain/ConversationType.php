<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain;

enum ConversationType: string
{
    /** one-to-one (トーク) */
    case Direct = 'direct';

    /** グループ */
    case Group = 'group';

    /** チャンネル (部署・プロジェクト): created by who company policy allows, open to join or invite-only, may be read-only */
    case Channel = 'channel';

    /** Conversations with members, rules and 管理者 (group and channel). */
    public function isShared(): bool
    {
        return $this !== self::Direct;
    }
}
