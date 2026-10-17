{{--
    コメント tab of the record panel (core::filament.record-sidebar), legacy layout: 宛先 and the text with 書き込む,
    then the comments newest first (who with the ユーザー情報 popup, when, 宛先, text) with 返信する and, for the
    writer, 削除する.
--}}
@php
    use Modules\Core\Filament\Support\UserPopup;

    $page = $getLivewire();
    $comments = $page->recordComments();
@endphp

<div class="sfk-comments">
    <form class="sfk-comment-form" wire:submit="postComment"
          x-on:sfk-comment-reply.window="$nextTick(() => $el.querySelector('[data-sfk-comment-body]')?.focus())">
        {{ $page->commentForm }}
        <div class="sfk-comment-form-foot">
            <button type="submit" class="sfk-filter-btn sfk-filter-btn-submit sfk-comment-submit" wire:loading.attr="disabled" wire:target="postComment">書き込む</button>
        </div>
    </form>

    @if ($comments->isEmpty())
        <p class="sfk-history-empty">コメントはありません。</p>
    @else
        <ol class="sfk-history-list sfk-comment-list">
            @foreach ($comments as $comment)
                <li class="sfk-history-item sfk-comment" wire:key="comment-{{ $comment->id }}">
                    <div class="sfk-history-item-head">
                        <span class="sfk-history-who sfk-comment-who">
                            @if ($comment->user !== null)
                                {{ UserPopup::html($comment->user) }}
                            @else
                                <span class="sfk-history-unknown">—</span>
                            @endif
                        </span>
                        <time datetime="{{ $comment->created_at?->toIso8601String() }}" class="sfk-history-date">{{ $comment->created_at?->format('Y-m-d H:i') }}</time>
                    </div>
                    @if ($comment->mentions !== [])
                        <p class="sfk-comment-mentions">
                            @foreach ($comment->mentionedUsers() as $mentioned)
                                <span class="sfk-comment-mention">{{ '@'.$mentioned->name }}</span>
                            @endforeach
                        </p>
                    @endif
                    <p class="sfk-comment-body">{!! nl2br(e($comment->body)) !!}</p>
                    <div class="sfk-history-actions">
                        <button type="button" class="sfk-filter-link" wire:click="replyToComment({{ $comment->id }})">返信する</button>
                        {{ ($page->deleteCommentAction)(['comment' => $comment->id]) }}
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
