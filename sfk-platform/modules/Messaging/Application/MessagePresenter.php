<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Core\Application\Records\RecordCards;
use Modules\Messaging\Domain\MessageText;
use Modules\Messaging\Domain\MessageType;
use Modules\Messaging\Models\Message;
use Modules\Messaging\Models\MessageAttachment;
use Modules\Messaging\Models\MessageMention;
use Modules\Messaging\Models\MessagePin;
use Modules\Messaging\Models\MessageReaction;

/**
 * Messages as the messenger draws them (compact JSON; names come from the page's directory, so only user ids travel).
 * A page of messages costs a fixed number of queries whatever its size: attachments, reactions, replied-to messages,
 * pins and mentions are each read once for all of them (none when there is nothing to read). The text of a message
 * unsent for everyone never leaves the server.
 */
final class MessagePresenter
{
    public function __construct(private readonly RecordLinks $records) {}

    /**
     * @param  iterable<Message>  $messages
     * @return list<array<string, mixed>>
     */
    public function present(iterable $messages, User $viewer): array
    {
        $messages = collect($messages)->values();
        if ($messages->isEmpty()) {
            return [];
        }

        $ids = $messages->pluck('id')->all();
        $visible = $messages->reject(fn (Message $message): bool => $message->isUnsent());
        $visibleIds = $visible->pluck('id')->all();

        $attachments = $visibleIds === [] ? collect() : MessageAttachment::query()->whereIn('message_id', $visibleIds)->orderBy('id')->get()->groupBy('message_id');
        $reactions = MessageReaction::query()->whereIn('message_id', $ids)->orderBy('id')->get(['message_id', 'user_id', 'emoji'])->groupBy('message_id');
        $pinned = MessagePin::query()->whereIn('message_id', $ids)->pluck('message_id')->flip();
        $mentions = $visibleIds === [] ? collect() : MessageMention::query()->whereIn('message_id', $visibleIds)->get(['message_id', 'user_id'])->groupBy('message_id');

        $replyIds = $visible->pluck('reply_to_id')->filter()->unique()->values()->all();
        $replies = $replyIds === [] ? collect() : Message::query()->whereIn('id', $replyIds)->get()->keyBy('id');
        $replyAttachments = $replyIds === [] ? collect() : MessageAttachment::query()->whereIn('message_id', $replyIds)->orderBy('id')->get()->groupBy('message_id');

        $cards = $this->records->cards($visible->pluck('body')->filter()->all(), $viewer);

        // shared ERP records: drawn for this reader (their rights; deleted / not theirs to see says so)
        $shared = [];
        foreach ($visible as $message) {
            $ref = $message->meta['record'] ?? null;
            if (is_array($ref) && isset($ref['app'], $ref['id'])) {
                $key = $ref['app'].'#'.$ref['id'];
                $shared[$key] ??= RecordCards::card($viewer, (string) $ref['app'], (string) $ref['id']);
            }
        }

        return $messages->map(fn (Message $message): array => $this->one(
            $message,
            $viewer,
            $attachments->get($message->id, collect()),
            $reactions->get($message->id, collect()),
            $pinned->has($message->id),
            $mentions->get($message->id, collect())->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all(),
            $message->reply_to_id !== null ? $replies->get($message->reply_to_id) : null,
            $message->reply_to_id !== null ? $replyAttachments->get($message->reply_to_id, collect()) : collect(),
            $cards,
            $shared,
        ))->all();
    }

    /** @return array<string, mixed> */
    public function presentOne(Message $message, User $viewer): array
    {
        return $this->present([$message], $viewer)[0];
    }

    /** One line about a message for the inbox, notifications and reply quotes. */
    public static function summary(Message $message, ?MessageAttachment $first = null, int $length = 60): string
    {
        if ($message->isUnsent()) {
            return isset($message->meta['moderated_by']) ? '管理者によって削除されました' : 'メッセージの送信を取り消しました';
        }

        $text = MessageText::preview((string) $message->body, $length);

        return match ($message->type) {
            MessageType::Image => $text !== '' ? '[画像] '.$text : '画像を送信しました',
            MessageType::File => $text !== '' ? '[ファイル] '.$text : 'ファイル: '.($first->original_name ?? (string) ($message->meta['file'] ?? '')),
            MessageType::Voice => 'ボイスメッセージ',
            MessageType::Sticker => 'スタンプを送信しました',
            default => $text !== '' ? $text : (isset($message->meta['record']) ? 'ERPレコードを共有しました' : ''),
        };
    }

    /**
     * @param  Collection<int, MessageAttachment>  $attachments
     * @param  Collection<int, MessageReaction>  $reactions
     * @param  list<int>  $mentioned
     * @param  Collection<int, MessageAttachment>  $replyAttachments
     * @param  array<string, array<string, mixed>>  $cards
     * @param  array<string, array<string, mixed>>  $shared
     * @return array<string, mixed>
     */
    private function one(Message $message, User $viewer, Collection $attachments, Collection $reactions, bool $pinned, array $mentioned, ?Message $reply, Collection $replyAttachments, array $cards, array $shared = []): array
    {
        $ref = is_array($message->meta['record'] ?? null) ? $message->meta['record'] : null;
        $unsent = $message->isUnsent();
        $meta = $unsent ? [] : (array) ($message->meta ?? []);
        $body = $unsent ? null : $message->body;

        $grouped = [];
        foreach ($reactions as $reaction) {
            $grouped[$reaction->emoji][] = (int) $reaction->user_id;
        }

        $messageCards = [];
        if ($body !== null) {
            foreach (RecordLinks::urlsIn($body) as $url) {
                if (isset($cards[$url])) {
                    $messageCards[] = $cards[$url];
                }
            }
        }

        return [
            'id' => $message->id,
            'cid' => $message->client_id,
            'user' => $message->user_id,
            'type' => $message->type->value,
            'body' => $body,
            'sticker' => $meta['sticker'] ?? null,
            'system' => $message->type === MessageType::System ? ($meta['event'] ?? null) : null,
            'fwd' => isset($meta['forwarded']) && is_array($meta['forwarded']) ? $meta['forwarded'] : null,
            'all' => (bool) ($meta['mention_all'] ?? false),
            'mentions' => $mentioned,
            'reply' => $reply !== null && ! $unsent ? [
                'id' => $reply->id,
                'user' => $reply->user_id,
                'text' => self::summary($reply, $replyAttachments->first(), 80),
                'thumb' => ! $reply->isUnsent() && $replyAttachments->first()?->kind->value === 'image' ? self::attachmentUrl($replyAttachments->first()) : null,
            ] : null,
            'att' => $attachments->map(fn (MessageAttachment $attachment): array => self::attachment($attachment))->values()->all(),
            'rx' => collect($grouped)->map(fn (array $users, string $emoji): array => [
                'e' => $emoji,
                'n' => count($users),
                'mine' => in_array($viewer->id, $users, true),
                'users' => $users,
            ])->values()->all(),
            'cards' => $messageCards,
            'record' => ! $unsent && $ref !== null ? ($shared[$ref['app'].'#'.$ref['id']] ?? null) : null,
            'priority' => $unsent ? 0 : $message->priority->value,
            'moderated' => $unsent && isset($message->meta['moderated_by']),
            'edited' => $message->edited_at !== null && ! $unsent,
            'unsent' => $unsent,
            'pinned' => $pinned,
            'at' => $message->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function attachment(MessageAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'kind' => $attachment->kind->value,
            'name' => $attachment->original_name,
            'size' => $attachment->size,
            'mime' => $attachment->mime,
            'url' => self::attachmentUrl($attachment),
            'w' => $attachment->width,
            'h' => $attachment->height,
            'dur' => $attachment->duration_ms,
        ];
    }

    public static function attachmentUrl(MessageAttachment $attachment): string
    {
        return route('messaging.attachments.show', ['attachment' => $attachment->id]);
    }
}
