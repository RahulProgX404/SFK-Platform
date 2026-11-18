<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Files\Uploads;
use Modules\Core\Application\Notifications\Notice;
use Modules\Core\Application\Notifications\Notifier;
use Modules\Core\Application\Search\SearchQuery;
use Modules\Messaging\Domain\MessageText;
use Modules\Messaging\Domain\Priority;
use Modules\Messaging\Models\Announcement;
use Modules\Messaging\Models\AnnouncementAttachment;
use Modules\Messaging\Models\AnnouncementRecipient;
use Modules\Messaging\Models\Participant;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 全社アナウンス: administrators publish a title, text, files and a priority (通常 / 重要 / 至急), optionally ask for a 確認
 * and end it on a date — to everyone, some 従業員カテゴリ, the members of some of their groups / channels or some
 * employees, now or at a later time. After its end date it leaves the recipients' list (the publisher and
 * administrators still find it). The audience is fixed when it is published (one row per
 * recipient, which also keeps 既読 and 確認). Recipients get a 通知 in the bell when it is published; the publisher and
 * administrators see who read and confirmed. Ordinary users can neither publish nor read announcements not sent to them.
 */
final class Announcements
{
    public const NOTIFICATION = 'messaging.announcement';

    public function __construct(
        private readonly MessagingAccess $access,
        private readonly Directory $directory,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  array{title?: string, body?: string, priority?: string, urgent?: bool, requires_confirmation?: bool, audience?: string, audience_ids?: list<int>, published_at?: string|null, expires_at?: string|null}  $input
     * @param  list<UploadedFile>  $files
     */
    public function publish(User $me, array $input, array $files = []): Announcement
    {
        if (! $this->access->canAnnounce($me)) {
            throw new HttpException(403, '全社アナウンスを投稿する権限がありません。');
        }

        $title = trim((string) ($input['title'] ?? ''));
        $body = MessageText::clean($input['body'] ?? '');
        $errors = [];
        if ($title === '' || mb_strlen($title) > 150) {
            $errors['title'] = 'タイトルを150文字以内で入力してください。';
        }
        if ($body === '' || mb_strlen($body) > 20000) {
            $errors['body'] = '本文を入力してください。';
        }
        $audience = (string) ($input['audience'] ?? Announcement::AUDIENCE_ALL);
        if (! in_array($audience, [Announcement::AUDIENCE_ALL, Announcement::AUDIENCE_CATEGORIES, Announcement::AUDIENCE_USERS, Announcement::AUDIENCE_GROUPS], true)) {
            $errors['audience'] = '対象を選んでください。';
        }
        $ids = array_values(array_unique(array_map('intval', (array) ($input['audience_ids'] ?? []))));
        if ($audience !== Announcement::AUDIENCE_ALL && $ids === []) {
            $errors['audience_ids'] = '対象を選んでください。';
        }
        if (count($files) > 10) {
            $errors['files'] = 'ファイルは10件までです。';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $recipients = $this->audience($me, $audience, $ids);
        if ($recipients === []) {
            throw ValidationException::withMessages(['audience_ids' => '対象の従業員がいません。']);
        }

        $publishedAt = filled($input['published_at'] ?? null) ? Carbon::parse((string) $input['published_at']) : now();
        if ($publishedAt->isPast()) {
            $publishedAt = now();
        }
        $expiresAt = filled($input['expires_at'] ?? null) ? Carbon::parse((string) $input['expires_at']) : null;
        if ($expiresAt !== null && $expiresAt->lte($publishedAt)) {
            throw ValidationException::withMessages(['expires_at' => '掲載終了は公開日時より後にしてください。']);
        }
        $priority = isset($input['priority']) ? Priority::fromKey((string) $input['priority']) : ((bool) ($input['urgent'] ?? false) ? Priority::Important : Priority::Normal);

        $stored = array_map(fn (UploadedFile $file): array => $this->storeFile($file), $files);

        $announcement = DB::transaction(function () use ($me, $title, $body, $input, $audience, $ids, $publishedAt, $expiresAt, $priority, $recipients, $stored): Announcement {
            $announcement = Announcement::query()->create([
                'title' => $title,
                'body' => $body,
                'priority' => $priority->key(),
                'expires_at' => $expiresAt,
                'requires_confirmation' => (bool) ($input['requires_confirmation'] ?? false),
                'audience' => $audience,
                'audience_ids' => $audience === Announcement::AUDIENCE_ALL ? null : $ids,
                'published_at' => $publishedAt,
                'created_by' => $me->id,
            ]);
            foreach ($stored as $file) {
                $announcement->attachments()->create($file);
            }
            foreach (array_chunk($recipients, 500) as $chunk) {
                AnnouncementRecipient::query()->insert(array_map(fn (int $id): array => [
                    'announcement_id' => $announcement->id,
                    'user_id' => $id,
                    // the publisher has read their own
                    'read_at' => $id === $me->id ? now() : null,
                ], $chunk));
            }

            MessagingAudit::record($me, 'announcement.published', null, ['announcement' => $announcement->id, 'recipients' => count($recipients)]);

            return $announcement;
        });

        if (! $publishedAt->isFuture()) {
            $this->notify($announcement);
        }

        return $announcement;
    }

    /** Sends the 通知 of the announcements whose time came (scheduled ones; messaging:announcements, every minute). */
    public function notifyDue(): int
    {
        $due = Announcement::query()->whereNull('notified_at')->where('published_at', '<=', now())->get();
        foreach ($due as $announcement) {
            $this->notify($announcement);
        }

        return $due->count();
    }

    /**
     * The announcements sent to the user (newest first), with their own 既読 / 確認; administrators also see all of them.
     *
     * @return list<array<string, mixed>>
     */
    public function listFor(User $me, int $limit = 50, string $words = ''): array
    {
        $manager = $this->access->canAnnounce($me);
        $rows = Announcement::query()
            ->leftJoin('announcement_recipients as r', function ($join) use ($me): void {
                $join->on('r.announcement_id', '=', 'announcements.id')->where('r.user_id', '=', $me->id);
            })
            ->when(! $manager, fn ($query) => $query->whereNotNull('r.id')
                ->where(fn ($open) => $open->whereNull('announcements.expires_at')->orWhere('announcements.expires_at', '>', now())))
            ->when(trim($words) !== '', function ($query) use ($words): void {
                foreach (preg_split('/[\s\x{3000}]+/u', trim($words)) ?: [] as $word) {
                    if ($word !== '') {
                        $query->where(function ($either) use ($word): void {
                            SearchQuery::where($either, 'announcements.title', $word);
                            SearchQuery::where($either, 'announcements.body', $word, 'or');
                        });
                    }
                }
            })
            ->where(fn ($query) => $query->where('announcements.published_at', '<=', now())->orWhere('announcements.created_by', $me->id))
            ->orderByDesc('announcements.published_at')
            ->orderByDesc('announcements.id')
            ->limit($limit)
            ->get(['announcements.*', 'r.id as recipient_id', 'r.read_at as my_read_at', 'r.confirmed_at as my_confirmed_at']);

        return $rows->map(fn (Announcement $announcement): array => [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'preview' => MessageText::preview($announcement->body, 80),
            'priority' => $announcement->priority,
            'confirm' => $announcement->requires_confirmation,
            'by' => $announcement->created_by,
            'at' => $announcement->published_at->toIso8601String(),
            'scheduled' => $announcement->published_at->isFuture(),
            'expired' => $announcement->expires_at !== null && $announcement->expires_at->isPast(),
            'recipient' => $announcement->getAttribute('recipient_id') !== null,
            'read' => $announcement->getAttribute('my_read_at') !== null || $announcement->getAttribute('recipient_id') === null,
            'confirmed' => $announcement->getAttribute('my_confirmed_at') !== null,
        ])->all();
    }

    /**
     * One announcement (marks it read for a recipient); the publisher and administrators also get who read / confirmed.
     *
     * @return array<string, mixed>
     */
    public function show(User $me, int $id): array
    {
        $announcement = Announcement::query()->with('attachments')->find($id);
        if (! $announcement instanceof Announcement) {
            throw new NotFoundHttpException;
        }
        $mine = AnnouncementRecipient::query()->where('announcement_id', $id)->where('user_id', $me->id)->first();
        $manager = $this->access->canAnnounce($me);
        if (($mine === null && ! $manager) || ($announcement->published_at->isFuture() && ! $manager)
            || (! $manager && $announcement->expires_at !== null && $announcement->expires_at->isPast())) {
            throw new NotFoundHttpException;
        }

        if ($mine instanceof AnnouncementRecipient && $mine->read_at === null) {
            $mine->forceFill(['read_at' => now()])->save();
            Signals::inboxesChanged([$me->id]);
        }

        $data = [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'priority' => $announcement->priority,
            'confirm' => $announcement->requires_confirmation,
            'by' => $announcement->created_by,
            'at' => $announcement->published_at->toIso8601String(),
            'scheduled' => $announcement->published_at->isFuture(),
            'expires' => $announcement->expires_at?->toIso8601String(),
            'expired' => $announcement->expires_at !== null && $announcement->expires_at->isPast(),
            'confirmed' => $mine?->confirmed_at !== null,
            'recipient' => $mine !== null,
            'files' => $announcement->attachments->map(fn (AnnouncementAttachment $file): array => [
                'id' => $file->id,
                'name' => $file->original_name,
                'size' => $file->size,
                'mime' => $file->mime,
                'url' => route('messaging.announcements.file', ['file' => $file->id]),
            ])->all(),
            'stats' => null,
        ];

        if ($manager) {
            $recipients = AnnouncementRecipient::query()->where('announcement_id', $id)->get(['user_id', 'read_at', 'confirmed_at']);
            $data['stats'] = [
                'total' => $recipients->count(),
                'read' => $recipients->whereNotNull('read_at')->count(),
                'confirmed' => $recipients->whereNotNull('confirmed_at')->count(),
                'unread_users' => $recipients->whereNull('read_at')->pluck('user_id')->values()->all(),
                'unconfirmed_users' => $announcement->requires_confirmation ? $recipients->whereNull('confirmed_at')->pluck('user_id')->values()->all() : [],
                // each recipient with when they read and confirmed (the publisher's and administrators' view)
                'people' => $recipients->sortBy('user_id')->map(fn (AnnouncementRecipient $r): array => [
                    'user' => $r->user_id,
                    'read_at' => $r->read_at?->toIso8601String(),
                    'confirmed_at' => $r->confirmed_at?->toIso8601String(),
                ])->values()->all(),
            ];
        }

        return $data;
    }

    public function confirm(User $me, int $id): void
    {
        $mine = AnnouncementRecipient::query()->where('announcement_id', $id)->where('user_id', $me->id)->first();
        if (! $mine instanceof AnnouncementRecipient) {
            throw new NotFoundHttpException;
        }
        $mine->forceFill(['read_at' => $mine->read_at ?? now(), 'confirmed_at' => $mine->confirmed_at ?? now()])->save();
        Signals::inboxesChanged([$me->id]);
    }

    /** The announcement file, for a recipient or an administrator. */
    public function file(User $me, int $fileId): AnnouncementAttachment
    {
        $file = AnnouncementAttachment::query()->with('announcement')->find($fileId);
        if (! $file instanceof AnnouncementAttachment || ! $file->announcement instanceof Announcement) {
            throw new NotFoundHttpException;
        }
        $isRecipient = AnnouncementRecipient::query()->where('announcement_id', $file->announcement_id)->where('user_id', $me->id)->exists();
        if (! $this->access->canAnnounce($me) && (! $isRecipient || $file->announcement->published_at->isFuture())) {
            throw new NotFoundHttpException;
        }

        return $file;
    }

    /** Unread announcements of the user (the badge). */
    public function unreadCount(User $me): int
    {
        return AnnouncementRecipient::query()
            ->join('announcements', 'announcements.id', '=', 'announcement_recipients.announcement_id')
            ->where('announcement_recipients.user_id', $me->id)
            ->whereNull('announcement_recipients.read_at')
            ->where('announcements.published_at', '<=', now())
            ->where(fn ($open) => $open->whereNull('announcements.expires_at')->orWhere('announcements.expires_at', '>', now()))
            ->count();
    }

    private function notify(Announcement $announcement): void
    {
        $announcement->forceFill(['notified_at' => now()])->save();
        $ids = AnnouncementRecipient::query()->where('announcement_id', $announcement->id)->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();

        $this->notifier->send(
            Notice::make(self::NOTIFICATION)
                ->title((($p = Priority::fromKey($announcement->priority)) !== Priority::Normal ? '【'.$p->label().'】' : '').'全社アナウンス: '.$announcement->title)
                ->url(route('filament.erp.pages.messages', ['a' => $announcement->id]))
                ->openLabel('アナウンスを開く'),
            User::query()->whereIn('id', $ids)->get(),
            $announcement->created_by !== null ? User::query()->find($announcement->created_by) : null,
        );
        Signals::inboxesChanged($ids);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int> the active users of the audience
     */
    private function audience(User $me, string $audience, array $ids): array
    {
        $people = $this->directory->active();
        if ($audience === Announcement::AUDIENCE_GROUPS) {
            // only groups / channels the publisher is in: they choose among what they see
            $mine = Participant::query()->where('user_id', $me->id)->whereNull('left_at')->whereIn('conversation_id', $ids)->pluck('conversation_id')->all();
            $members = Participant::query()->whereIn('conversation_id', $mine ?: [0])->whereNull('left_at')->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->unique()->all();

            return array_values(array_map(fn (array $person): int => $person['id'], array_filter($people, fn (array $person): bool => in_array($person['id'], $members, true))));
        }

        return array_values(array_map(fn (array $person): int => $person['id'], array_filter($people, fn (array $person): bool => match ($audience) {
            Announcement::AUDIENCE_CATEGORIES => array_intersect($person['category_ids'], $ids) !== [],
            Announcement::AUDIENCE_USERS => in_array($person['id'], $ids, true),
            default => true,
        })));
    }

    /** @return array{path: string, original_name: string, mime: string, size: int} */
    private function storeFile(UploadedFile $file): array
    {
        $types = (array) config('messaging.attachments.types', []);
        $extension = strtolower($file->getClientOriginalExtension());
        if (! isset($types[$extension]) || $types[$extension] === 'voice' || ! $file->isValid() || $file->getSize() > (int) config('messaging.attachments.max_kb', 20480) * 1024) {
            throw ValidationException::withMessages(['files' => '添付できないファイルです: '.$file->getClientOriginalName()]);
        }

        $path = 'messaging/announcements/'.Str::lower((string) Str::ulid()).'.'.$extension;
        Uploads::storage()->put($path, (string) file_get_contents((string) $file->getRealPath()));

        return [
            'path' => $path,
            'original_name' => mb_substr((string) preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '_', $file->getClientOriginalName()), 0, 180),
            'mime' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
            'size' => (int) $file->getSize(),
        ];
    }
}
