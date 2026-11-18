{{--
    メッセージ in the top bar of the dashboard's home, right after アプリ (MessagingPlugin, GLOBAL_SEARCH_BEFORE): opens the
    messenger. The same Filament icon button as the bell next to it (gray, large icon, its badge) — same size, color and
    hover (user 2026-11-17). messaging::badge-script writes the unread count into the badge and shows it
    (sfk-has-unread), live with the pushes. $url
--}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp
<style>
    .fi-topbar-messages-btn:not(.sfk-has-unread) .fi-icon-btn-badge-ctn {
        display: none;
    }
</style>
<x-filament::icon-button
    tag="a"
    :href="$url"
    color="gray"
    :icon="Heroicon::OutlinedChatBubbleLeftRight"
    icon-size="lg"
    label="メッセージ"
    badge="0"
    class="fi-topbar-messages-btn"
    data-sfk-messages-link
/>
