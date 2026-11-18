<?php

declare(strict_types=1);

namespace Modules\Core\Application\Comments;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Core\Application\Notifications\Notice;
use Modules\Core\Application\Notifications\Notifier;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Models\RecordComment;

/**
 * コメント of app records (legacy record comments): written by anyone who may view the record, optionally addressed to
 * users (宛先) or to 全員; the 宛先 and the record's comment recipients (e.g. 作成者 / 更新者) get a 通知 with a link to
 * the record, except the writer and users who may not open the record (Notifier). A reply (返信) sits under the comment
 * it answers — one level: a reply to a reply joins the same thread. Only the writer edits (編集, no new 通知) or deletes
 * a comment.
 */
final class RecordComments
{
    /** The kind of its 通知 (NotificationKinds, registered by Core). */
    public const NOTIFICATION = 'comment';

    /** Longest comment, like the legacy system. */
    public const MAX_LENGTH = 65535;

    public function __construct(private readonly Notifier $notifier) {}

    /** @return Collection<int, RecordComment> every comment, newest first */
    public function comments(Model $record): Collection
    {
        return $this->query($record)->with(['user.employeeCategories', 'user.position'])->orderByDesc('id')->get();
    }

    /**
     * The comments as threads: comments newest first, each with its replies oldest first (RecordComment::replies()) and
     * every comment's 宛先 (RecordComment::mentionedUsers()) — three queries whatever the number of comments.
     *
     * @return Collection<int, RecordComment>
     */
    public function threads(Model $record): Collection
    {
        $comments = $this->comments($record);

        $mentioned = $comments->flatMap(fn (RecordComment $comment): array => $comment->mentions)->unique()->values()->all();
        $users = $mentioned === []
            ? new Collection
            : User::query()->with(['employeeCategories', 'position'])->whereIn('id', $mentioned)->orderBy('name')->get();

        $ids = $comments->modelKeys();
        $replies = $comments->filter(fn (RecordComment $comment): bool => $comment->parent_id !== null && in_array($comment->parent_id, $ids, true))
            ->sortBy('id')
            ->groupBy('parent_id');

        foreach ($comments as $comment) {
            $comment->setRelation('mentioned', $users->filter(fn (User $user): bool => in_array($user->id, $comment->mentions, true))->values());
            $comment->setRelation('replies', new Collection($replies->get($comment->id)?->values()->all() ?? []));
        }

        // a reply whose comment is gone stands on its own
        return $comments->reject(fn (RecordComment $comment): bool => $comment->parent_id !== null && in_array($comment->parent_id, $ids, true))->values();
    }

    /**
     * @param  list<int>  $mentions  宛先 (user ids; unknown ids are dropped)
     * @param  bool  $toEveryone  宛先 「全員」: every user who may sign in (the Notifier leaves out who may not open the record)
     * @param  int|null  $replyTo  the comment answered (返信); a reply to a reply joins its thread
     * @param  string  $subject  what the 通知 is about, e.g. 「01. 取引先マスタ: 株式会社テスト印刷」
     */
    public function post(Model&HasRecordComments $record, User $writer, string $body, array $mentions, string $subject, string $url, bool $toEveryone = false, ?int $replyTo = null): RecordComment
    {
        $addressed = User::query()->whereIn('id', array_map('intval', $mentions))->orderBy('name')->get();
        $parent = $replyTo !== null ? $this->query($record)->whereKey($replyTo)->first() : null;

        $comment = RecordComment::query()->create([
            'record_type' => $record->getMorphClass(),
            'record_id' => $record->getKey(),
            'parent_id' => $parent !== null ? ($parent->parent_id ?? $parent->id) : null,
            'user_id' => $writer->id,
            'body' => $body,
            'mentions' => $addressed->modelKeys(),
            'mentions_all' => $toEveryone,
        ]);

        $everyone = $toEveryone
            ? User::query()->whereIn('employment_status', array_map(
                fn (EmploymentStatus $status): string => $status->value,
                array_filter(EmploymentStatus::cases(), fn (EmploymentStatus $status): bool => $status->canSignIn()),
            ))->get()->all()
            : [];

        $this->notifier->send(
            Notice::make(self::NOTIFICATION)
                ->about($record)
                ->title($subject)
                ->body($writer->name.': '.Str::limit(preg_replace("/\s+/u", ' ', $body) ?? $body, 100))
                ->url($url),
            [...$addressed->all(), ...$everyone, ...$record->commentRecipients()],
            $writer,
        );

        return $comment;
    }

    /** 編集: changes the text of the comment when the user wrote it; false otherwise. Nobody is notified again. */
    public function update(Model $record, int $commentId, User $user, string $body): bool
    {
        return $this->query($record)->whereKey($commentId)->where('user_id', $user->id)->update(['body' => $body, 'edited_at' => now()]) > 0;
    }

    /** Deletes the comment when the user wrote it; false otherwise. Its replies stay, as comments of their own. */
    public function delete(Model $record, int $commentId, User $user): bool
    {
        $comment = $this->query($record)->whereKey($commentId)->where('user_id', $user->id)->first();

        return $comment !== null && (bool) $comment->delete();
    }

    /** @return Builder<RecordComment> */
    private function query(Model $record): Builder
    {
        return RecordComment::query()->where('record_type', $record->getMorphClass())->where('record_id', $record->getKey());
    }
}
