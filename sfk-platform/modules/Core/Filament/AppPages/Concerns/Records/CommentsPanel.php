<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages\Concerns\Records;

use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\Comments\RecordComments;
use Modules\Core\Application\Users\UserChoices;
use Modules\Core\Models\RecordComment;

/**
 * コメント of the record page's side panel (records implementing HasRecordComments, その他の設定): 宛先 (全員 or users)
 * + text → 書き込む, comment cards newest first with their replies, 返信 and, on one's own, 編集 / 削除.
 *
 * Part of Modules\Core\Filament\AppPages\AppViewRecord (the shared record page).
 */
trait CommentsPanel
{
    /** @var array<string, mixed>|null コメント being written: body, mentions (宛先; 「全員」 = COMMENT_TO_EVERYONE) */
    public ?array $commentData = [];

    /** 返信する: the comment the one being written answers */
    public ?int $commentReplyTo = null;

    /** 編集する: the own comment being edited, and its text */
    public ?int $editingComment = null;

    public string $editingCommentBody = '';

    /** 宛先 「全員」 */
    public const COMMENT_TO_EVERYONE = 'all';

    /** @var Collection<int, RecordComment>|null the comment threads, read once per request */
    private ?Collection $commentThreads = null;

    public function showsComments(): bool
    {
        return ! $this->printMode && $this->record instanceof HasRecordComments && $this->appSetting('record_comments_enabled');
    }

    /** 宛先 (「全員」 first, then the users who may sign in) + コメント → 書き込む */
    public function commentForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('commentData')
            ->components([
                Select::make('mentions')
                    ->label('宛先')
                    ->multiple()
                    ->searchable()
                    // the option list closes once a 宛先 is chosen (user, 2026-11-16); a click opens it again for the next
                    // one. Filament keeps a multiple select open, so its Alpine component (`select`, `state`) is told here —
                    // two frames later, after Filament placed the list for the new chips: closed before that, the list kept a
                    // position measured while hidden and the next opening scrolled the page to it
                    ->extraAlpineAttributes(['x-init' => "\$watch('state', () => requestAnimationFrame(() => requestAnimationFrame(() => { if (select?.isOpen) { select.closeDropdown(); select.selectButton?.focus({ preventScroll: true }); } })))"])
                    // `+` keeps the user ids as keys (a spread would number them anew)
                    ->options(fn (): array => [self::COMMENT_TO_EVERYONE => '全員'] + UserChoices::active())
                    // the choices go with the page: opening the list asks the server nothing (a closure would make Filament
                    // fetch them again on every opening — a round trip each time the list is reopened for the next 宛先)
                    ->dynamicOptions(false),
                Textarea::make('body')
                    ->label('コメント')
                    ->hiddenLabel()
                    ->required()
                    ->maxLength(RecordComments::MAX_LENGTH)
                    ->rows(3)
                    ->autosize()
                    ->extraInputAttributes(['data-sfk-comment-body' => true]),
            ]);
    }

    /** @return Collection<int, RecordComment> the comment threads: newest first, each with its replies oldest first */
    public function recordComments(): Collection
    {
        if (! $this->showsComments()) {
            return new Collection;
        }

        return $this->commentThreads ??= app(RecordComments::class)->threads($this->record);
    }

    /** Comments and replies (the count on the collapsed panel). */
    public function recordCommentCount(): int
    {
        return $this->recordComments()->sum(fn (RecordComment $comment): int => 1 + $comment->replies()->count());
    }

    /** A comment or reply of the record. */
    public function recordComment(int $commentId): ?RecordComment
    {
        foreach ($this->recordComments() as $comment) {
            if ($comment->id === $commentId) {
                return $comment;
            }
            $reply = $comment->replies()->firstWhere('id', $commentId);
            if ($reply !== null) {
                return $reply;
            }
        }

        return null;
    }

    /** The comment the one being written answers (返信する), while it is there. */
    public function commentReplyTarget(): ?RecordComment
    {
        return $this->commentReplyTo !== null ? $this->recordComment($this->commentReplyTo) : null;
    }

    public function postComment(): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $this->showsComments() || ! $this->record instanceof HasRecordComments) {
            return;
        }

        $data = $this->commentForm->getState();
        $mentions = array_map('strval', (array) ($data['mentions'] ?? []));
        app(RecordComments::class)->post(
            $this->record,
            $user,
            trim((string) $data['body']),
            array_map('intval', array_values(array_filter($mentions, fn (string $id): bool => ctype_digit($id)))),
            $this->appResource()::displayName().': '.$this->getRecordTitle(),
            static::getResource()::getUrl('view', ['record' => $this->record]),
            toEveryone: in_array(self::COMMENT_TO_EVERYONE, $mentions, true),
            replyTo: $this->commentReplyTarget()?->id,
        );

        $this->commentReplyTo = null;
        $this->commentThreads = null;
        $this->commentForm->fill();
    }

    /** 返信する: the comment being written answers the given one and is addressed to its writer. */
    public function replyToComment(int $commentId): void
    {
        $comment = $this->recordComment($commentId);
        if ($comment === null) {
            return;
        }

        $mentions = array_map('strval', (array) ($this->commentData['mentions'] ?? []));
        if ($comment->user_id !== null && $comment->user_id !== auth()->id()) {
            $mentions = [(string) $comment->user_id];
        }

        $this->commentReplyTo = $comment->id;
        $this->commentForm->fill(['body' => $this->commentData['body'] ?? '', 'mentions' => $mentions]);
        $this->dispatch('sfk-comment-reply');
    }

    public function cancelCommentReply(): void
    {
        $this->commentReplyTo = null;
    }

    /** 編集する: one's own comment, edited in place. */
    public function editComment(int $commentId): void
    {
        $comment = $this->recordComment($commentId);
        if ($comment === null || $comment->user_id === null || $comment->user_id !== auth()->id()) {
            return;
        }

        $this->editingComment = $comment->id;
        $this->editingCommentBody = $comment->body;
        $this->resetValidation('editingCommentBody');
    }

    public function cancelCommentEdit(): void
    {
        $this->editingComment = null;
        $this->editingCommentBody = '';
        $this->resetValidation('editingCommentBody');
    }

    public function saveCommentEdit(): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $this->showsComments() || $this->editingComment === null) {
            return;
        }

        $this->validate(
            ['editingCommentBody' => ['required', 'string', 'max:'.RecordComments::MAX_LENGTH]],
            attributes: ['editingCommentBody' => 'コメント'],
        );

        app(RecordComments::class)->update($this->record, $this->editingComment, $user, trim($this->editingCommentBody));
        $this->commentThreads = null;
        $this->cancelCommentEdit();
    }

    public function deleteCommentAction(): Action
    {
        return Action::make('deleteComment')
            ->label('削除')
            ->icon(Heroicon::Trash)
            ->extraAttributes(['class' => 'sfk-comment-action is-danger'])
            ->link()
            ->color('danger')
            ->visible(fn (array $arguments): bool => $this->showsComments()
                && ($writer = $this->recordComment((int) ($arguments['comment'] ?? 0))?->user_id) !== null
                && $writer === auth()->id())
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalIconColor('danger')
            ->modalWidth('md')
            ->modalHeading('コメントを削除しますか？')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('削除する')->color('danger'))
            ->modalCancelActionLabel('キャンセル')
            ->action(function (array $arguments): void {
                $user = auth()->user();
                if ($user instanceof User) {
                    $id = (int) ($arguments['comment'] ?? 0);
                    app(RecordComments::class)->delete($this->record, $id, $user);
                    $this->commentThreads = null;
                    if ($this->commentReplyTo === $id) {
                        $this->commentReplyTo = null;
                    }
                    if ($this->editingComment === $id) {
                        $this->cancelCommentEdit();
                    }
                }
            });
    }
}
