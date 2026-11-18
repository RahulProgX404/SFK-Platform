<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Messaging\Domain\MessageType;
use Modules\Messaging\Models\Message;
use Modules\Messaging\Models\MessageAttachment;
use Modules\Messaging\Models\Participant;

/**
 * The user's conversation list (トーク一覧) with what each row shows — name, image, the newest message, its time, the
 * unread count, @ for an unread mention, pinned / muted / archived / favourite — and the unread total of the badge.
 * A fixed number of queries whatever the number of conversations: memberships with their conversations, the newest
 * messages, the unread counts (one grouped query over the (conversation_id, id) index, from each member's read
 * watermark), unread mentions, and the other people of the direct conversations / the sizes of the groups.
 */
final class Inbox
{
    /** Conversations listed at most (the newest first); older ones are reached through the search. */
    private const LIMIT = 300;

    public function __construct(private readonly Directory $directory) {}

    /** @return list<array<string, mixed>> */
    public function list(User $me): array
    {
        $memberships = Participant::query()
            ->with('conversation')
            ->where('user_id', $me->id)
            ->whereNull('left_at')
            ->join('conversations', 'conversations.id', '=', 'conversation_participants.conversation_id')
            ->orderByRaw('COALESCE(conversations.last_message_at, conversations.created_at) DESC')
            ->limit(self::LIMIT)
            ->get(['conversation_participants.*'])
            // 「トークを削除」 hides it until a newer message arrives
            ->filter(fn (Participant $p): bool => $p->hidden_at === null || (int) $p->conversation->last_message_id > (int) $p->cleared_message_id)
            ->values();
        if ($memberships->isEmpty()) {
            return [];
        }

        $conversationIds = $memberships->pluck('conversation_id')->all();
        $lastIds = $memberships->map(fn (Participant $p): ?int => $p->conversation->last_message_id)->filter()->values()->all();
        $last = $lastIds === [] ? collect() : Message::query()->whereIn('id', $lastIds)->get()->keyBy('id');
        $lastFiles = $lastIds === [] ? collect() : MessageAttachment::query()->whereIn('message_id', $lastIds)->orderBy('id')->get()->groupBy('message_id');
        $unread = $this->unreadCounts($me->id);
        $mentions = $this->unreadMentions($me->id);

        $others = DB::table('conversation_participants')
            ->whereIn('conversation_id', $memberships->filter(fn (Participant $p): bool => ! $p->conversation->isGroup())->pluck('conversation_id')->all() ?: [0])
            ->where('user_id', '!=', $me->id)
            ->pluck('user_id', 'conversation_id');
        $sizes = DB::table('conversation_participants')
            ->whereIn('conversation_id', $memberships->filter(fn (Participant $p): bool => $p->conversation->isGroup())->pluck('conversation_id')->all() ?: [0])
            ->whereNull('left_at')
            ->groupBy('conversation_id')
            ->selectRaw('conversation_id, COUNT(*) AS n')
            ->pluck('n', 'conversation_id');
        $presence = Signals::presence($others->values()->map(fn (mixed $id): int => (int) $id)->unique()->values()->all());

        return $memberships->map(function (Participant $p) use ($last, $lastFiles, $unread, $mentions, $others, $sizes, $presence): array {
            $conversation = $p->conversation;
            $peer = $conversation->isGroup() ? null : (int) ($others[$conversation->id] ?? 0);
            $message = $conversation->last_message_id !== null && (int) $conversation->last_message_id > $p->floorMessageId()
                ? $last->get($conversation->last_message_id)
                : null;

            return [
                'id' => $conversation->id,
                'type' => $conversation->type->value,
                'name' => $conversation->isGroup() ? (string) $conversation->name : $this->directory->name($peer),
                'peer' => $peer,
                'members' => $conversation->isGroup() ? (int) ($sizes[$conversation->id] ?? 0) : 2,
                'avatar' => $conversation->avatar_path !== null ? route('messaging.conversations.avatar', ['conversation' => $conversation->id, 'v' => substr(md5($conversation->avatar_path), 0, 8)]) : null,
                'last' => $message instanceof Message ? [
                    'id' => $message->id,
                    'user' => $message->user_id,
                    'type' => $message->type->value,
                    'text' => MessagePresenter::summary($message, $lastFiles->get($message->id)?->first()),
                    'priority' => $message->isUnsent() ? 0 : $message->priority->value,
                ] : null,
                'at' => ($conversation->last_message_at ?? $conversation->created_at)->toIso8601String(),
                'unread' => (int) ($unread[$conversation->id] ?? 0),
                'mention' => isset($mentions[$conversation->id]),
                'pinned' => $p->pinned_at !== null,
                'pin_order' => $p->pinned_at !== null ? (int) $p->pin_order : null,
                'level' => $p->effectiveLevel()->value,
                'kind' => $conversation->isChannel() ? $conversation->groupSettings()->kind : null,
                'readonly' => $conversation->isChannel() && ($conversation->isArchived() || ! $conversation->groupSettings()->post->allows($p->role)),
                'closed' => $conversation->isArchived(),
                'archived' => $p->archived_at !== null,
                'favorite' => $p->favorite_at !== null,
                'muted' => $p->isMuted(),
                'admin' => $p->isAdmin(),
                'online' => $peer !== null && ($presence[$peer]['online'] ?? false),
                'presence' => $peer !== null ? ($presence[$peer]['state'] ?? 'offline') : null,
                'inactive' => $peer !== null && ! $this->directory->isActive($peer),
            ];
        })->all();
    }

    /** The badge: unread messages of the conversations that are not muted. */
    public function unreadTotal(User $me): int
    {
        $muted = Participant::query()->where('user_id', $me->id)->whereNull('left_at')
            ->where(fn ($query) => $query->where('muted_until', '>', now())->orWhere('notification_level', 'none'))
            ->pluck('conversation_id')->flip();
        $total = 0;
        foreach ($this->unreadCounts($me->id) as $conversationId => $count) {
            if (! $muted->has($conversationId)) {
                $total += $count;
            }
        }

        return $total;
    }

    /**
     * The newest unread message for a toast (who and where; the text only when the user lets previews show).
     *
     * @return array{conversation: int, message: int, user: int|null, name: string, group: string|null, text: string, muted: bool, level: string, priority: int}|null
     */
    public function newest(User $me): ?array
    {
        $row = DB::table('messages')
            ->join('conversation_participants as p', function ($join) use ($me): void {
                $join->on('p.conversation_id', '=', 'messages.conversation_id')->where('p.user_id', '=', $me->id)->whereNull('p.left_at');
            })
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->whereColumn('messages.id', '=', 'conversations.last_message_id')
            ->whereRaw('messages.id > COALESCE(p.last_read_message_id, 0)')
            ->where('messages.user_id', '!=', $me->id)
            ->whereNull('messages.unsent_at')
            ->where('messages.type', '!=', MessageType::System->value)
            ->orderByDesc('messages.id')
            ->first(['messages.id', 'messages.conversation_id', 'messages.user_id', 'messages.priority', 'conversations.type', 'conversations.name', 'p.muted_until', 'p.notification_level']);
        if ($row === null) {
            return null;
        }

        $message = Message::query()->find($row->id);

        return [
            'conversation' => (int) $row->conversation_id,
            'message' => (int) $row->id,
            'user' => $row->user_id !== null ? (int) $row->user_id : null,
            'name' => $this->directory->name($row->user_id !== null ? (int) $row->user_id : null),
            'group' => $row->type !== 'direct' ? (string) $row->name : null,
            'text' => $message instanceof Message ? MessagePresenter::summary($message, $message->attachments()->first(), 80) : '',
            'muted' => ($row->muted_until !== null && now()->lt($row->muted_until)) || $row->notification_level === 'none',
            'level' => $row->notification_level,
            'priority' => (int) $row->priority,
        ];
    }

    /** @return array<int, int> conversation id => unread messages from others */
    public function unreadCounts(int $userId): array
    {
        return DB::table('messages')
            ->join('conversation_participants as p', function ($join) use ($userId): void {
                $join->on('p.conversation_id', '=', 'messages.conversation_id')->where('p.user_id', '=', $userId)->whereNull('p.left_at');
            })
            ->whereRaw('messages.id > COALESCE(p.last_read_message_id, 0)')
            ->whereRaw('messages.id > COALESCE(p.visible_from_message_id, 0)')
            ->whereRaw('messages.id > COALESCE(p.cleared_message_id, 0)')
            ->where('messages.user_id', '!=', $userId)
            ->whereNull('messages.unsent_at')
            ->groupBy('messages.conversation_id')
            ->selectRaw('messages.conversation_id, COUNT(*) AS n')
            ->pluck('n', 'conversation_id')
            ->map(fn (mixed $n): int => (int) $n)
            ->all();
    }

    /** @return array<int, bool> conversations with an unread message mentioning the user (true) */
    private function unreadMentions(int $userId): array
    {
        /** @var Collection<int, mixed> $ids */
        $ids = DB::table('message_mentions')
            ->join('conversation_participants as p', function ($join) use ($userId): void {
                $join->on('p.conversation_id', '=', 'message_mentions.conversation_id')->where('p.user_id', '=', $userId)->whereNull('p.left_at');
            })
            ->where('message_mentions.user_id', $userId)
            ->whereRaw('message_mentions.message_id > COALESCE(p.last_read_message_id, 0)')
            ->distinct()
            ->pluck('message_mentions.conversation_id');

        return $ids->mapWithKeys(fn (mixed $id): array => [(int) $id => true])->all();
    }
}
