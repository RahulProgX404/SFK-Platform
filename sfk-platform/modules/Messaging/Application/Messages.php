<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Notifications\Notice;
use Modules\Core\Application\Notifications\Notifier;
use Modules\Core\Application\Records\RecordCards;
use Modules\Messaging\Domain\AttachmentKind;
use Modules\Messaging\Domain\MessageText;
use Modules\Messaging\Domain\MessageType;
use Modules\Messaging\Domain\NotificationLevel;
use Modules\Messaging\Domain\Priority;
use Modules\Messaging\Domain\TimeWindow;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\Message;
use Modules\Messaging\Models\MessageAttachment;
use Modules\Messaging\Models\MessageEdit;
use Modules\Messaging\Models\MessageHide;
use Modules\Messaging\Models\MessageMention;
use Modules\Messaging\Models\MessagePin;
use Modules\Messaging\Models\MessageReaction;
use Modules\Messaging\Models\Participant;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Sending and changing messages. Every operation is checked against the member's rights (MessagingAccess) and
 * announced to the open pages after its transaction commits (Signals).
 *
 * - send: text, images / files / a voice message (uploaded before, AttachmentStore), a sticker, a reply, mentions. A
 *   message carries the sender's own id (client_id): the same request sent twice (double tap, retry after a lost
 *   answer) stores one message and answers the first one again.
 * - edit / 送信取消 (unsend for everyone): one's own messages, within the company's time windows; the text before is
 *   kept in message_edits (audit). An unsent message keeps its row and files for the audit, but nobody can read them.
 * - 自分のみ削除: hides a message from one's own history.
 */
final class Messages
{
    public const NOTIFICATION_MENTION = 'messaging.mention';

    /** someone replied to one's message */
    public const NOTIFICATION_REPLY = 'messaging.reply';

    /** a 重要 / 至急 message */
    public const NOTIFICATION_PRIORITY = 'messaging.priority';

    public function __construct(
        private readonly MessagingAccess $access,
        private readonly Directory $directory,
        private readonly Stickers $stickers,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  array{body?: string|null, client_id?: string|null, reply_to_id?: int|null, attachment_ids?: list<int>, sticker?: string|null, mentions?: list<int>, mention_all?: bool, priority?: int|null, record?: array{app?: string, id?: string}|null}  $input
     */
    public function send(User $me, Participant $participant, array $input): Message
    {
        $conversation = $participant->conversation;
        $clientId = isset($input['client_id']) && Str::isUuid((string) $input['client_id']) ? strtolower((string) $input['client_id']) : null;
        if ($clientId !== null && ($same = Message::query()->where('user_id', $me->id)->where('client_id', $clientId)->first()) instanceof Message) {
            return $same;
        }

        if (! $this->access->canPost($participant)) {
            throw new HttpException(403, $conversation->isArchived() ? 'アーカイブされたチャンネルには投稿できません。' : 'このチャンネルは管理者のみ投稿できます。');
        }

        $body = MessageText::clean($input['body'] ?? null);
        if (mb_strlen($body) > (int) config('messaging.max_message_length', 5000)) {
            throw ValidationException::withMessages(['body' => 'メッセージが長すぎます。']);
        }

        $sticker = ($input['sticker'] ?? '') !== '' ? (string) $input['sticker'] : null;
        if ($sticker !== null && ! $this->stickers->exists($sticker)) {
            throw ValidationException::withMessages(['sticker' => 'スタンプが見つかりません。']);
        }

        // an ERP record shared as a card: only one the sender may open (the card itself is drawn for each reader)
        $record = null;
        if (is_array($input['record'] ?? null) && filled($input['record']['app'] ?? null) && filled($input['record']['id'] ?? null)) {
            $app = (string) $input['record']['app'];
            $id = (string) $input['record']['id'];
            if (! RecordCards::has($app) || RecordCards::card($me, $app, $id)['state'] !== 'ok') {
                throw ValidationException::withMessages(['record' => '共有できるレコードではありません。']);
            }
            $record = ['app' => $app, 'id' => $id];
        }

        $priority = Priority::tryFrom((int) ($input['priority'] ?? 0)) ?? Priority::Normal;

        $attachments = $this->pendingAttachments($me, $conversation, (array) ($input['attachment_ids'] ?? []));
        if ($body === '' && $attachments === [] && $sticker === null && $record === null) {
            throw ValidationException::withMessages(['body' => 'メッセージを入力してください。']);
        }

        $replyTo = null;
        if (! empty($input['reply_to_id'])) {
            $replyTo = Message::query()->where('conversation_id', $conversation->id)->find((int) $input['reply_to_id']);
            if (! $replyTo instanceof Message || $replyTo->id <= $participant->floorMessageId() || $replyTo->type === MessageType::System) {
                throw ValidationException::withMessages(['reply_to_id' => '返信先のメッセージが見つかりません。']);
            }
        }

        $members = $conversation->members()->get(['id', 'user_id', 'muted_until', 'notification_level', 'role']);
        $memberIds = $members->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
        $mentions = array_values(array_intersect(array_map('intval', (array) ($input['mentions'] ?? [])), array_diff($memberIds, [$me->id])));
        $mentionAll = (bool) ($input['mention_all'] ?? false) && $conversation->isGroup();
        if ($mentionAll && ! $conversation->groupSettings()->mentionAll->allows($participant->role)) {
            throw ValidationException::withMessages(['body' => '@全員 は管理者のみ使えます。']);
        }

        $type = $sticker !== null ? MessageType::Sticker : MessageType::forAttachments($attachments[0]->kind ?? null);
        $meta = array_filter([
            'sticker' => $sticker,
            'mention_all' => $mentionAll ?: null,
            'file' => $type === MessageType::File ? $attachments[0]->original_name : null,
            'record' => $record,
        ], fn (mixed $value): bool => $value !== null);

        try {
            $message = DB::transaction(function () use ($me, $participant, $conversation, $body, $type, $meta, $replyTo, $clientId, $attachments, $mentions, $priority): Message {
                $message = $conversation->messages()->create([
                    'user_id' => $me->id,
                    'type' => $type,
                    'body' => $body !== '' ? $body : null,
                    'meta' => $meta !== [] ? $meta : null,
                    'reply_to_id' => $replyTo?->id,
                    'client_id' => $clientId,
                    'priority' => $priority,
                ]);

                if ($attachments !== []) {
                    MessageAttachment::query()->whereIn('id', array_map(fn (MessageAttachment $attachment): int => $attachment->id, $attachments))->update(['message_id' => $message->id]);
                }
                foreach ($mentions as $userId) {
                    MessageMention::query()->create(['user_id' => $userId, 'message_id' => $message->id, 'conversation_id' => $conversation->id, 'created_at' => now()]);
                }

                $this->advance($conversation, $message);
                $participant->forceFill(['last_read_message_id' => $message->id, 'last_read_at' => now(), 'last_delivered_message_id' => $message->id])->save();

                return $message;
            });
        } catch (QueryException $exception) {
            // the same client id committed by a parallel request
            if ($clientId !== null && ($same = Message::query()->where('user_id', $me->id)->where('client_id', $clientId)->first()) instanceof Message) {
                return $same;
            }
            throw $exception;
        }

        Signals::conversationChanged($conversation->id, $memberIds);
        $this->notify($me, $conversation, $message, $members->all(), $mentions, $mentionAll, $replyTo, $priority);

        return $message;
    }

    public function edit(User $me, int $messageId, string $body): Message
    {
        [$message, $participant] = $this->access->message($me, $messageId);
        if ($message->user_id !== $me->id || $message->isUnsent() || ! $message->type->isEditable()) {
            throw new HttpException(403, 'このメッセージは編集できません。');
        }
        if (! (new TimeWindow(self::minutes('messaging.edit_window_minutes')))->isOpen($message->created_at, now())) {
            throw new HttpException(403, '編集できる期間を過ぎています。');
        }

        $body = MessageText::clean($body);
        if ($body === '' && $message->type === MessageType::Text) {
            throw ValidationException::withMessages(['body' => 'メッセージを入力してください。']);
        }
        if (mb_strlen($body) > (int) config('messaging.max_message_length', 5000)) {
            throw ValidationException::withMessages(['body' => 'メッセージが長すぎます。']);
        }
        if ($body === (string) $message->body) {
            return $message;
        }

        DB::transaction(function () use ($me, $message, $body): void {
            MessageEdit::query()->create(['message_id' => $message->id, 'user_id' => $me->id, 'action' => 'edit', 'body' => $message->body, 'created_at' => now()]);
            $message->forceFill(['body' => $body !== '' ? $body : null, 'edited_at' => now()])->save();
        });
        $this->changed($participant->conversation);

        return $message;
    }

    /** 送信取消: the message disappears for everyone (a line says it was unsent); the audit keeps its text. */
    public function unsend(User $me, int $messageId): Message
    {
        [$message, $participant] = $this->access->message($me, $messageId);
        if ($message->user_id !== $me->id || $message->type === MessageType::System) {
            throw new HttpException(403, 'このメッセージは取り消せません。');
        }
        if ($message->isUnsent()) {
            return $message;
        }
        if (! (new TimeWindow(self::minutes('messaging.unsend_window_minutes')))->isOpen($message->created_at, now())) {
            throw new HttpException(403, '送信取消できる期間を過ぎています。');
        }

        DB::transaction(function () use ($me, $message): void {
            MessageEdit::query()->create(['message_id' => $message->id, 'user_id' => $me->id, 'action' => 'unsend', 'body' => $message->body, 'created_at' => now()]);
            $message->forceFill(['unsent_at' => now()])->save();
            MessagePin::query()->where('message_id', $message->id)->delete();
            MessageReaction::query()->where('message_id', $message->id)->delete();
        });
        $this->changed($participant->conversation);

        return $message;
    }

    /** 自分のみ削除. */
    public function hideForMe(User $me, int $messageId): void
    {
        [$message] = $this->access->message($me, $messageId);
        MessageHide::query()->firstOrCreate(['user_id' => $me->id, 'message_id' => $message->id], ['created_at' => now()]);
    }

    /** A reaction; the same one again takes it back, another replaces it (one per person). */
    public function react(User $me, int $messageId, string $emoji): Message
    {
        [$message, $participant] = $this->access->message($me, $messageId);
        if ($message->isUnsent() || $message->type === MessageType::System) {
            throw new HttpException(422, 'このメッセージにはリアクションできません。');
        }
        $emoji = trim($emoji);
        if ($emoji === '' || mb_strlen($emoji) > 8 || strlen($emoji) > 32 || preg_match('/[\p{L}\p{N}\s<>&"\']/u', $emoji) === 1) {
            throw ValidationException::withMessages(['emoji' => 'リアクションが正しくありません。']);
        }

        DB::transaction(function () use ($me, $message, $emoji): void {
            $existing = MessageReaction::query()->where('message_id', $message->id)->where('user_id', $me->id)->first();
            if ($existing instanceof MessageReaction && $existing->emoji === $emoji) {
                $existing->delete();
            } elseif ($existing instanceof MessageReaction) {
                $existing->forceFill(['emoji' => $emoji])->save();
            } else {
                MessageReaction::query()->create(['message_id' => $message->id, 'user_id' => $me->id, 'emoji' => $emoji]);
            }
            $message->touch();
        });
        $this->changed($participant->conversation);

        return $message;
    }

    public function pin(User $me, int $messageId, bool $pin): Message
    {
        [$message, $participant] = $this->access->message($me, $messageId);
        $conversation = $participant->conversation;
        if ($conversation->isGroup()) {
            $this->access->requireRule($participant, $conversation->groupSettings()->pinMessages, 'ピン留めは管理者のみ行えます。');
        }
        if ($message->isUnsent() || $message->type === MessageType::System) {
            throw new HttpException(422, 'このメッセージはピン留めできません。');
        }

        DB::transaction(function () use ($me, $message, $conversation, $pin): void {
            if ($pin) {
                if (MessagePin::query()->where('conversation_id', $conversation->id)->count() >= 50) {
                    throw ValidationException::withMessages(['pin' => 'ピン留めは50件までです。']);
                }
                MessagePin::query()->firstOrCreate(['conversation_id' => $conversation->id, 'message_id' => $message->id], ['pinned_by' => $me->id, 'created_at' => now()]);
            } else {
                MessagePin::query()->where('message_id', $message->id)->delete();
            }
            $message->touch();
        });
        $this->changed($conversation);

        return $message;
    }

    /**
     * Forwards a message to other conversations of the user (転送): text, sticker and files (the same stored files),
     * marked with who wrote it first.
     *
     * @param  list<int>  $conversationIds
     * @return list<Message>
     */
    public function forward(User $me, int $messageId, array $conversationIds): array
    {
        [$source] = $this->access->message($me, $messageId);
        if ($source->isUnsent() || $source->type === MessageType::System) {
            throw new HttpException(422, 'このメッセージは転送できません。');
        }

        $targets = array_slice(array_values(array_unique(array_map('intval', $conversationIds))), 0, 10);
        $participants = array_map(fn (int $id): Participant => $this->access->member($me, $id), $targets);
        foreach ($participants as $target) {
            if (! $this->access->canPost($target)) {
                throw new HttpException(403, '「'.$target->conversation->name.'」には投稿できません。');
            }
        }
        $files = MessageAttachment::query()->where('message_id', $source->id)->orderBy('id')->get();
        $origin = $source->forwarded_from_id !== null && is_array($source->meta['forwarded'] ?? null)
            ? $source->meta['forwarded']
            : ['user' => $source->user_id, 'name' => $this->directory->name($source->user_id)];

        $sent = [];
        foreach ($participants as $participant) {
            $conversation = $participant->conversation;
            $message = DB::transaction(function () use ($me, $participant, $conversation, $source, $files, $origin): Message {
                $meta = array_filter(['sticker' => $source->meta['sticker'] ?? null, 'file' => $source->meta['file'] ?? null, 'forwarded' => $origin], fn (mixed $value): bool => $value !== null);
                $message = $conversation->messages()->create([
                    'user_id' => $me->id,
                    'type' => $source->type,
                    'body' => $source->body,
                    'meta' => $meta,
                    'forwarded_from_id' => $source->id,
                ]);
                foreach ($files as $file) {
                    MessageAttachment::query()->create([
                        ...$file->only(['kind', 'path', 'original_name', 'mime', 'size', 'width', 'height', 'duration_ms']),
                        'conversation_id' => $conversation->id,
                        'message_id' => $message->id,
                        'user_id' => $me->id,
                    ]);
                }
                $this->advance($conversation, $message);
                $participant->forceFill(['last_read_message_id' => $message->id, 'last_read_at' => now(), 'last_delivered_message_id' => $message->id])->save();

                return $message;
            });
            $this->changed($conversation);
            $sent[] = $message;
        }

        return $sent;
    }

    /** The member read up to this message (never beyond the newest, never backwards). */
    public function markRead(Participant $participant, int $upTo): void
    {
        $upTo = min($upTo, (int) $participant->conversation->last_message_id);
        if ($upTo <= (int) $participant->last_read_message_id) {
            return;
        }

        $participant->forceFill([
            'last_read_message_id' => $upTo,
            'last_read_at' => now(),
            'last_delivered_message_id' => max($upTo, (int) $participant->last_delivered_message_id),
        ])->save();
        Signals::readChanged($participant->conversation_id);
        Signals::inboxesChanged([$participant->user_id]);
    }

    /** The member's device received up to this message (配信済み). */
    public function markDelivered(Participant $participant, int $upTo): void
    {
        $upTo = min($upTo, (int) $participant->conversation->last_message_id);
        if ($upTo <= (int) $participant->last_delivered_message_id) {
            return;
        }

        $participant->forceFill(['last_delivered_message_id' => $upTo])->save();
        Signals::readChanged($participant->conversation_id);
    }

    private function advance(Conversation $conversation, Message $message): void
    {
        $conversation->forceFill(['last_message_id' => $message->id, 'last_message_at' => $message->created_at])->save();
    }

    private function changed(Conversation $conversation): void
    {
        Signals::conversationChanged($conversation->id, $conversation->members()->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    /**
     * @param  list<int>  $ids
     * @return list<MessageAttachment>
     */
    private function pendingAttachments(User $me, Conversation $conversation, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        if (count($ids) > (int) config('messaging.attachments.max_files', 10)) {
            throw ValidationException::withMessages(['attachment_ids' => 'ファイルが多すぎます。']);
        }

        $attachments = MessageAttachment::query()
            ->whereIn('id', $ids)
            ->where('user_id', $me->id)
            ->where('conversation_id', $conversation->id)
            ->whereNull('message_id')
            ->orderBy('id')
            ->get()
            ->all();
        if (count($attachments) !== count($ids)) {
            throw ValidationException::withMessages(['attachment_ids' => 'ファイルが見つかりません。もう一度添付してください。']);
        }
        $voice = array_filter($attachments, fn (MessageAttachment $attachment): bool => $attachment->kind === AttachmentKind::Voice);
        if ($voice !== [] && count($attachments) > 1) {
            throw ValidationException::withMessages(['attachment_ids' => 'ボイスメッセージは1件ずつ送信してください。']);
        }
        // images first, so a message of photos and documents shows its photos
        usort($attachments, fn (MessageAttachment $a, MessageAttachment $b): int => [$a->kind !== AttachmentKind::Image, $a->id] <=> [$b->kind !== AttachmentKind::Image, $b->id]);

        return $attachments;
    }

    /**
     * The 通知 of a message in the bell — at most one per member, for its strongest reason: they were mentioned
     * (メンション), their message was replied to (返信), everyone was mentioned (@全員) or it is 重要 / 至急. Each member's
     * level for the conversation decides (通知: すべて / メンション・返信・重要のみ / 通知しない); a running 通知オフ still
     * lets their own mentions and replies through. The text of the message is never put into the 通知.
     *
     * @param  list<Participant>  $members
     * @param  list<int>  $mentioned
     */
    private function notify(User $me, Conversation $conversation, Message $message, array $members, array $mentioned, bool $mentionAll, ?Message $replyTo, Priority $priority): void
    {
        $byReason = [];
        foreach ($members as $member) {
            if ($member->user_id === $me->id) {
                continue;
            }
            $mention = in_array($member->user_id, $mentioned, true);
            $reply = $replyTo !== null && $replyTo->user_id === $member->user_id;
            $reason = match (true) {
                $mention => 'mention',
                $reply => 'reply',
                $mentionAll => 'all',
                $priority !== Priority::Normal => 'priority',
                default => null,
            };
            if ($reason === null || ! $this->reaches($member, $mention || $reply, $mentionAll, $priority)) {
                continue;
            }
            $byReason[$reason][] = $member->user_id;
        }
        if ($byReason === []) {
            return;
        }

        $where = $conversation->isGroup() ? '「'.$conversation->name.'」' : 'トーク';
        $url = route('filament.erp.pages.messages', ['c' => $conversation->id, 'm' => $message->id]);
        foreach ($byReason as $reason => $ids) {
            [$kind, $title] = match ($reason) {
                'mention' => [self::NOTIFICATION_MENTION, $me->name.'さんが'.$where.'であなたをメンションしました'],
                'reply' => [self::NOTIFICATION_REPLY, $me->name.'さんが'.$where.'であなたのメッセージに返信しました'],
                'all' => [self::NOTIFICATION_MENTION, $me->name.'さんが'.$where.'で全員にメンションしました'],
                default => [self::NOTIFICATION_PRIORITY, '【'.$priority->label().'】'.$me->name.'さんから'.$where.'にメッセージ'],
            };
            $this->notifier->send(Notice::make($kind)->title($title)->url($url)->openLabel('トークを開く'), User::query()->whereIn('id', $ids)->get(), $me);
        }
    }

    /** Whether a message reaches the member under their level for the conversation. */
    private function reaches(Participant $member, bool $personal, bool $mentionAll, Priority $priority): bool
    {
        $level = $member->notification_level;
        if ($level === NotificationLevel::None) {
            return false;
        }
        // 通知オフ for a while: only what is for them personally
        if ($member->muted_until !== null && $member->muted_until->isFuture()) {
            return $personal;
        }

        return $level->allows($personal || $mentionAll, $priority);
    }

    /**
     * Moderation: a group's / channel's 管理者 removes another member's message for everyone (shown as removed by a
     * 管理者); its text stays in the audit (message_edits) and the action in the messaging audit log.
     */
    public function moderate(User $me, int $messageId): Message
    {
        [$message, $participant] = $this->access->message($me, $messageId);
        if (! $this->access->canModerate($participant) || $message->user_id === $me->id || $message->type === MessageType::System) {
            throw new HttpException(403, 'このメッセージは削除できません。');
        }
        if ($message->isUnsent()) {
            return $message;
        }

        DB::transaction(function () use ($me, $message): void {
            MessageEdit::query()->create(['message_id' => $message->id, 'user_id' => $me->id, 'action' => 'moderate', 'body' => $message->body, 'created_at' => now()]);
            $message->forceFill(['unsent_at' => now(), 'meta' => [...(array) ($message->meta ?? []), 'moderated_by' => $me->id]])->save();
            MessagePin::query()->where('message_id', $message->id)->delete();
            MessageReaction::query()->where('message_id', $message->id)->delete();
            MessagingAudit::record($me, 'message.moderated', $message->conversation_id, ['message' => $message->id, 'author' => $message->user_id]);
        });
        $this->changed($participant->conversation);

        return $message;
    }

    /** 未読にする: the newest message from someone else counts as unread again (for the member only). */
    public function markUnread(Participant $participant): void
    {
        $newest = Message::query()
            ->where('conversation_id', $participant->conversation_id)
            ->where('id', '>', $participant->floorMessageId())
            ->where('user_id', '!=', $participant->user_id)
            ->whereNull('unsent_at')
            ->max('id');
        if ($newest === null) {
            return;
        }

        $participant->forceFill(['last_read_message_id' => (int) $newest - 1])->save();
        Signals::readChanged($participant->conversation_id);
        Signals::inboxesChanged([$participant->user_id]);
    }

    private static function minutes(string $key): ?int
    {
        $value = config($key);

        return $value === null ? null : (int) $value;
    }
}
