<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain;

/**
 * The rules of one group or channel (conversations.settings): who may add members, change its name / image /
 * description, pin messages, mention everyone (@全員) and post at all (a read-only channel: 管理者のみ), whether members
 * added later read the history from before they joined (default: yes — a project's newcomer needs the earlier
 * discussion), and for a channel whether anyone may find and join it (public) or only invited members see it, and what
 * kind it is (部署 / プロジェクト).
 */
final readonly class GroupSettings
{
    public function __construct(
        public GroupRule $addMembers = GroupRule::Everyone,
        public GroupRule $editInfo = GroupRule::Admins,
        public GroupRule $pinMessages = GroupRule::Everyone,
        public GroupRule $mentionAll = GroupRule::Admins,
        public bool $shareHistory = true,
        public GroupRule $post = GroupRule::Everyone,
        public bool $public = false,
        public string $kind = 'project',
    ) {}

    /** @param  array<string, mixed>|null  $stored */
    public static function fromArray(?array $stored): self
    {
        $rule = fn (string $key, GroupRule $default): GroupRule => GroupRule::tryFrom((string) ($stored[$key] ?? '')) ?? $default;

        return new self(
            addMembers: $rule('add_members', GroupRule::Everyone),
            editInfo: $rule('edit_info', GroupRule::Admins),
            pinMessages: $rule('pin_messages', GroupRule::Everyone),
            mentionAll: $rule('mention_all', GroupRule::Admins),
            shareHistory: (bool) ($stored['share_history'] ?? true),
            post: $rule('post', GroupRule::Everyone),
            public: (bool) ($stored['public'] ?? false),
            kind: in_array($stored['kind'] ?? null, ['department', 'project'], true) ? (string) $stored['kind'] : 'project',
        );
    }

    /** @return array{add_members: string, edit_info: string, pin_messages: string, mention_all: string, share_history: bool, post: string, public: bool, kind: string} */
    public function toArray(): array
    {
        return [
            'add_members' => $this->addMembers->value,
            'edit_info' => $this->editInfo->value,
            'pin_messages' => $this->pinMessages->value,
            'mention_all' => $this->mentionAll->value,
            'share_history' => $this->shareHistory,
            'post' => $this->post->value,
            'public' => $this->public,
            'kind' => $this->kind,
        ];
    }
}
