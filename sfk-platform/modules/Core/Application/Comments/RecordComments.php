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
use Modules\Core\Models\RecordComment;

/**
 * コメント of app records (legacy record comments): written by anyone who may view the record, optionally addressed to
 * users (宛先); the 宛先 and the record's comment recipients (e.g. 作成者 / 更新者) get a 通知 with a link to the
 * record, except the writer and users who may not open the record (Notifier). Only the writer deletes a comment.
 */
final class RecordComments
{
    /** The kind of its 通知 (NotificationKinds, registered by Core). */
    public const NOTIFICATION = 'comment';

    /** Longest comment, like the legacy system. */
    public const MAX_LENGTH = 65535;

    public function __construct(private readonly Notifier $notifier) {}

    /** @return Collection<int, RecordComment> newest first */
    public function comments(Model $record): Collection
    {
        return $this->query($record)->with(['user.employeeCategories', 'user.position'])->orderByDesc('id')->get();
    }

    /**
     * @param  list<int>  $mentions  宛先 (user ids; unknown ids are dropped)
     * @param  string  $subject  what the 通知 is about, e.g. 「01. 取引先マスタ: 株式会社テスト印刷」
     */
    public function post(Model&HasRecordComments $record, User $writer, string $body, array $mentions, string $subject, string $url): RecordComment
    {
        $addressed = User::query()->whereIn('id', array_map('intval', $mentions))->orderBy('name')->get();

        $comment = RecordComment::query()->create([
            'record_type' => $record->getMorphClass(),
            'record_id' => $record->getKey(),
            'user_id' => $writer->id,
            'body' => $body,
            'mentions' => $addressed->modelKeys(),
        ]);

        $this->notifier->send(
            Notice::make(self::NOTIFICATION)
                ->about($record)
                ->title($subject)
                ->body($writer->name.': '.Str::limit(preg_replace("/\s+/u", ' ', $body) ?? $body, 100))
                ->url($url),
            [...$addressed->all(), ...$record->commentRecipients()],
            $writer,
        );

        return $comment;
    }

    /** Deletes the comment when the user wrote it; false otherwise. */
    public function delete(Model $record, int $commentId, User $user): bool
    {
        return $this->query($record)->whereKey($commentId)->where('user_id', $user->id)->delete() > 0;
    }

    /** @return Builder<RecordComment> */
    private function query(Model $record): Builder
    {
        return RecordComment::query()->where('record_type', $record->getMorphClass())->where('record_id', $record->getKey());
    }
}
