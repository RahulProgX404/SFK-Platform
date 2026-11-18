{{--
    One comment card of the コメント tab (core::filament.record-comments; comment-design.png): the writer (avatar + name
    with the ユーザー情報 popup, あなた on one's own), how long ago (the date and time on hover), then the 宛先
    (@全員 / @name, in the brand colour) before the text, 編集済み after an edit, and the links 返信, and on one's own
    comments 編集 / 削除. 編集 turns the text into a text box with キャンセル / 保存.
--}}
@php
    use Filament\Support\Icons\Heroicon;
    use Modules\Core\Filament\Support\UserPopup;

    $own = $comment->user_id !== null && $comment->user_id === auth()->id();
    $editing = $own && $page->editingComment === $comment->id;
@endphp

<article @class(['sfk-comment-card', 'is-own' => $own, 'is-editing' => $editing]) wire:key="comment-{{ $comment->id }}">
    <header class="sfk-comment-head">
        <span class="sfk-comment-author">
            @if ($comment->user !== null)
                {{ UserPopup::html($comment->user) }}
            @else
                <span class="sfk-history-unknown">—</span>
            @endif
            @if ($own)
                <span class="sfk-comment-you">あなた</span>
            @endif
            @if ($comment->created_at !== null)
                <time datetime="{{ $comment->created_at->toIso8601String() }}" class="sfk-comment-time" title="{{ $comment->created_at->format('Y-m-d H:i') }}">{{ $comment->created_at->locale('ja')->diffForHumans() }}</time>
            @endif
        </span>

        @unless ($editing)
            <span class="sfk-comment-actions">
                <button type="button" class="sfk-comment-action" wire:click="replyToComment({{ $comment->id }})">
                    <x-filament::icon :icon="Heroicon::ArrowUturnLeft" class="sfk-comment-action-icon" />返信
                </button>
                @if ($own)
                    {{-- only on one's own comments (an action drawn here would show disabled when hidden) --}}
                    @if (($delete = ($page->deleteCommentAction)(['comment' => $comment->id]))->isVisible())
                        {{ $delete }}
                    @endif
                    <button type="button" class="sfk-comment-action" wire:click="editComment({{ $comment->id }})">
                        <x-filament::icon :icon="Heroicon::Pencil" class="sfk-comment-action-icon" />編集
                    </button>
                @endif
            </span>
        @endunless
    </header>

    @if ($editing)
        <form class="sfk-comment-edit" wire:submit="saveCommentEdit">
            <textarea
                class="sfk-comment-edit-input"
                rows="3"
                aria-label="コメント"
                wire:model="editingCommentBody"
                x-init="$nextTick(() => { $el.focus(); $el.setSelectionRange($el.value.length, $el.value.length) })"
                x-on:keydown.escape.prevent="$wire.cancelCommentEdit()"
            ></textarea>
            @error('editingCommentBody')
                <p class="sfk-comment-edit-error">{{ $message }}</p>
            @enderror
            <div class="sfk-comment-edit-foot">
                <button type="button" class="sfk-filter-btn" wire:click="cancelCommentEdit">キャンセル</button>
                <button type="submit" class="sfk-filter-btn sfk-filter-btn-submit" wire:loading.attr="disabled" wire:target="saveCommentEdit">保存</button>
            </div>
        </form>
    @else
        <p class="sfk-comment-body">
            @if ($comment->mentions_all)
                <span class="sfk-comment-mention">@全員</span>
            @endif
            @foreach ($comment->mentionedUsers() as $mentioned)
                <span class="sfk-comment-mention">{{ '@'.$mentioned->name }}</span>
            @endforeach
            {!! nl2br(e($comment->body)) !!}
        </p>
        @if ($comment->edited_at !== null)
            <p class="sfk-comment-edited" title="{{ $comment->edited_at->format('Y-m-d H:i') }}">編集済み</p>
        @endif
    @endif
</article>
