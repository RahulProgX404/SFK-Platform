{{--
    コメント tab of the record panel (core::filament.record-sidebar; comment-design.png): 宛先 (全員 or users) and the
    text with 書き込む — while answering a comment, 「…への返信」 with × above it — then the comments newest first as
    cards (core::filament.record-comment), each with its replies under it, oldest first, behind a thread line.
--}}
@php
    use Filament\Support\Icons\Heroicon;

    $page = $getLivewire();
    $comments = $page->recordComments();
    $replyTarget = $page->commentReplyTarget();
@endphp

<div class="sfk-comments">
    <form class="sfk-comment-form" wire:submit="postComment"
          x-on:sfk-comment-reply.window="$nextTick(() => $el.querySelector('[data-sfk-comment-body]')?.focus())">
        @if ($replyTarget !== null)
            <div class="sfk-comment-replying">
                <x-filament::icon :icon="Heroicon::ArrowUturnLeft" class="sfk-comment-action-icon" />
                <span class="sfk-comment-replying-text">{{ ($replyTarget->user?->name ?? '—').' への返信' }}</span>
                <button type="button" class="sfk-comment-replying-cancel" title="返信をやめる" wire:click="cancelCommentReply">
                    <x-filament::icon :icon="Heroicon::XMark" class="sfk-comment-action-icon" />
                    <span class="fi-sr-only">返信をやめる</span>
                </button>
            </div>
        @endif
        {{ $page->commentForm }}
        <div class="sfk-comment-form-foot">
            <button type="submit" class="sfk-filter-btn sfk-filter-btn-submit sfk-comment-submit" wire:loading.attr="disabled" wire:target="postComment">書き込む</button>
        </div>
    </form>

    @if ($comments->isEmpty())
        <p class="sfk-history-empty">コメントはありません。</p>
    @else
        <ol class="sfk-comment-list">
            @foreach ($comments as $comment)
                <li class="sfk-comment-thread" wire:key="thread-{{ $comment->id }}">
                    @include('core::filament.record-comment', ['comment' => $comment])
                    @if ($comment->replies()->isNotEmpty())
                        <ol class="sfk-comment-replies">
                            @foreach ($comment->replies() as $reply)
                                <li wire:key="reply-{{ $reply->id }}">
                                    @include('core::filament.record-comment', ['comment' => $reply])
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</div>
