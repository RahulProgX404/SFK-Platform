<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Files\Uploads;
use Modules\Messaging\Domain\ConversationType;
use Modules\Messaging\Domain\DirectKey;
use Modules\Messaging\Domain\GroupRule;
use Modules\Messaging\Domain\GroupSettings;
use Modules\Messaging\Domain\MessageType;
use Modules\Messaging\Domain\NotificationLevel;
use Modules\Messaging\Domain\ParticipantRole;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\Message;
use Modules\Messaging\Models\Participant;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Starting conversations and managing them: the one direct conversation of two users, groups and channels (name,
 * image, description, settings, members and their 管理者), leaving, and each member's own state of a conversation
 * (ピン留め and its order, アーカイブ, お気に入り, 通知 level / オフ, 「トークを削除」). Changes leave a system line in the
 * conversation; what a 管理者 does to others (members, roles, archive) is also written to the audit (MessagingAudit).
 *
 * Channels (部署・プロジェクト): created by who company policy allows (チャンネルを作成できる人), public (anyone finds and
 * joins them) or invite-only, optionally read-only (only 管理者 post); an archived channel stays readable for its members
 * but takes no new messages.
 *
 * Membership rules (docs/messaging.md "History"): a member who leaves or is removed loses the conversation entirely —
 * its messages and files are no longer theirs to read, new or old. A member added later reads the history from before
 * they joined when the group shares it (GroupSettings::shareHistory, default), else only what comes after.
 */
final class Conversations
{
    public function __construct(
        private readonly MessagingAccess $access,
        private readonly Directory $directory,
    ) {}

    /** The direct conversation of the two users, started when there is none yet; a hidden one shows again. */
    public function direct(User $me, int $otherId): Conversation
    {
        if ($otherId === $me->id || ! $this->directory->isActive($otherId)) {
            throw ValidationException::withMessages(['user' => 'このユーザーとはトークできません。']);
        }

        $key = DirectKey::for($me->id, $otherId);
        $existing = Conversation::query()->where('direct_key', $key)->first();
        if ($existing instanceof Conversation) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($me, $otherId, $key): Conversation {
                $conversation = Conversation::query()->create([
                    'type' => ConversationType::Direct,
                    'direct_key' => $key,
                    'created_by' => $me->id,
                ]);
                foreach ([$me->id, $otherId] as $userId) {
                    $conversation->participants()->create([
                        'user_id' => $userId,
                        'role' => ParticipantRole::Member,
                        'added_by' => $me->id,
                        'joined_at' => now(),
                    ]);
                }

                return $conversation;
            });
        } catch (QueryException) {
            // both started it at the same moment: the other request created it
            return Conversation::query()->where('direct_key', $key)->firstOrFail();
        }
    }

    /**
     * A new group with its creator as 管理者.
     *
     * @param  list<int>  $memberIds
     */
    public function createGroup(User $me, string $name, array $memberIds, ?string $description = null): Conversation
    {
        if (! $this->access->canCreateGroups($me)) {
            throw new HttpException(403, 'グループを作成する権限がありません。');
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['name' => 'グループ名を100文字以内で入力してください。']);
        }

        $memberIds = $this->addableIds($memberIds, exclude: [$me->id]);
        if (count($memberIds) + 1 > (int) config('messaging.max_group_members', 500)) {
            throw ValidationException::withMessages(['members' => 'メンバーが多すぎます。']);
        }

        return DB::transaction(function () use ($me, $name, $memberIds, $description): Conversation {
            $conversation = Conversation::query()->create([
                'type' => ConversationType::Group,
                'name' => $name,
                'description' => filled($description) ? Str::limit(trim((string) $description), 1000, '') : null,
                'settings' => (new GroupSettings)->toArray(),
                'created_by' => $me->id,
            ]);

            $conversation->participants()->create(['user_id' => $me->id, 'role' => ParticipantRole::Admin, 'added_by' => $me->id, 'joined_at' => now()]);
            foreach ($memberIds as $id) {
                $conversation->participants()->create(['user_id' => $id, 'role' => ParticipantRole::Member, 'added_by' => $me->id, 'joined_at' => now()]);
            }

            $this->systemLine($conversation, 'created', $me->id, ['name' => $name]);

            return $conversation;
        });
    }

    /**
     * A new channel (部署 / プロジェクト) with its creator as 管理者; members by name and the current employees of chosen
     * 従業員カテゴリ.
     *
     * @param  array{name?: string, description?: string|null, kind?: string, public?: bool, post?: string, members?: list<int>, categories?: list<int>}  $input
     */
    public function createChannel(User $me, array $input): Conversation
    {
        if (! $this->access->canCreateChannels($me)) {
            throw new HttpException(403, 'チャンネルを作成する権限がありません。');
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['name' => 'チャンネル名を100文字以内で入力してください。']);
        }

        $categoryIds = array_map('intval', (array) ($input['categories'] ?? []));
        $fromCategories = array_map(fn (array $person): int => $person['id'], array_filter($this->directory->active(), fn (array $person): bool => array_intersect($person['category_ids'], $categoryIds) !== []));
        $memberIds = $this->addableIds([...array_map('intval', (array) ($input['members'] ?? [])), ...$fromCategories], exclude: [$me->id]);
        if (count($memberIds) + 1 > (int) config('messaging.max_group_members', 500)) {
            throw ValidationException::withMessages(['members' => 'メンバーが多すぎます。']);
        }

        $settings = new GroupSettings(
            addMembers: GroupRule::Admins,
            editInfo: GroupRule::Admins,
            pinMessages: GroupRule::Admins,
            mentionAll: GroupRule::Admins,
            shareHistory: true,
            post: GroupRule::tryFrom((string) ($input['post'] ?? '')) ?? GroupRule::Everyone,
            public: (bool) ($input['public'] ?? true),
            kind: ($input['kind'] ?? '') === 'department' ? 'department' : 'project',
        );

        return DB::transaction(function () use ($me, $name, $input, $settings, $memberIds, $categoryIds): Conversation {
            $conversation = Conversation::query()->create([
                'type' => ConversationType::Channel,
                'name' => $name,
                'description' => filled($input['description'] ?? null) ? Str::limit(trim((string) $input['description']), 1000, '') : null,
                'settings' => $settings->toArray(),
                'created_by' => $me->id,
            ]);

            $conversation->participants()->create(['user_id' => $me->id, 'role' => ParticipantRole::Admin, 'added_by' => $me->id, 'joined_at' => now()]);
            foreach ($memberIds as $id) {
                $conversation->participants()->create(['user_id' => $id, 'role' => ParticipantRole::Member, 'added_by' => $me->id, 'joined_at' => now()]);
            }

            $this->systemLine($conversation, 'channel_created', $me->id, ['name' => $name]);
            MessagingAudit::record($me, 'channel.created', $conversation->id, ['name' => $name, 'members' => count($memberIds) + 1, 'categories' => $categoryIds]);

            return $conversation;
        });
    }

    /**
     * Public channels anyone may find (not archived), with their size and whether the user is in them.
     *
     * @return list<array{id: int, name: string, description: string|null, kind: string, members: int, joined: bool, readonly: bool}>
     */
    public function publicChannels(User $me): array
    {
        $channels = Conversation::query()
            ->where('type', ConversationType::Channel->value)
            ->whereNull('archived_at')
            ->withCount('members')
            ->orderBy('name')
            ->limit(300)
            ->get()
            ->filter(fn (Conversation $channel): bool => $channel->groupSettings()->public);
        $mine = Participant::query()->where('user_id', $me->id)->whereNull('left_at')->whereIn('conversation_id', $channels->pluck('id')->all() ?: [0])->pluck('conversation_id')->flip();

        return $channels->map(fn (Conversation $channel): array => [
            'id' => $channel->id,
            'name' => (string) $channel->name,
            'description' => $channel->description,
            'kind' => $channel->groupSettings()->kind,
            'members' => (int) $channel->getAttribute('members_count'),
            'joined' => $mine->has($channel->id),
            'readonly' => $channel->groupSettings()->post === GroupRule::Admins,
        ])->values()->all();
    }

    /** Joins a public channel (again, after leaving it). */
    public function join(User $me, int $channelId): Conversation
    {
        $channel = Conversation::query()->where('type', ConversationType::Channel->value)->whereNull('archived_at')->find($channelId);
        if (! $channel instanceof Conversation || ! $channel->groupSettings()->public) {
            throw new NotFoundHttpException;
        }
        if ($channel->members()->where('user_id', $me->id)->exists()) {
            return $channel;
        }

        DB::transaction(function () use ($me, $channel): void {
            $from = $channel->groupSettings()->shareHistory ? null : $channel->last_message_id;
            Participant::query()->updateOrCreate(
                ['conversation_id' => $channel->id, 'user_id' => $me->id],
                [
                    'role' => ParticipantRole::Member, 'added_by' => $me->id, 'joined_at' => now(), 'left_at' => null, 'removed_by' => null,
                    'visible_from_message_id' => $from, 'cleared_message_id' => null, 'hidden_at' => null, 'pinned_at' => null,
                    'archived_at' => null, 'favorite_at' => null, 'muted_until' => null, 'notification_level' => NotificationLevel::All,
                    'last_read_message_id' => $channel->last_message_id, 'last_delivered_message_id' => $channel->last_message_id,
                ],
            );
            $this->systemLine($channel, 'joined', $me->id, []);
        });

        return $channel;
    }

    /**
     * Archives (or reopens) a channel: by its 管理者, or by a company administrator from メッセージ管理. Archived, the
     * members still read it but nobody posts.
     */
    public function archiveChannel(User $actor, int $channelId, bool $archive): Conversation
    {
        $channel = Conversation::query()->where('type', ConversationType::Channel->value)->find($channelId);
        if (! $channel instanceof Conversation) {
            throw new NotFoundHttpException;
        }
        $isAdmin = $channel->members()->where('user_id', $actor->id)->where('role', ParticipantRole::Admin->value)->exists();
        if (! $isAdmin && ! $actor->user_type->isAdministrator()) {
            throw new HttpException(403, 'チャンネルの管理者のみ操作できます。');
        }
        if ($channel->isArchived() === $archive) {
            return $channel;
        }

        DB::transaction(function () use ($actor, $channel, $archive): void {
            $channel->forceFill(['archived_at' => $archive ? now() : null])->save();
            $this->systemLine($channel, $archive ? 'archived' : 'restored', $actor->id, []);
            MessagingAudit::record($actor, $archive ? 'channel.archived' : 'channel.restored', $channel->id, ['name' => $channel->name]);
        });

        return $channel;
    }

    /** All the user's conversations read up to their newest message (すべて既読にする). */
    public function markAllRead(User $me): int
    {
        $pending = DB::table('conversation_participants as p')
            ->join('conversations as c', 'c.id', '=', 'p.conversation_id')
            ->where('p.user_id', $me->id)
            ->whereNull('p.left_at')
            ->whereRaw('c.last_message_id > COALESCE(p.last_read_message_id, 0)')
            ->pluck('p.conversation_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        if ($pending === []) {
            return 0;
        }

        DB::table('conversation_participants')
            ->where('user_id', $me->id)
            ->whereIn('conversation_id', $pending)
            ->update([
                'last_read_message_id' => DB::raw('(SELECT last_message_id FROM conversations WHERE conversations.id = conversation_participants.conversation_id)'),
                'last_delivered_message_id' => DB::raw('(SELECT last_message_id FROM conversations WHERE conversations.id = conversation_participants.conversation_id)'),
                'last_read_at' => now(),
            ]);
        foreach ($pending as $conversationId) {
            Signals::readChanged($conversationId);
        }
        Signals::inboxesChanged([$me->id]);

        return count($pending);
    }

    /**
     * The user's own order of their pinned conversations (drag in the list): the ids top first.
     *
     * @param  list<int>  $conversationIds
     */
    public function reorderPins(User $me, array $conversationIds): void
    {
        DB::transaction(function () use ($me, $conversationIds): void {
            foreach (array_values(array_unique(array_map('intval', $conversationIds))) as $position => $id) {
                Participant::query()->where('user_id', $me->id)->where('conversation_id', $id)->whereNull('left_at')->whereNotNull('pinned_at')
                    ->update(['pin_order' => $position + 1]);
            }
        });
        Signals::inboxesChanged([$me->id]);
    }

    /** @param  array{name?: string|null, description?: string|null}  $info */
    public function updateInfo(Participant $actor, array $info): Conversation
    {
        $conversation = $this->access->requireGroup($actor);
        $this->access->requireRule($actor, $conversation->groupSettings()->editInfo);

        return DB::transaction(function () use ($actor, $conversation, $info): Conversation {
            if (array_key_exists('name', $info)) {
                $name = trim((string) $info['name']);
                if ($name === '' || mb_strlen($name) > 100) {
                    throw ValidationException::withMessages(['name' => 'グループ名を100文字以内で入力してください。']);
                }
                if ($name !== $conversation->name) {
                    $conversation->name = $name;
                    $this->systemLine($conversation, 'renamed', $actor->user_id, ['name' => $name]);
                }
            }
            if (array_key_exists('description', $info)) {
                $conversation->description = filled($info['description']) ? Str::limit(trim((string) $info['description']), 1000, '') : null;
            }
            $conversation->save();
            $this->changed($conversation);

            return $conversation;
        });
    }

    public function updateAvatar(Participant $actor, ?UploadedFile $file): Conversation
    {
        $conversation = $this->access->requireGroup($actor);
        $this->access->requireRule($actor, $conversation->groupSettings()->editInfo);

        $old = $conversation->avatar_path;
        if ($file !== null) {
            $info = @getimagesize((string) $file->getRealPath());
            if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true) || $file->getSize() > 5 * 1024 * 1024) {
                throw ValidationException::withMessages(['avatar' => '5MB以下の画像（JPG / PNG / WEBP / GIF）を選んでください。']);
            }
            $extension = image_type_to_extension($info[2], false) ?: 'img';
            $path = 'messaging/'.$conversation->id.'/avatar-'.Str::lower((string) Str::ulid()).'.'.$extension;
            Uploads::storage()->put($path, (string) file_get_contents((string) $file->getRealPath()));
            $conversation->avatar_path = $path;
        } else {
            $conversation->avatar_path = null;
        }
        $conversation->save();
        if ($old !== null) {
            Uploads::delete($old);
        }
        $this->changed($conversation);

        return $conversation;
    }

    /** @param  array<string, mixed>  $values  add_members / edit_info / pin_messages / mention_all / share_history */
    public function updateSettings(Participant $actor, array $values): Conversation
    {
        $conversation = $this->access->requireGroup($actor);
        if (! $actor->isAdmin()) {
            throw new HttpException(403, 'グループの設定は管理者のみ変更できます。');
        }

        $settings = $conversation->groupSettings()->toArray();
        foreach (['add_members', 'edit_info', 'pin_messages', 'mention_all'] as $key) {
            if (isset($values[$key]) && GroupRule::tryFrom((string) $values[$key]) !== null) {
                $settings[$key] = (string) $values[$key];
            }
        }
        if (array_key_exists('share_history', $values)) {
            $settings['share_history'] = (bool) $values['share_history'];
        }
        if ($conversation->isChannel()) {
            if (isset($values['post']) && GroupRule::tryFrom((string) $values['post']) !== null) {
                $settings['post'] = (string) $values['post'];
            }
            if (array_key_exists('public', $values)) {
                $settings['public'] = (bool) $values['public'];
            }
        }
        $conversation->settings = $settings;
        $conversation->save();
        $this->changed($conversation);

        return $conversation;
    }

    /**
     * @param  list<int>  $userIds
     * @return list<int> who was added
     */
    public function addMembers(Participant $actor, array $userIds): array
    {
        $conversation = $this->access->requireGroup($actor);
        $settings = $conversation->groupSettings();
        $this->access->requireRule($actor, $settings->addMembers, 'メンバーの追加は管理者のみ行えます。');

        $current = $conversation->members()->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
        $ids = $this->addableIds($userIds, exclude: $current);
        if ($ids === []) {
            return [];
        }
        if (count($current) + count($ids) > (int) config('messaging.max_group_members', 500)) {
            throw ValidationException::withMessages(['members' => 'メンバーが多すぎます。']);
        }

        return DB::transaction(function () use ($actor, $conversation, $settings, $ids): array {
            $from = $settings->shareHistory ? null : $conversation->last_message_id;
            foreach ($ids as $id) {
                Participant::query()->updateOrCreate(
                    ['conversation_id' => $conversation->id, 'user_id' => $id],
                    [
                        'role' => ParticipantRole::Member, 'added_by' => $actor->user_id, 'joined_at' => now(),
                        'left_at' => null, 'removed_by' => null, 'visible_from_message_id' => $from,
                        // a re-added member starts afresh
                        'cleared_message_id' => null, 'hidden_at' => null, 'pinned_at' => null, 'archived_at' => null,
                        'favorite_at' => null, 'muted_until' => null,
                        'last_read_message_id' => $conversation->last_message_id, 'last_delivered_message_id' => $conversation->last_message_id,
                    ],
                );
            }
            $this->systemLine($conversation, 'added', $actor->user_id, ['users' => $ids]);
            MessagingAudit::record($actor->user, 'member.added', $conversation->id, ['users' => $ids]);

            return $ids;
        });
    }

    public function removeMember(Participant $actor, int $userId): void
    {
        $conversation = $this->access->requireGroup($actor);
        if ($userId === $actor->user_id) {
            $this->leave($actor);

            return;
        }
        if (! $actor->isAdmin()) {
            throw new HttpException(403, 'メンバーの削除は管理者のみ行えます。');
        }

        $member = $conversation->members()->where('user_id', $userId)->first();
        if (! $member instanceof Participant) {
            return;
        }

        DB::transaction(function () use ($actor, $conversation, $member): void {
            $member->forceFill(['left_at' => now(), 'removed_by' => $actor->user_id, 'role' => ParticipantRole::Member])->save();
            $this->systemLine($conversation, 'removed', $actor->user_id, ['users' => [$member->user_id]]);
            MessagingAudit::record($actor->user, 'member.removed', $conversation->id, ['users' => [$member->user_id]]);
            // the removed member's inbox drops it
            Signals::inboxesChanged([$member->user_id]);
        });
    }

    /** Leaves a group; the last 管理者 hands the role to the longest member, the last member leaves an empty group. */
    public function leave(Participant $actor): void
    {
        $conversation = $this->access->requireGroup($actor);

        DB::transaction(function () use ($actor, $conversation): void {
            $actor->forceFill(['left_at' => now(), 'role' => ParticipantRole::Member])->save();

            $members = $conversation->members()->orderBy('joined_at')->orderBy('id')->get();
            if ($members->isNotEmpty() && ! $members->contains(fn (Participant $member): bool => $member->isAdmin())) {
                $members->first()->forceFill(['role' => ParticipantRole::Admin])->save();
            }
            $this->systemLine($conversation, 'left', $actor->user_id, []);
            Signals::inboxesChanged([$actor->user_id]);
        });
    }

    public function setRole(Participant $actor, int $userId, ParticipantRole $role): void
    {
        $conversation = $this->access->requireGroup($actor);
        if (! $actor->isAdmin()) {
            throw new HttpException(403, '管理者の変更は管理者のみ行えます。');
        }

        $member = $conversation->members()->where('user_id', $userId)->first();
        if (! $member instanceof Participant || $member->role === $role) {
            return;
        }
        if ($role === ParticipantRole::Member && $conversation->members()->where('role', ParticipantRole::Admin->value)->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'グループには管理者が1人以上必要です。']);
        }

        DB::transaction(function () use ($actor, $conversation, $member, $role): void {
            $member->forceFill(['role' => $role])->save();
            $this->systemLine($conversation, $role === ParticipantRole::Admin ? 'admin_added' : 'admin_removed', $actor->user_id, ['users' => [$member->user_id]]);
            MessagingAudit::record($actor->user, 'member.role', $conversation->id, ['users' => [$member->user_id], 'role' => $role->value]);
        });
    }

    /**
     * The member's own state: pin / archive / favorite / mute (until, null = off) / hide (「トークを削除」: the history so
     * far is cleared for them only; the conversation comes back with the next message).
     */
    public function setState(Participant $me, string $state, mixed $value): Participant
    {
        $conversation = $me->conversation;
        $on = (bool) $value;
        match ($state) {
            'pinned' => [$me->pinned_at, $me->pin_order] = $on ? [now(), 0] : [null, null],
            'archived' => $me->archived_at = $on ? now() : null,
            'favorite' => $me->favorite_at = $on ? now() : null,
            'muted' => $me->muted_until = self::muteUntil($value),
            'level' => $me->notification_level = NotificationLevel::tryFrom((string) $value) ?? throw ValidationException::withMessages(['value' => '通知の設定が正しくありません。']),
            'hidden' => $this->hide($me, $conversation),
            default => throw ValidationException::withMessages(['state' => '不明な操作です。']),
        };
        $me->save();
        Signals::inboxesChanged([$me->user_id]);

        return $me;
    }

    private function hide(Participant $me, Conversation $conversation): void
    {
        $me->hidden_at = now();
        $me->cleared_message_id = $conversation->last_message_id;
        $me->last_read_message_id = max((int) $me->last_read_message_id, (int) $conversation->last_message_id) ?: null;
        $me->pinned_at = null;
    }

    /** 通知オフ: true / 'forever' = until it is switched on, a number = so many hours, false / null = on. */
    private static function muteUntil(mixed $value): ?Carbon
    {
        if ($value === false || $value === null || $value === 0 || $value === '0') {
            return null;
        }

        return is_numeric($value) && (int) $value > 0 && (int) $value < 24 * 365 ? now()->addHours((int) $value) : now()->addYears(100);
    }

    /**
     * A line of the conversation itself (参加 / 退出 / 名前の変更 …).
     *
     * @param  array<string, mixed>  $data
     */
    private function systemLine(Conversation $conversation, string $event, int $actorId, array $data): Message
    {
        $message = $conversation->messages()->create([
            'user_id' => null,
            'type' => MessageType::System,
            'body' => $this->systemText($event, $actorId, $data),
            'meta' => ['event' => $event, 'actor' => $actorId, ...$data],
        ]);
        $conversation->forceFill(['last_message_id' => $message->id, 'last_message_at' => $message->created_at])->save();
        $this->changed($conversation);

        return $message;
    }

    /** @param  array<string, mixed>  $data */
    private function systemText(string $event, int $actorId, array $data): string
    {
        $actor = $this->directory->name($actorId);
        $users = implode('、', array_map(fn (mixed $id): string => $this->directory->name((int) $id), (array) ($data['users'] ?? [])));

        return match ($event) {
            'created' => "{$actor}さんがグループ「{$data['name']}」を作成しました",
            'channel_created' => "{$actor}さんがチャンネル「{$data['name']}」を作成しました",
            'joined' => "{$actor}さんが参加しました",
            'archived' => "{$actor}さんがチャンネルをアーカイブしました",
            'restored' => "{$actor}さんがチャンネルを再開しました",
            'renamed' => "{$actor}さんがグループ名を「{$data['name']}」に変更しました",
            'added' => "{$actor}さんが{$users}さんを追加しました",
            'removed' => "{$actor}さんが{$users}さんを削除しました",
            'left' => "{$actor}さんが退出しました",
            'admin_added' => "{$users}さんが管理者になりました",
            'admin_removed' => "{$users}さんが管理者ではなくなりました",
            default => '',
        };
    }

    /** Every member of the conversation hears of the change (inbox and open chats). */
    public function changed(Conversation $conversation): void
    {
        Signals::conversationChanged($conversation->id, $conversation->members()->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    /**
     * @param  list<int>  $ids
     * @param  list<int>  $exclude
     * @return list<int> active people among the ids, without the excluded ones
     */
    private function addableIds(array $ids, array $exclude): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return array_values(array_filter($ids, fn (int $id): bool => ! in_array($id, $exclude, true) && $this->directory->isActive($id)));
    }
}
