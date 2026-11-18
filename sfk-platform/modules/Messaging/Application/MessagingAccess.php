<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use App\Models\User;
use Modules\Messaging\Domain\GroupRule;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\Message;
use Modules\Messaging\Models\MessageAttachment;
use Modules\Messaging\Models\Participant;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Who may read and do what (docs/messaging.md "Security"). Every read, write, download and forward goes through here:
 * a conversation is open only to its current members — someone who left or was removed sees nothing of it, and an
 * id that is not the user's answers 404 like one that does not exist (no probing of ids). A message is open to a
 * member when it lies after their floor (joined without the history / 「トークを削除」).
 */
final class MessagingAccess
{
    /** Every entry to the messenger asks here first: the company's settings are laid over the config once per request. */
    public function __construct()
    {
        MessagingSettings::ensure();
    }

    /** The user's active membership of the conversation (with the conversation loaded), else 404. */
    public function member(User $user, int $conversationId): Participant
    {
        $participant = Participant::query()
            ->with('conversation')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();

        if ($participant === null || ! $participant->conversation instanceof Conversation) {
            throw new NotFoundHttpException;
        }

        return $participant;
    }

    /**
     * A message the user may see, with their membership, else 404.
     *
     * @return array{0: Message, 1: Participant}
     */
    public function message(User $user, int $messageId): array
    {
        $message = Message::query()->find($messageId);
        if (! $message instanceof Message) {
            throw new NotFoundHttpException;
        }

        $participant = $this->member($user, $message->conversation_id);
        if ($message->id <= $participant->floorMessageId()) {
            throw new NotFoundHttpException;
        }

        return [$message, $participant];
    }

    /** An attachment the user may download: theirs while not yet sent, else of a message they may see. */
    public function attachment(User $user, int $attachmentId): MessageAttachment
    {
        $attachment = MessageAttachment::query()->find($attachmentId);
        if (! $attachment instanceof MessageAttachment) {
            throw new NotFoundHttpException;
        }

        if ($attachment->message_id === null) {
            if ($attachment->user_id !== $user->id) {
                throw new NotFoundHttpException;
            }
            $this->member($user, $attachment->conversation_id);

            return $attachment;
        }

        [$message] = $this->message($user, $attachment->message_id);
        if ($message->isUnsent()) {
            throw new NotFoundHttpException;
        }

        return $attachment;
    }

    /** A group rule (グループの設定) for this member; 403 when it does not allow them. */
    public function requireRule(Participant $participant, GroupRule $rule, string $message = 'この操作は管理者のみ行えます。'): void
    {
        if (! $rule->allows($participant->role)) {
            throw new HttpException(403, $message);
        }
    }

    public function requireGroup(Participant $participant): Conversation
    {
        $conversation = $participant->conversation;
        if (! $conversation->isGroup()) {
            throw new HttpException(422, 'グループのトークではありません。');
        }

        return $conversation;
    }

    public function canCreateGroups(User $user): bool
    {
        MessagingSettings::ensure();

        return config('messaging.group_creators') !== 'administrators' || $user->user_type->isAdministrator();
    }

    /** Channels (部署・プロジェクト): administrators by default (チャンネルを作成できる人). */
    public function canCreateChannels(User $user): bool
    {
        MessagingSettings::ensure();

        return config('messaging.channel_creators') === 'everyone' || $user->user_type->isAdministrator();
    }

    /** Whether the member may write in the conversation now (an archived channel: nobody; a read-only one: 管理者). */
    public function canPost(Participant $participant): bool
    {
        $conversation = $participant->conversation;
        if ($conversation->isArchived()) {
            return false;
        }

        return ! $conversation->isChannel() || $conversation->groupSettings()->post->allows($participant->role);
    }

    /**
     * Moderation: a group's or channel's 管理者 may remove others' messages there (marked as removed by a 管理者, kept for
     * the audit). Never in a direct conversation, never by someone outside the conversation.
     */
    public function canModerate(Participant $participant): bool
    {
        return $participant->conversation->isGroup() && $participant->isAdmin();
    }

    /** 全社アナウンス: administrators (ユーザー種別 管理者 / システム管理者) only. */
    public function canAnnounce(User $user): bool
    {
        return $user->user_type->isAdministrator();
    }

    /** Company stickers are added by administrators. */
    public function canManageStickers(User $user): bool
    {
        return $user->user_type->isAdministrator();
    }
}
