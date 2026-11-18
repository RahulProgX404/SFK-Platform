<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use App\Models\User;
use Modules\Messaging\Models\MessagingAuditLog;

/**
 * The audit of what administrators do in the messenger (メッセージ管理 → 監査ログ): channels created / archived, members
 * added / removed / made 管理者 by someone else, messages removed by a 管理者 (moderation), settings, announcements.
 * Names, ids and kinds — never the text of a message.
 */
final class MessagingAudit
{
    public const LABELS = [
        'settings.updated' => '設定を変更',
        'channel.created' => 'チャンネルを作成',
        'channel.archived' => 'チャンネルをアーカイブ',
        'channel.restored' => 'チャンネルを再開',
        'member.added' => 'メンバーを追加',
        'member.removed' => 'メンバーを削除',
        'member.role' => '管理者を変更',
        'message.moderated' => 'メッセージを管理者削除',
        'announcement.published' => 'アナウンスを投稿',
    ];

    /** @param  array<string, mixed>  $details */
    public static function record(User $actor, string $action, ?int $conversationId, array $details = []): void
    {
        MessagingAuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'conversation_id' => $conversationId,
            'details' => $details !== [] ? $details : null,
            'created_at' => now(),
        ]);
    }
}
