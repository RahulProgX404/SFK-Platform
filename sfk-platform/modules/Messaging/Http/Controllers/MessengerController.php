<?php

declare(strict_types=1);

namespace Modules\Messaging\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Records\RecordCards;
use Modules\Messaging\Application\Announcements;
use Modules\Messaging\Application\AttachmentStore;
use Modules\Messaging\Application\Conversations;
use Modules\Messaging\Application\Directory;
use Modules\Messaging\Application\Inbox;
use Modules\Messaging\Application\MessageFeed;
use Modules\Messaging\Application\MessagePresenter;
use Modules\Messaging\Application\Messages;
use Modules\Messaging\Application\MessageSearch;
use Modules\Messaging\Application\MessagingAccess;
use Modules\Messaging\Application\Preferences;
use Modules\Messaging\Application\Signals;
use Modules\Messaging\Application\Stickers;
use Modules\Messaging\Domain\ParticipantRole;
use Modules\Messaging\Http\MessengerPulse;
use Modules\Messaging\Models\Conversation;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The messenger's JSON API (/messaging/api/…, signed-in users, CSRF-checked, rate-limited where it writes). Thin: it
 * reads the request, asks MessagingAccess for the membership, calls the Application service and answers JSON.
 * Every conversation / message id in the address is checked against the signed-in user (404 when not theirs).
 */
final class MessengerController
{
    public function __construct(
        private readonly MessagingAccess $access,
        private readonly Inbox $inbox,
        private readonly MessageFeed $feed,
        private readonly Messages $messages,
        private readonly Conversations $conversations,
        private readonly MessagePresenter $presenter,
    ) {}

    public function inbox(Request $request, Announcements $announcements): JsonResponse
    {
        $me = self::user($request);

        return response()->json(['conversations' => $this->inbox->list($me), 'announcements' => $announcements->unreadCount($me)]);
    }

    /**
     * The badge of every page (asked when the inbox pulse changed): unread messages + announcements, and the newest
     * unread message for a toast. Everything the user's devices fetched counts as delivered (配信済み).
     */
    public function unread(Request $request, Announcements $announcements, Preferences $preferences): JsonResponse
    {
        $me = self::user($request);
        $newest = $this->inbox->newest($me);
        $preference = $preferences->for($me);
        $this->markAllDelivered($me);

        if ($newest !== null && ! $preference->preview) {
            $newest['text'] = '';
        }

        return response()->json([
            'count' => $this->inbox->unreadTotal($me),
            'announcements' => $announcements->unreadCount($me),
            'newest' => $newest,
            'prefs' => Preferences::toArray($preference),
        ]);
    }

    public function direct(Request $request): JsonResponse
    {
        $me = self::user($request);
        $data = $request->validate(['user' => ['required', 'integer']]);
        $conversation = $this->conversations->direct($me, (int) $data['user']);
        $participant = $this->access->member($me, $conversation->id);
        if ($participant->hidden_at !== null || $participant->archived_at !== null) {
            $participant->forceFill(['archived_at' => null])->save();
            Signals::inboxesChanged([$me->id]);
        }

        return response()->json(['id' => $conversation->id]);
    }

    public function createGroup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'members' => ['array', 'max:500'],
            'members.*' => ['integer'],
        ]);
        $conversation = $this->conversations->createGroup(self::user($request), $data['name'], $data['members'] ?? [], $data['description'] ?? null);

        return response()->json(['id' => $conversation->id], 201);
    }

    /** Opening a conversation: it, the newest page (or the page around ?around=), and the members' read watermarks. */
    public function show(Request $request, int $conversation): JsonResponse
    {
        $me = self::user($request);
        $participant = $this->access->member($me, $conversation);
        $around = $request->integer('around');
        $page = $around > 0 ? $this->feed->around($me, $participant, $around) : $this->feed->page($me, $participant);

        return response()->json([
            'conversation' => $this->feed->conversation($participant->conversation, $participant),
            'messages' => $page['messages'],
            'more' => $page['more'],
            'newer' => $page['newer'] ?? false,
            'reads' => $this->feed->reads($participant),
            'pulse' => MessengerPulse::url($me->id, $conversation, $this->peerOf($participant->conversation, $me->id)),
            'now' => now()->toIso8601String(),
        ]);
    }

    public function history(Request $request, int $conversation): JsonResponse
    {
        $me = self::user($request);
        $participant = $this->access->member($me, $conversation);
        if ($request->filled('after')) {
            return response()->json($this->feed->after($me, $participant, $request->integer('after')));
        }

        return response()->json($this->feed->page($me, $participant, $request->filled('before') ? $request->integer('before') : null));
    }

    public function sync(Request $request, int $conversation): JsonResponse
    {
        $me = self::user($request);
        $participant = $this->access->member($me, $conversation);
        $since = null;
        if ($request->filled('since')) {
            try {
                $since = Carbon::parse((string) $request->query('since'));
            } catch (\Throwable) {
                $since = null;
            }
        }

        return response()->json($this->feed->sync($me, $participant, $request->integer('after'), $request->integer('from'), $since));
    }

    public function reads(Request $request, int $conversation): JsonResponse
    {
        return response()->json(['reads' => $this->feed->reads($this->access->member(self::user($request), $conversation))]);
    }

    public function details(Request $request, int $conversation): JsonResponse
    {
        $me = self::user($request);

        return response()->json($this->feed->details($me, $this->access->member($me, $conversation)));
    }

    public function shared(Request $request, int $conversation): JsonResponse
    {
        $participant = $this->access->member(self::user($request), $conversation);
        $kinds = $request->query('kind') === 'files' ? ['file', 'voice'] : ['image'];

        return response()->json(['items' => $this->feed->shared($participant, $kinds, 60, $request->filled('before') ? $request->integer('before') : null)]);
    }

    public function send(Request $request, int $conversation): JsonResponse
    {
        $me = self::user($request);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:'.((int) config('messaging.max_message_length', 5000) + 100)],
            'client_id' => ['nullable', 'uuid'],
            'reply_to_id' => ['nullable', 'integer'],
            'attachment_ids' => ['array', 'max:'.(int) config('messaging.attachments.max_files', 10)],
            'attachment_ids.*' => ['integer'],
            'sticker' => ['nullable', 'string', 'max:40'],
            'mentions' => ['array', 'max:500'],
            'mentions.*' => ['integer'],
            'mention_all' => ['boolean'],
            'priority' => ['nullable', 'integer', 'in:0,1,2'],
            'record' => ['nullable', 'array'],
            'record.app' => ['required_with:record', 'string', 'max:60'],
            'record.id' => ['required_with:record', 'string', 'max:40'],
        ]);
        $participant = $this->access->member($me, $conversation);
        $message = $this->messages->send($me, $participant, $data);
        Signals::typing($conversation, $me->id, '', false);

        return response()->json(['message' => $this->presenter->presentOne($message, $me)], 201);
    }

    public function read(Request $request, int $conversation): JsonResponse
    {
        $participant = $this->access->member(self::user($request), $conversation);
        $this->messages->markRead($participant, $request->integer('message'));

        return response()->json(['ok' => true]);
    }

    public function state(Request $request, int $conversation): JsonResponse
    {
        $data = $request->validate(['state' => ['required', 'in:pinned,archived,favorite,muted,hidden,level'], 'value' => ['nullable']]);
        $participant = $this->access->member(self::user($request), $conversation);
        $this->conversations->setState($participant, $data['state'], $data['value'] ?? null);

        return response()->json(['ok' => true]);
    }

    public function info(Request $request, int $conversation): JsonResponse
    {
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:100'], 'description' => ['sometimes', 'nullable', 'string', 'max:1000']]);
        $participant = $this->access->member(self::user($request), $conversation);
        $this->conversations->updateInfo($participant, $data);

        return response()->json(['ok' => true]);
    }

    public function avatar(Request $request, int $conversation): JsonResponse
    {
        $participant = $this->access->member(self::user($request), $conversation);
        $file = $request->file('avatar');
        $this->conversations->updateAvatar($participant, $file instanceof UploadedFile ? $file : null);

        return response()->json(['ok' => true]);
    }

    public function settings(Request $request, int $conversation): JsonResponse
    {
        $participant = $this->access->member(self::user($request), $conversation);
        $this->conversations->updateSettings($participant, $request->only(['add_members', 'edit_info', 'pin_messages', 'mention_all', 'share_history']));

        return response()->json(['ok' => true]);
    }

    public function addMembers(Request $request, int $conversation): JsonResponse
    {
        $data = $request->validate(['users' => ['required', 'array', 'max:500'], 'users.*' => ['integer']]);
        $participant = $this->access->member(self::user($request), $conversation);

        return response()->json(['added' => $this->conversations->addMembers($participant, $data['users'])]);
    }

    public function removeMember(Request $request, int $conversation, int $user): JsonResponse
    {
        $participant = $this->access->member(self::user($request), $conversation);
        $this->conversations->removeMember($participant, $user);

        return response()->json(['ok' => true]);
    }

    public function role(Request $request, int $conversation, int $user): JsonResponse
    {
        $data = $request->validate(['role' => ['required', 'in:admin,member']]);
        $participant = $this->access->member(self::user($request), $conversation);
        $this->conversations->setRole($participant, $user, ParticipantRole::from($data['role']));

        return response()->json(['ok' => true]);
    }

    public function leave(Request $request, int $conversation): JsonResponse
    {
        $this->conversations->leave($this->access->member(self::user($request), $conversation));

        return response()->json(['ok' => true]);
    }

    public function upload(Request $request, int $conversation, AttachmentStore $store): JsonResponse
    {
        $me = self::user($request);
        $participant = $this->access->member($me, $conversation);
        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            throw new HttpException(422, 'ファイルを選んでください。');
        }
        $attachment = $store->upload($me, $participant, $file, $request->boolean('voice'), $request->filled('duration') ? $request->integer('duration') : null);

        return response()->json(['attachment' => MessagePresenter::attachment($attachment)], 201);
    }

    public function discard(Request $request, int $attachment, AttachmentStore $store): JsonResponse
    {
        $store->discard(self::user($request), $attachment);

        return response()->json(['ok' => true]);
    }

    public function edit(Request $request, int $message): JsonResponse
    {
        $me = self::user($request);
        $data = $request->validate(['body' => ['nullable', 'string', 'max:'.((int) config('messaging.max_message_length', 5000) + 100)]]);

        return response()->json(['message' => $this->presenter->presentOne($this->messages->edit($me, $message, (string) ($data['body'] ?? '')), $me)]);
    }

    public function unsend(Request $request, int $message): JsonResponse
    {
        $me = self::user($request);

        return response()->json(['message' => $this->presenter->presentOne($this->messages->unsend($me, $message), $me)]);
    }

    public function hide(Request $request, int $message): JsonResponse
    {
        $this->messages->hideForMe(self::user($request), $message);

        return response()->json(['ok' => true]);
    }

    public function react(Request $request, int $message): JsonResponse
    {
        $me = self::user($request);
        $data = $request->validate(['emoji' => ['required', 'string', 'max:32']]);

        return response()->json(['message' => $this->presenter->presentOne($this->messages->react($me, $message, $data['emoji']), $me)]);
    }

    public function pin(Request $request, int $message): JsonResponse
    {
        $me = self::user($request);

        return response()->json(['message' => $this->presenter->presentOne($this->messages->pin($me, $message, $request->boolean('pin', true)), $me)]);
    }

    public function forward(Request $request, int $message): JsonResponse
    {
        $data = $request->validate(['conversations' => ['required', 'array', 'min:1', 'max:10'], 'conversations.*' => ['integer']]);
        $sent = $this->messages->forward(self::user($request), $message, $data['conversations']);

        return response()->json(['sent' => count($sent)]);
    }

    public function search(Request $request, MessageSearch $search): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'user' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'conversation' => ['nullable', 'integer'],
            'files' => ['nullable', 'boolean'],
            'kind' => ['nullable', 'in:'.implode(',', MessageSearch::KINDS)],
            'before' => ['nullable', 'integer'],
        ]);

        return response()->json($search->search(self::user($request), $data));
    }

    public function preferences(Request $request, Preferences $preferences): JsonResponse
    {
        $me = self::user($request);
        if ($request->isMethod('post')) {
            $preferences->update($me, $request->only(['sound', 'desktop', 'preview', 'presence', 'dnd_hours', 'status']));
        }

        return response()->json(['prefs' => Preferences::toArray($preferences->for($me))]);
    }

    public function announcements(Request $request, Announcements $announcements): JsonResponse
    {
        return response()->json(['announcements' => $announcements->listFor(self::user($request), 50, (string) $request->query('q', ''))]);
    }

    public function announcement(Request $request, int $announcement, Announcements $announcements): JsonResponse
    {
        return response()->json(['announcement' => $announcements->show(self::user($request), $announcement)]);
    }

    public function publish(Request $request, Announcements $announcements): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:20000'],
            'urgent' => ['boolean'],
            'priority' => ['nullable', 'in:normal,important,urgent'],
            'requires_confirmation' => ['boolean'],
            'audience' => ['required', 'in:all,categories,users,groups'],
            'expires_at' => ['nullable', 'date'],
            'audience_ids' => ['array'],
            'audience_ids.*' => ['integer'],
            'published_at' => ['nullable', 'date'],
            'files' => ['array', 'max:10'],
            'files.*' => ['file'],
        ]);
        $files = array_values(array_filter((array) $request->file('files', []), fn (mixed $file): bool => $file instanceof UploadedFile));
        $announcement = $announcements->publish(self::user($request), $data, $files);

        return response()->json(['id' => $announcement->id], 201);
    }

    public function confirm(Request $request, int $announcement, Announcements $announcements): JsonResponse
    {
        $announcements->confirm(self::user($request), $announcement);

        return response()->json(['ok' => true]);
    }

    public function addSticker(Request $request, Stickers $stickers): JsonResponse
    {
        $me = self::user($request);
        if (! $this->access->canManageStickers($me)) {
            throw new HttpException(403, 'スタンプを追加する権限がありません。');
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:50'], 'file' => ['required', 'file']]);
        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            throw new HttpException(422, 'ファイルを選んでください。');
        }
        $stickers->add($data['name'], $file, $me->id);

        return response()->json(['stickers' => $stickers->catalog()], 201);
    }

    public function removeSticker(Request $request, int $sticker, Stickers $stickers): JsonResponse
    {
        if (! $this->access->canManageStickers(self::user($request))) {
            throw new HttpException(403, 'スタンプを削除する権限がありません。');
        }
        $stickers->remove($sticker);

        return response()->json(['stickers' => $stickers->catalog()]);
    }

    public function createChannel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'kind' => ['required', 'in:department,project'],
            'public' => ['boolean'],
            'post' => ['required', 'in:everyone,admins'],
            'members' => ['array', 'max:500'],
            'members.*' => ['integer'],
            'categories' => ['array', 'max:50'],
            'categories.*' => ['integer'],
        ]);
        $channel = $this->conversations->createChannel(self::user($request), $data);

        return response()->json(['id' => $channel->id], 201);
    }

    /** チャンネルを探す: the public channels. */
    public function channels(Request $request): JsonResponse
    {
        return response()->json(['channels' => $this->conversations->publicChannels(self::user($request))]);
    }

    public function join(Request $request, int $channel): JsonResponse
    {
        return response()->json(['id' => $this->conversations->join(self::user($request), $channel)->id]);
    }

    public function archive(Request $request, int $channel): JsonResponse
    {
        $this->conversations->archiveChannel(self::user($request), $channel, $request->boolean('archive', true));

        return response()->json(['ok' => true]);
    }

    /** 未読にする. */
    public function unreadOne(Request $request, int $conversation): JsonResponse
    {
        $this->messages->markUnread($this->access->member(self::user($request), $conversation));

        return response()->json(['ok' => true]);
    }

    /** すべて既読にする. */
    public function readAll(Request $request): JsonResponse
    {
        return response()->json(['read' => $this->conversations->markAllRead(self::user($request))]);
    }

    /** The user's order of their pinned conversations. */
    public function pinOrder(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:300'], 'ids.*' => ['integer']]);
        $this->conversations->reorderPins(self::user($request), $data['ids']);

        return response()->json(['ok' => true]);
    }

    /** 日付へ移動: the messages from a day on. */
    public function date(Request $request, int $conversation): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date']]);
        $me = self::user($request);

        return response()->json($this->feed->aroundDate($me, $this->access->member($me, $conversation), Carbon::parse($data['date'])));
    }

    /** 共有リンク. */
    public function links(Request $request, int $conversation): JsonResponse
    {
        $participant = $this->access->member(self::user($request), $conversation);

        return response()->json($this->feed->links($participant, $request->filled('before') ? $request->integer('before') : null));
    }

    /** メッセージ情報. */
    public function messageInfo(Request $request, int $message): JsonResponse
    {
        $me = self::user($request);
        [$found, $participant] = $this->access->message($me, $message);

        return response()->json(['info' => $this->feed->info($me, $found, $participant)]);
    }

    /** A group's / channel's 管理者 removes a member's message. */
    public function moderate(Request $request, int $message): JsonResponse
    {
        $me = self::user($request);

        return response()->json(['message' => $this->presenter->presentOne($this->messages->moderate($me, $message), $me)]);
    }

    /** ERPレコードを共有: the apps whose records the user may share. */
    public function recordApps(Request $request): JsonResponse
    {
        return response()->json(['apps' => RecordCards::appsFor(self::user($request))]);
    }

    /** ERPレコードを共有: the user's records of an app, by words. */
    public function recordSearch(Request $request): JsonResponse
    {
        $data = $request->validate(['app' => ['required', 'string', 'max:60'], 'q' => ['nullable', 'string', 'max:100']]);

        return response()->json(['records' => RecordCards::search(self::user($request), $data['app'], (string) ($data['q'] ?? ''))]);
    }

    public function people(Directory $directory): JsonResponse
    {
        return response()->json(['people' => self::peopleFor($directory)]);
    }

    /**
     * The people the page knows (names for every message, the pickers): what a colleague's card shows, never the
     * private contact data.
     *
     * @return list<array{id: int, name: string, kana: string, position: string, categories: list<string>, active: bool}>
     */
    public static function peopleFor(Directory $directory): array
    {
        return array_values(array_map(fn (array $person): array => [
            'id' => $person['id'],
            'name' => $person['name'],
            'kana' => $person['kana'],
            'position' => $person['position'],
            'categories' => $person['categories'],
            'active' => $person['active'],
        ], $directory->all()));
    }

    private function peerOf(Conversation $conversation, int $me): int
    {
        if ($conversation->isGroup() || $conversation->direct_key === null) {
            return 0;
        }
        [$a, $b] = array_map('intval', explode(':', $conversation->direct_key) + [1 => 0]);

        return $a === $me ? $b : $a;
    }

    /** Everything the user's open pages fetched counts as delivered: one update over their conversations. */
    private function markAllDelivered(User $me): void
    {
        $pending = DB::table('conversation_participants as p')
            ->join('conversations as c', 'c.id', '=', 'p.conversation_id')
            ->where('p.user_id', $me->id)
            ->whereNull('p.left_at')
            ->whereRaw('c.last_message_id > COALESCE(p.last_delivered_message_id, 0)')
            ->pluck('p.conversation_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        if ($pending === []) {
            return;
        }

        DB::table('conversation_participants')
            ->where('user_id', $me->id)
            ->whereIn('conversation_id', $pending)
            ->update(['last_delivered_message_id' => DB::raw('(SELECT last_message_id FROM conversations WHERE conversations.id = conversation_participants.conversation_id)')]);
        foreach ($pending as $conversationId) {
            Signals::readChanged($conversationId);
        }
    }

    private static function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new HttpException(401);
        }

        return $user;
    }
}
