{{--
    Panel on the right of an app record (Modules\Core\Filament\AppPages\AppViewRecord), legacy layout: the tabs
    コメント (core::filament.record-comments) and 変更履歴 (core::filament.record-history), each when it is on.
    Collapsed when the record opens (only a narrow bar with the tabs' icons, the record uses the width); a click on
    an icon opens the panel on that tab, 閉じる collapses it again. It stays open while a version is shown.
--}}
@php
    use Filament\Support\Icons\Heroicon;

    $page = $getLivewire();
    $tabs = array_filter([
        'comments' => $page->showsComments() ? ['コメント', Heroicon::OutlinedChatBubbleLeftEllipsis, 'sfk-comments-title'] : null,
        'history' => $page->showsHistory() ? ['変更履歴', Heroicon::OutlinedClock, 'sfk-history-title'] : null,
    ]);
    $open = $page->sidebarStartsOpen();
    $commentCount = isset($tabs['comments']) ? $page->recordComments()->count() : 0;
@endphp

<aside
    @class(['sfk-history', 'sfk-sidebar', 'is-collapsed' => ! $open])
    x-data="{ open: @js($open), tab: @js($page->sidebarTab()) }"
    x-bind:class="{ 'is-collapsed': ! open }"
    x-on:sfk-comment-reply.window="tab = 'comments'; open = true"
>
    {{-- collapsed: the tabs as icons --}}
    <div class="sfk-sidebar-rail" x-show="! open" @if ($open) x-cloak @endif>
        @foreach ($tabs as $tab => [$tabLabel, $tabIcon])
            <button
                type="button"
                class="sfk-sidebar-rail-btn"
                title="{{ $tabLabel }}"
                aria-expanded="false"
                x-on:click="tab = @js($tab); open = true"
            >
                <x-filament::icon :icon="$tabIcon" class="sfk-history-tab-icon" />
                <span class="fi-sr-only">{{ $tabLabel }}</span>
                @if ($tab === 'comments' && $commentCount > 0)
                    <span class="sfk-sidebar-rail-count">{{ $commentCount }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="sfk-sidebar-panel" x-show="open" @unless ($open) x-cloak @endunless>
        <div class="sfk-history-head sfk-sidebar-tabs" role="tablist">
            @foreach ($tabs as $tab => [$tabLabel, $tabIcon, $tabId])
                <button
                    type="button"
                    role="tab"
                    id="{{ $tabId }}"
                    class="sfk-sidebar-tab"
                    x-bind:class="{ 'is-active': tab === @js($tab) }"
                    x-bind:aria-selected="tab === @js($tab)"
                    x-on:click="tab = @js($tab)"
                >
                    <x-filament::icon :icon="$tabIcon" class="sfk-history-tab-icon" />
                    <span class="sfk-history-title">{{ $tabLabel }}</span>
                </button>
            @endforeach
            <button type="button" class="sfk-sidebar-close" title="閉じる" aria-expanded="true" x-on:click="open = false">
                <x-filament::icon :icon="Heroicon::ChevronDoubleRight" class="sfk-history-tab-icon" />
                <span class="fi-sr-only">閉じる</span>
            </button>
        </div>

        @isset($tabs['comments'])
            <section role="tabpanel" aria-labelledby="sfk-comments-title" x-show="tab === 'comments'" @if ($page->sidebarTab() !== 'comments') x-cloak @endif>
                @include('core::filament.record-comments')
            </section>
        @endisset

        @isset($tabs['history'])
            <section role="tabpanel" aria-labelledby="sfk-history-title" x-show="tab === 'history'" @if ($page->sidebarTab() !== 'history') x-cloak @endif>
                @include('core::filament.record-history')
            </section>
        @endisset
    </div>
</aside>
