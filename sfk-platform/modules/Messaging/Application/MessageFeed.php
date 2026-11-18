<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\Message;
use Modules\Messaging\Models\MessageAttachment;
use Modules\Messaging\Models\MessagePin;
use Modules\Messaging\Models\Participant;

/**
 * The history of a conversation for one member, a page at a time by keyset over (conversation_id, id) — never the
 * whole conversation, never OFFSET: the newest page on opening, older pages while scrolling up, the messages around
 * one (a reply's quote, a search result), and the sync of an open chat (new messages after the newest it has, and the
 * ones changed since it last asked — edits, reactions, pins, 送信取消).
 * Only what the member may see: after their floor, without what they hid (自分のみ削除).
 */
final class MessageFeed
{
    public const PAGE = 40;

    public function __construct(
        private readonly MessagingAccess $access,
        private readonly MessagePresenter $presenter,
        private readonly Directory $directory,
        private readonly Messages $messages,
    ) {}

    /**
     * The newest page, or the page before a message.
     *
     * @return array{messages: list<array<string, mixed>>, more: bool}
     */
    public function page(User $me, Participant $participant, ?int $before = null): array
    {
        $rows = $this->visible($me, $participant)
            ->when($before !== null, fn (Builder $query) => $query->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit(self::PAGE + 1)
            ->get();

        $more = $rows->count() > self::PAGE;
        $rows = $rows->take(self::PAGE)->reverse()->values();
        if ($before === null && $rows->isNotEmpty()) {
            $this->messages->markDelivered($participant, (int) $rows->last()->id);
        }

        return ['messages' => $this->presenter->present($rows, $me), 'more' => $more];
    }

    /**
     * The messages around one (half before, half after), for a jump to it.
     *
     * @return array{messages: list<array<string, mixed>>, more: bool, newer: bool}
     */
    public function around(User $me, Participant $participant, int $messageId): array
    {
        $half = intdiv(self::PAGE, 2);
        $older = $this->visible($me, $participant)->where('id', '<=', $messageId)->orderByDesc('id')->limit($half + 1)->get();
        $newer = $this->visible($me, $participant)->where('id', '>', $messageId)->orderBy('id')->limit($half + 1)->get();

        $more = $older->count() > $half;
        $hasNewer = $newer->count() > $half;
        $rows = $older->take($half)->reverse()->values()->concat($newer->take($half))->values();

        return ['messages' => $this->presenter->present($rows, $me), 'more' => $more, 'newer' => $hasNewer];
    }

    /**
     * The messages after one, oldest first (a jump's newer side while scrolling down).
     *
     * @return array{messages: list<array<string, mixed>>, newer: bool}
     */
    public function after(User $me, Participant $participant, int $afterId): array
    {
        $rows = $this->visible($me, $participant)->where('id', '>', $afterId)->orderBy('id')->limit(self::PAGE + 1)->get();

        return ['messages' => $this->presenter->present($rows->take(self::PAGE), $me), 'newer' => $rows->count() > self::PAGE];
    }

    /**
     * What changed in an open chat: messages after `after`, and those from `from` on that changed since `since`. Marks
     * the new ones delivered.
     *
     * @return array{messages: list<array<string, mixed>>, changed: list<array<string, mixed>>, now: string, gap: bool}
     */
    public function sync(User $me, Participant $participant, int $after, int $from, ?Carbon $since): array
    {
        $now = now();
        $new = $this->visible($me, $participant)->where('id', '>', $after)->orderBy('id')->limit(self::PAGE + 1)->get();
        // too many to fetch: the chat reloads its newest page
        $gap = $new->count() > self::PAGE;
        $new = $new->take(self::PAGE);

        $changed = collect();
        if ($since !== null && $from > 0 && $from <= $after) {
            // a second of overlap: a change written while the last sync ran is never missed
            $changed = Message::query()
                ->where('conversation_id', $participant->conversation_id)
                ->whereBetween('id', [$from, $after])
                ->where('updated_at', '>=', $since->copy()->subSecond())
                ->orderBy('id')
                ->limit(200)
                ->get();
        }

        if ($new->isNotEmpty()) {
            $this->messages->markDelivered($participant, (int) $new->last()->id);
        }

        return [
            'messages' => $this->presenter->present($new, $me),
            'changed' => $this->presenter->present($changed, $me),
            'now' => $now->toIso8601String(),
            'gap' => $gap,
        ];
    }

    /**
     * The messages from a day on (日付へ移動): the first visible message of that day or after, with the ones around it.
     *
     * @return array{messages: list<array<string, mixed>>, more: bool, newer: bool, target: int|null}
     */
    public function aroundDate(User $me, Participant $participant, Carbon $day): array
    {
        $target = $this->visible($me, $participant)->where('created_at', '>=', $day->copy()->startOfDay())->orderBy('id')->value('id')
            ?? $this->visible($me, $participant)->orderByDesc('id')->value('id');
        if ($target === null) {
            return ['messages' => [], 'more' => false, 'newer' => false, 'target' => null];
        }

        return [...$this->around($me, $participant, (int) $target), 'target' => (int) $target];
    }

    /**
     * Links shared in the conversation (共有リンク), newest first, keyset `before` (message id).
     *
     * @return array{items: list<array{url: string, host: string, message: int, user: int|null, at: string}>, more: bool}
     */
    public function links(Participant $participant, ?int $before = null): array
    {
        $rows = Message::query()
            ->where('conversation_id', $participant->conversation_id)
            ->where('id', '>', $participant->floorMessageId())
            ->whereNull('unsent_at')
            ->where(fn (Builder $query) => $query->where('body', 'like', '%http://%')->orWhere('body', 'like', '%https://%'))
            ->when($before !== null, fn (Builder $query) => $query->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit(31)
            ->get(['id', 'user_id', 'body', 'created_at']);

        $items = [];
        foreach ($rows->take(30) as $message) {
            preg_match_all('#https?://[^\s<>"\']+#u', (string) $message->body, $found);
            foreach (array_unique($found[0]) as $url) {
                $url = rtrim($url, '.,);:!?。、');
                $host = (string) parse_url($url, PHP_URL_HOST);
                if ($host !== '') {
                    $items[] = ['url' => $url, 'host' => $host, 'message' => $message->id, 'user' => $message->user_id, 'at' => $message->created_at->toIso8601String()];
                }
            }
        }

        return ['items' => $items, 'more' => $rows->count() > 30];
    }

    /**
     * メッセージ情報: when it was sent and changed, and — for the sender only — who received and read it (the members'
     * watermarks; not shown when the company switched 既読 off).
     *
     * @return array<string, mixed>
     */
    public function info(User $me, Message $message, Participant $participant): array
    {
        $info = [
            'id' => $message->id,
            'user' => $message->user_id,
            'at' => $message->created_at->toIso8601String(),
            'edited' => $message->edited_at?->toIso8601String(),
            'edits' => $message->edits()->where('action', 'edit')->count(),
            'priority' => $message->priority->value,
            'mine' => $message->user_id === $me->id,
            'read' => null,
            'delivered' => null,
            'waiting' => null,
        ];

        if ($message->user_id === $me->id) {
            $others = Participant::query()->where('conversation_id', $participant->conversation_id)->whereNull('left_at')->where('user_id', '!=', $me->id)
                ->get(['user_id', 'last_read_message_id', 'last_read_at', 'last_delivered_message_id']);
            $receipts = (bool) config('messaging.read_receipts', true);
            $info['read'] = $receipts ? $others->filter(fn (Participant $p): bool => (int) $p->last_read_message_id >= $message->id)->pluck('user_id')->values()->all() : [];
            $info['delivered'] = $others->filter(fn (Participant $p): bool => (int) $p->last_delivered_message_id >= $message->id && (! $receipts || (int) $p->last_read_message_id < $message->id))->pluck('user_id')->values()->all();
            $info['waiting'] = $others->filter(fn (Participant $p): bool => (int) $p->last_delivered_message_id < $message->id)->pluck('user_id')->values()->all();
        }

        return $info;
    }

    /**
     * Where the members are: their read / delivered watermarks (the read marks are counted from them) and their role.
     *
     * @return list<array{user: int, read: int, delivered: int, role: string}>
     */
    public function reads(Participant $participant): array
    {
        $receipts = (bool) config('messaging.read_receipts', true);

        return Participant::query()
            ->where('conversation_id', $participant->conversation_id)
            ->whereNull('left_at')
            ->orderBy('joined_at')
            ->orderBy('id')
            ->get(['user_id', 'last_read_message_id', 'last_delivered_message_id', 'role'])
            ->map(fn (Participant $member): array => [
                'user' => $member->user_id,
                // 既読 switched off by the company: only one's own position is told
                'read' => $receipts || $member->user_id === $participant->user_id ? (int) $member->last_read_message_id : 0,
                'delivered' => (int) $member->last_delivered_message_id,
                'role' => $member->role->value,
            ])->all();
    }

    /**
     * The details panel: the conversation, its pinned messages and the newest shared media / files.
     *
     * @return array<string, mixed>
     */
    public function details(User $me, Participant $participant): array
    {
        $conversation = $participant->conversation;
        $floor = $participant->floorMessageId();
        $pins = MessagePin::query()
            ->where('message_pins.conversation_id', $conversation->id)
            ->join('messages', 'messages.id', '=', 'message_pins.message_id')
            ->where('messages.id', '>', $floor)
            ->orderByDesc('message_pins.id')
            ->limit(50)
            ->pluck('messages.id')
            ->all();

        return [
            'conversation' => $this->conversation($conversation, $participant),
            'pins' => $this->presenter->present(Message::query()->whereIn('id', $pins ?: [0])->orderByDesc('id')->get(), $me),
            'media' => $this->shared($participant, ['image'], 30),
            'files' => $this->shared($participant, ['file', 'voice'], 30),
        ];
    }

    /**
     * Shared images or files (newest first, keyset `before`).
     *
     * @param  list<string>  $kinds
     * @return list<array<string, mixed>>
     */
    public function shared(Participant $participant, array $kinds, int $limit, ?int $before = null): array
    {
        return MessageAttachment::query()
            ->where('message_attachments.conversation_id', $participant->conversation_id)
            ->whereIn('message_attachments.kind', $kinds)
            ->whereNotNull('message_attachments.message_id')
            ->where('message_attachments.message_id', '>', $participant->floorMessageId())
            ->when($before !== null, fn (Builder $query) => $query->where('message_attachments.id', '<', $before))
            ->join('messages', 'messages.id', '=', 'message_attachments.message_id')
            ->whereNull('messages.unsent_at')
            ->orderByDesc('message_attachments.id')
            ->limit($limit)
            ->get(['message_attachments.*', 'messages.user_id as sender_id', 'messages.created_at as sent_at'])
            ->map(fn (MessageAttachment $attachment): array => [
                ...MessagePresenter::attachment($attachment),
                'message' => $attachment->message_id,
                'user' => $attachment->getAttribute('sender_id'),
                'at' => Carbon::parse((string) $attachment->getAttribute('sent_at'))->toIso8601String(),
            ])->all();
    }

    /** @return array<string, mixed> */
    public function conversation(Conversation $conversation, Participant $participant): array
    {
        $settings = $conversation->groupSettings();

        return [
            'id' => $conversation->id,
            'type' => $conversation->type->value,
            'name' => $conversation->name,
            'description' => $conversation->description,
            'avatar' => $conversation->avatar_path !== null ? route('messaging.conversations.avatar', ['conversation' => $conversation->id, 'v' => substr(md5($conversation->avatar_path), 0, 8)]) : null,
            'settings' => $conversation->isGroup() ? $settings->toArray() : null,
            'created_by' => $conversation->created_by,
            'me' => [
                'role' => $participant->role->value,
                'pinned' => $participant->pinned_at !== null,
                'archived' => $participant->archived_at !== null,
                'favorite' => $participant->favorite_at !== null,
                'muted' => $participant->isMuted(),
                'muted_until' => $participant->muted_until?->isFuture() ? $participant->muted_until->toIso8601String() : null,
                'level' => $participant->notification_level->value,
                'floor' => $participant->floorMessageId(),
                'post' => $this->access->canPost($participant),
                'moderate' => $this->access->canModerate($participant),
            ],
            'archived' => $conversation->isArchived(),
            'profile' => $conversation->isGroup() ? null : $this->profile($conversation, $participant),
        ];
    }

    /** @return array<string, mixed>|null the other person of a direct conversation, with the fields company policy shows */
    private function profile(Conversation $conversation, Participant $participant): ?array
    {
        $peer = (int) $conversation->participants()->where('user_id', '!=', $participant->user_id)->value('user_id');
        $person = $this->directory->find($peer);
        if ($person === null) {
            return null;
        }

        $fields = (array) config('messaging.profile_fields', []);

        return array_filter([
            'user' => $peer,
            'name' => $person['name'],
            'kana' => $person['kana'],
            'position' => in_array('position', $fields, true) ? $person['position'] : null,
            'categories' => in_array('categories', $fields, true) ? $person['categories'] : null,
            'email' => in_array('email', $fields, true) ? $person['email'] : null,
            'phone' => in_array('phone', $fields, true) ? $person['phone'] : null,
            'active' => $person['active'],
        ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /** @return Builder<Message> */
    private function visible(User $me, Participant $participant): Builder
    {
        return Message::query()
            ->where('conversation_id', $participant->conversation_id)
            ->where('id', '>', $participant->floorMessageId())
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('message_hides')
                ->whereColumn('message_hides.message_id', 'messages.id')
                ->where('message_hides.user_id', $me->id));
    }
}
